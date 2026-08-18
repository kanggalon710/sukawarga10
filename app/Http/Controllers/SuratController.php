<?php

namespace App\Http\Controllers;

use App\Models\Surat;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\AppSetting;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuratController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $level = $user->levelEfektif();

        // Penyaringan di sini adalah aturan KEPEMILIKAN, bukan izin: siapa pun
        // yang tidak melihat surat orang lain hanya melihat miliknya. Tombol
        // aksi di blade memakai bolehkah(), bukan nama level.
        $isWarga = $level === 'warga';
        $surats = $isWarga
            ? Surat::where('user_id', $user->id)->orderByDesc('created_at')->get()
            : Surat::orderByDesc('created_at')->get();

        $wargaList = Keluarga::where('status', 'aktif')->orderBy('nama')->get();

        return view('layanan.surat', compact('surats', 'wargaList', 'user', 'level', 'isWarga'));
    }

    public function store(Request $request)
    {
        $request->validate(['kodeSurat' => ['required', Rule::in(Surat::KODE_VALID)]]);

        $user = auth()->user();
        // Sekretaris memegang `surat.buat` (terbit langsung); warga hanya
        // `surat.ajukan` (masuk antrean tanda tangan RT -> RW -> sekretaris).
        $isWarga = ! bolehkah('surat.buat');

        $pemohon = $request->pemohon === '__luar__'
            ? $request->pemohon_manual
            : $request->pemohon;

        $tahun = date('Y');
        $nomorUrut = Surat::where('tahun', $tahun)->max('nomorUrut') + 1;
        // Nomor RW dari tenant request (dulu hardcode "RW10" untuk semua tenant).
        $nomorSurat = sprintf('%03d/%s/RW%s/%s', $nomorUrut, $request->kodeSurat, wilayahTenant()['rw'], $tahun);
        // Nomor hasil edit manual bisa sudah memakai nomor yang akan dihasilkan
        // sekuens otomatis; geser urut sampai bebas bentrok (per tenant, via scope).
        while (Surat::where('nomorSurat', $nomorSurat)->exists()) {
            $nomorUrut++;
            $nomorSurat = sprintf('%03d/%s/RW%s/%s', $nomorUrut, $request->kodeSurat, wilayahTenant()['rw'], $tahun);
        }

        // Determine RT for the warga
        $rt = null;
        if ($isWarga) {
            $keluarga = Keluarga::where('nama', $pemohon)->first();
            $rt = $keluarga->rt ?? $user->rt ?? null;
        }

        $surat = Surat::create([
            'surat_id'      => 'SRT-' . uniqid(),
            'kodeSurat'     => $request->kodeSurat,
            'tahun'         => $tahun,
            'nomorUrut'     => $nomorUrut,
            'nomorSurat'    => $nomorSurat,
            'tanggal'       => now()->toDateString(),
            'pemohon'       => $pemohon,
            'keperluan'     => $request->keperluan,
            'user_id'       => $user->id,
            'rt_target'     => $rt,
            'approval_step' => $isWarga ? 'diajukan' : 'selesai',
            'status'        => $isWarga ? 'draft' : 'selesai',
        ]);

        AuditLogService::log('create', 'surat', "Buat surat: $nomorSurat untuk $pemohon");

        // If warga submits → notify RT
        if ($isWarga && NotificationService::isEnabled('notif_surat_diajukan')) {
            $msg = "📋 *SURAT BARU PERLU TTD*\n\nSurat {$nomorSurat} ({$request->kodeSurat})\nPemohon: {$pemohon}\nKeperluan: {$request->keperluan}\n\nSilakan login untuk review dan tanda tangan.";
            if ($rt) {
                NotificationService::notifyRT($rt, $msg);
            } else {
                NotificationService::notifyPengurus($msg);
            }
        }

        if ($isWarga) {
            return redirect()->route('surat.index')
                ->with('success', "Surat $nomorSurat berhasil diajukan. Menunggu persetujuan RT → RW → Sekretaris.");
        }

        // Admin/pengurus → langsung cetak
        return redirect()->route('surat.cetak', $surat->id)
            ->with('success', "Surat $nomorSurat berhasil dibuat.");
    }

    public function approve(Request $request, $id)
    {
        $surat = Surat::findOrFail($id);
        $user = auth()->user();
        $step = $surat->approval_step;

        // Tahap berjalan menentukan kapabilitas yang dibutuhkan (peta tunggal
        // di Surat::KAPABILITAS_TAHAP, dipakai blade juga). Dulu di sini
        // dicocokkan NAMA level, sehingga akun bawaan berlevel 'admin' lolos
        // middleware tapi ditolak di baris ini.
        if (! bolehkah(Surat::KAPABILITAS_TAHAP[$step] ?? '')) {
            return back()->with('error', 'Anda tidak memiliki akses untuk approve surat ini pada tahap ini.');
        }

        if ($step === 'diajukan') {
            $surat->update([
                'approval_step' => 'ttd_rt',
                'rt_signed_by'  => $user->namaLengkap ?? $user->username,
                'rt_signed_at'  => now(),
            ]);
            AuditLogService::log('update', 'surat', "TTD RT surat {$surat->nomorSurat} oleh {$user->username}");

            // Notify RW
            if (NotificationService::isEnabled('notif_surat_ttd_rw')) {
                $msg = "✍️ *SURAT PERLU TTD RW*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nPemohon: {$surat->pemohon}\nSudah ditandatangani RT. Giliran RW untuk tanda tangan.\n\nLogin untuk review.";
                NotificationService::notifyByKapabilitas('surat.ttdRw', $msg);
            }

            return back()->with('success', "Surat {$surat->nomorSurat} berhasil ditandatangani RT.");

        } elseif ($step === 'ttd_rt') {
            $surat->update([
                'approval_step' => 'ttd_rw',
                'rw_signed_by'  => $user->namaLengkap ?? $user->username,
                'rw_signed_at'  => now(),
            ]);
            AuditLogService::log('update', 'surat', "TTD RW surat {$surat->nomorSurat} oleh {$user->username}");

            // Notify Sekretaris/Admin
            if (NotificationService::isEnabled('notif_surat_cap')) {
                $msg = "🔏 *SURAT PERLU CAP SEKRETARIS*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nPemohon: {$surat->pemohon}\nSudah TTD RT & RW. Tinggal cap sekretaris.\n\nLogin untuk finalize.";
                NotificationService::notifyByKapabilitas('surat.finalisasi', $msg);
            }

            return back()->with('success', "Surat {$surat->nomorSurat} berhasil ditandatangani RW.");

        } elseif ($step === 'ttd_rw') {
            $surat->update([
                'approval_step' => 'selesai',
                'status'        => 'selesai',
                'sek_signed_by' => $user->namaLengkap ?? $user->username,
                'sek_signed_at' => now(),
            ]);
            AuditLogService::log('update', 'surat', "Finalize surat {$surat->nomorSurat} oleh {$user->username}");

            // Notify warga
            if ($surat->user_id && NotificationService::isEnabled('notif_surat_selesai')) {
                $wargaUser = User::find($surat->user_id);
                if ($wargaUser && $wargaUser->wa) {
                    $msg = "✅ *SURAT ANDA SELESAI*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nTelah ditandatangani RT, RW, dan dicap Sekretaris.\n\nSurat sudah bisa diambil atau dicetak.";
                    NotificationService::notifyWarga($wargaUser->wa, $msg);
                }
            }

            return back()->with('success', "Surat {$surat->nomorSurat} berhasil di-finalize dan dicap sekretaris.");
        }

        return back()->with('error', 'Anda tidak memiliki akses untuk approve surat ini pada tahap ini.');
    }

    public function reject(Request $request, $id)
    {
        $surat = Surat::findOrFail($id);
        $user = auth()->user();
        $step = $surat->approval_step;

        // Dulu method ini tidak memeriksa APA PUN: petugas RT bisa menolak
        // surat yang sudah ditandatangani RW bahkan yang sudah dicap selesai.
        if (in_array($step, Surat::TAHAP_AKHIR, true)) {
            return back()->with('error', 'Surat ini sudah selesai atau sudah ditolak, tidak bisa ditolak lagi.');
        }

        // Boleh menolak bila memegang tahap yang sedang berjalan, atau punya
        // kewenangan tolak menyeluruh (ketua RW dan petugas RT).
        if (! bolehkah(Surat::KAPABILITAS_TAHAP[$step] ?? '', 'surat.tolak')) {
            return back()->with('error', 'Anda tidak memiliki akses untuk menolak surat ini pada tahap ini.');
        }

        $surat->update([
            'approval_step'   => 'ditolak',
            'status'          => 'ditolak',
            'rejected_by'     => $user->namaLengkap ?? $user->username,
            'rejected_reason' => $request->alasan ?? 'Tidak ada alasan',
        ]);

        AuditLogService::log('update', 'surat', "Tolak surat {$surat->nomorSurat} oleh {$user->username}: {$request->alasan}");

        // Notify warga
        if ($surat->user_id) {
            $wargaUser = User::find($surat->user_id);
            if ($wargaUser && $wargaUser->wa) {
                $msg = "❌ *SURAT DITOLAK*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nAlasan: {$request->alasan}\n\nSilakan hubungi pengurus untuk info lebih lanjut.";
                NotificationService::notifyWarga($wargaUser->wa, $msg);
            }
        }

        return back()->with('success', "Surat {$surat->nomorSurat} ditolak.");
    }

    /**
     * Warga hanya boleh membuka surat miliknya sendiri. Tanpa ini, id di URL
     * cukup untuk membaca surat warga lain (peran saja tidak menjamin kepemilikan).
     */
    private function pastikanBolehLihat(Surat $surat): void
    {
        $user = auth()->user();
        if ($user->levelEfektif() === 'warga' && $surat->user_id !== $user->id) {
            abort(403, 'Anda hanya dapat membuka surat milik sendiri.');
        }
    }

    public function show($id)
    {
        $surat = Surat::findOrFail($id);
        $this->pastikanBolehLihat($surat);
        return response()->json($surat);
    }

    public function update(Request $request, $id)
    {
        $surat = Surat::findOrFail($id);
        $validated = $request->validate([
            'pemohon' => 'required|string|max:150',
            'keperluan' => 'nullable|string|max:255',
            'kodeSurat' => ['required', Rule::in(Surat::KODE_VALID)],
            'nomorSurat' => ['required', 'string', 'max:100',
                // Closure ber-scope tenant, BUKAN Rule::unique: Rule::unique
                // melewati global scope ScopedKeOrganisasi sehingga nomor milik
                // tenant lain ikut menolak.
                function ($attribute, $value, $fail) use ($surat) {
                    if (Surat::where('nomorSurat', $value)->whereKeyNot($surat->id)->exists()) {
                        $fail('Nomor surat sudah dipakai surat lain.');
                    }
                }],
        ]);

        // nomorUrut/tahun sengaja tidak disentuh: nomor bebas belum tentu bisa
        // diurai, dan sekuens otomatis max(nomorUrut)+1 harus tetap monoton.
        $surat->update($validated);
        AuditLogService::log('update', 'surat', 'Edit surat: ' . $surat->nomorSurat);
        return back()->with('success', 'Surat ' . $surat->nomorSurat . ' berhasil diperbarui.');
    }

    /**
     * Simpan isi surat hasil editor (atau kembalikan ke template dengan
     * reset=1). HTML disanitasi DI SINI karena halaman cetak merendernya
     * mentah; penulisnya pun sudah dibatasi rute izin:surat.ubahIsi.
     */
    public function updateIsi(Request $request, $id)
    {
        $surat = Surat::findOrFail($id);

        if ($request->boolean('reset')) {
            $surat->update(['isi_kustom' => null]);
            AuditLogService::log('update', 'surat', 'Isi surat '.$surat->nomorSurat.' dikembalikan ke template');

            return redirect()->route('surat.cetak', $surat->id)
                ->with('success', 'Isi surat dikembalikan ke template otomatis.');
        }

        $data = $request->validate(['isi' => 'required|string|max:60000']);

        $surat->update(['isi_kustom' => \App\Services\PembersihHtmlSurat::bersihkan($data['isi'])]);
        AuditLogService::log('update', 'surat', 'Edit isi surat: '.$surat->nomorSurat);

        return redirect()->route('surat.cetak', $surat->id)
            ->with('success', 'Isi surat berhasil disimpan.');
    }

    public function updateStatus(Request $request, $id)
    {
        $surat = Surat::findOrFail($id);
        $newStatus = $surat->status === 'draft' ? 'selesai' : 'draft';
        $surat->update(['status' => $newStatus]);
        AuditLogService::log('update', 'surat', 'Status surat ' . $surat->nomorSurat . ' → ' . $newStatus);
        return back()->with('success', 'Status surat diubah menjadi ' . $newStatus . '.');
    }

    public function cetak($id)
    {
        $surat = Surat::findOrFail($id);
        $this->pastikanBolehLihat($surat);
        $settings = AppSetting::semuaEfektif();

        // Sinkron dengan rute simpan isi (izin:surat.ubahIsi): tombol edit
        // hanya untuk yang memang bisa menyimpan.
        $bolehEdit = bolehkah('surat.ubahIsi');

        return view('layanan.cetak_surat', compact('surat', 'settings', 'bolehEdit'));
    }

    public function destroy($id)
    {
        $surat = Surat::findOrFail($id);
        $nomor = $surat->nomorSurat;
        $surat->delete();
        AuditLogService::log('delete', 'surat', "Hapus surat: $nomor");
        return back()->with('success', "Surat $nomor berhasil dihapus.");
    }
}
