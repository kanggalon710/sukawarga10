<?php

namespace App\Http\Controllers;

use App\Models\Surat;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\AppSetting;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class SuratController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $level = $user->level ?? 'warga';
        $isWarga = $level === 'warga';
        $isRT = $level === 'petugas_rt';
        $isRW = in_array($level, ['ketua_rw']);
        $isAdmin = in_array($level, ['superadmin', 'sekretaris']);

        if ($isWarga) {
            // Warga sees only their own surat
            $surats = Surat::where('user_id', $user->id)->orderByDesc('created_at')->get();
        } elseif ($isRT) {
            // RT sees surat pending their approval + all from their RT
            $surats = Surat::orderByDesc('created_at')->get();
        } else {
            // RW, Admin, Sekretaris see all
            $surats = Surat::orderByDesc('created_at')->get();
        }

        $wargaList = Keluarga::where('status', 'aktif')->orderBy('nama')->get();

        return view('layanan.surat', compact('surats', 'wargaList', 'user', 'level', 'isWarga', 'isRT', 'isRW', 'isAdmin'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $level = $user->level ?? 'warga';
        $isWarga = ($level === 'warga');

        $pemohon = $request->pemohon === '__luar__'
            ? $request->pemohon_manual
            : $request->pemohon;

        $tahun = date('Y');
        $nomorUrut = Surat::where('tahun', $tahun)->max('nomorUrut') + 1;
        $nomorSurat = sprintf('%03d/%s/RW10/%s', $nomorUrut, $request->kodeSurat, $tahun);

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
        $level = $user->level ?? 'warga';
        $step = $surat->approval_step;

        // Determine what this user can approve
        if ($step === 'diajukan' && in_array($level, ['petugas_rt', 'superadmin', 'ketua_rw'])) {
            $surat->update([
                'approval_step' => 'ttd_rt',
                'rt_signed_by'  => $user->namaLengkap ?? $user->username,
                'rt_signed_at'  => now(),
            ]);
            AuditLogService::log('update', 'surat', "TTD RT surat {$surat->nomorSurat} oleh {$user->username}");

            // Notify RW
            if (NotificationService::isEnabled('notif_surat_ttd_rw')) {
                $msg = "✍️ *SURAT PERLU TTD RW*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nPemohon: {$surat->pemohon}\nSudah ditandatangani RT. Giliran RW untuk tanda tangan.\n\nLogin untuk review.";
                NotificationService::notifyByLevel('ketua_rw', $msg);
            }

            return back()->with('success', "Surat {$surat->nomorSurat} berhasil ditandatangani RT.");

        } elseif ($step === 'ttd_rt' && in_array($level, ['ketua_rw', 'superadmin'])) {
            $surat->update([
                'approval_step' => 'ttd_rw',
                'rw_signed_by'  => $user->namaLengkap ?? $user->username,
                'rw_signed_at'  => now(),
            ]);
            AuditLogService::log('update', 'surat', "TTD RW surat {$surat->nomorSurat} oleh {$user->username}");

            // Notify Sekretaris/Admin
            if (NotificationService::isEnabled('notif_surat_cap')) {
                $msg = "🔏 *SURAT PERLU CAP SEKRETARIS*\n\nSurat {$surat->nomorSurat} ({$surat->kodeSurat})\nPemohon: {$surat->pemohon}\nSudah TTD RT & RW. Tinggal cap sekretaris.\n\nLogin untuk finalize.";
                NotificationService::notifyByLevel('superadmin', $msg);
            }

            return back()->with('success', "Surat {$surat->nomorSurat} berhasil ditandatangani RW.");

        } elseif ($step === 'ttd_rw' && in_array($level, ['superadmin'])) {
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
        if (($user->level ?? 'warga') === 'warga' && $surat->user_id !== $user->id) {
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
        $request->validate(['pemohon' => 'required']);

        $surat->update($request->only(['pemohon', 'keperluan', 'kodeSurat']));
        AuditLogService::log('update', 'surat', 'Edit surat: ' . $surat->nomorSurat);
        return back()->with('success', 'Surat ' . $surat->nomorSurat . ' berhasil diperbarui.');
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
        return view('layanan.cetak_surat', compact('surat', 'settings'));
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
