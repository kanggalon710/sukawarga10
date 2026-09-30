<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Services\PenyimpanBerkas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengaturanController extends Controller
{
    public function index()
    {
        $settings = AppSetting::semuaEfektif();
        // Status kunci MPWA saja, bukan nilainya: rahasia tidak pernah
        // dirender ke HTML (lihat AppSetting::KEY_RAHASIA).
        $statusKunciMpwa = AppSetting::statusRahasia('mpwa_api_key');
        $hostGateway = \App\Services\MpwaService::hostGateway();

        return view('admin.pengaturan', compact('settings', 'statusKunciMpwa', 'hostGateway'));
    }

    /**
     * Key yang boleh ditulis lewat form Pengaturan.
     *
     * Whitelist, bukan blacklist: tabel app_settings ikut menentukan otorisasi
     * (`role_permissions`) dan ambang kemiskinan, jadi menerima key apa pun dari
     * request berarti siapa pun yang bisa membuka halaman ini bisa menaikkan
     * haknya sendiri. `role_permissions` hanya lewat Manajemen Akun (superadmin),
     * `mpwa_templates` & `notif_*` hanya lewat halaman MPWA.
     *
     * `mpwa_api_key` sengaja TIDAK di sini: rahasia, ditulis terpisah lewat
     * simpanRahasia(). `mpwa_api_url` sudah tidak bisa diatur tenant sama
     * sekali (host gateway dari config + allow-list).
     */
    private const KEY_DIIZINKAN = [
        'nama_aplikasi', 'tagline_aplikasi', 'lokasi_singkat', 'alamat_portal',
        'nama_rw', 'ketua_rw', 'kelurahan', 'kecamatan', 'kabupaten', 'alamat_rw',
        'nama_operator', 'tahun_aktif',
        'tarif_sampah', 'tarif_padaringan', 'garis_kemiskinan',
        'mpwa_sender',
    ];

    public function update(Request $request)
    {
        $tab = $request->input('_active_tab', 'tarif');

        $validated = $request->validate([
            'nama_aplikasi'    => 'nullable|string|max:60',
            'tagline_aplikasi' => 'nullable|string|max:200',
            'lokasi_singkat'   => 'nullable|string|max:100',
            // Nama host saja, tanpa skema dan tanpa path: nilainya ditempel apa
            // adanya ke pesan WhatsApp ("Akses portal di: <nilai>").
            'alamat_portal'    => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'nama_rw'          => 'nullable|string|max:100',
            'ketua_rw'         => 'nullable|string|max:100',
            'kelurahan'        => 'nullable|string|max:100',
            'kecamatan'        => 'nullable|string|max:100',
            'kabupaten'        => 'nullable|string|max:100',
            'alamat_rw'        => 'nullable|string|max:200',
            'nama_operator'    => 'nullable|string|max:100',
            'tahun_aktif'      => 'nullable|integer|min:2000|max:2100',
            'tarif_sampah'     => 'nullable|integer|min:0',
            'tarif_padaringan' => 'nullable|integer|min:0',
            'garis_kemiskinan' => 'nullable|integer|min:0',
            // Kosong = pertahankan kunci yang ada; menghapus lewat checkbox.
            'mpwa_api_key'     => ['nullable', 'string', 'max:255', 'regex:/^\S+$/'],
            'mpwa_api_key_hapus' => 'nullable|boolean',
            'mpwa_sender'      => 'nullable|string|max:50',
            // Logo kop: tanpa SVG (bisa memuat skrip = stored XSS, dilayani
            // same-origin dari /storage), maksimal 1 MB.
            // Isi berkas diperiksa ulang oleh PenyimpanBerkas (sniff + kode ulang);
            // aturan di sini hanya saringan murah di depan.
            'kop_logo_file'    => 'nullable|file|max:1024',
            'kop_logo_aksi'    => 'nullable|in:hapus,reset',
        ]);

        // Logo kop di luar loop whitelist: nilai kop_logo HANYA hasil store()
        // atau konstanta - klien tidak pernah mengirim path (anti path injection).
        // Tiga status: '' = logo bawaan, 'kop/...' = upload, 'tanpa-logo' = tanpa logo.
        $aksiLogo = null;
        if ($request->hasFile('kop_logo_file')) {
            // Disimpan (dan diperiksa) DULU: logo lama hanya dihapus bila logo
            // baru benar-benar sah, bukan sebelum validasinya.
            $pathBaru = app(PenyimpanBerkas::class)->simpan($request->file('kop_logo_file'), 'logo_kop', 'kop_logo_file');
            $this->hapusFileLogoMilikTenant();
            AppSetting::simpan('kop_logo', $pathBaru);
            $aksiLogo = 'kop_logo (upload)';
        } elseif (($validated['kop_logo_aksi'] ?? null) === 'hapus') {
            $this->hapusFileLogoMilikTenant();
            AppSetting::simpan('kop_logo', 'tanpa-logo');
            $aksiLogo = 'kop_logo (hapus)';
        } elseif (($validated['kop_logo_aksi'] ?? null) === 'reset') {
            $this->hapusFileLogoMilikTenant();
            AppSetting::simpan('kop_logo', '');
            $aksiLogo = 'kop_logo (reset)';
        }

        foreach (self::KEY_DIIZINKAN as $key) {
            if (!array_key_exists($key, $validated)) continue;
            AppSetting::simpan($key, $validated[$key]);
        }

        $aksiKunci = null;
        if (! empty($validated['mpwa_api_key'])) {
            AppSetting::simpanRahasia('mpwa_api_key', $validated['mpwa_api_key']);
            $aksiKunci = 'mpwa_api_key (diganti)';
        } elseif ($request->boolean('mpwa_api_key_hapus')) {
            AppSetting::simpanRahasia('mpwa_api_key', null);
            $aksiKunci = 'mpwa_api_key (dihapus)';
        }

        \App\Services\AuditLogService::log(
            'update', 'pengaturan',
            'Ubah pengaturan: ' . implode(', ', array_filter(array_merge(
                array_keys(array_intersect_key($validated, array_flip(self::KEY_DIIZINKAN))),
                [$aksiLogo, $aksiKunci]
            )))
        );

        return redirect()->route('pengaturan.index', ['tab' => $tab])
                         ->with('success', 'Pengaturan berhasil disimpan.');
    }

    /**
     * Hapus file logo kop MILIK organisasi host request (sebelum diganti).
     *
     * Sengaja query langsung by-key, pengecualian sadar atas aturan model
     * AppSetting: yang dibutuhkan baris milik org host, BUKAN nilai efektif -
     * nilai efektif bisa warisan desa/platform dan file-nya masih dipakai
     * tenant saudara, jadi tidak boleh ikut terhapus.
     */
    private function hapusFileLogoMilikTenant(): void
    {
        // Resolusi org host sama persis dengan AppSetting::simpan().
        $context = app(\App\Services\TenantContext::class);
        $orgId = $context->sudahDitetapkan() ? $context->organisasi()?->id : null;

        $lama = AppSetting::where('key', 'kop_logo')
            ->where('organization_id', $orgId)->value('value');

        // hapus() hanya bertindak di dalam folder kop/ (cek realpath).
        app(PenyimpanBerkas::class)->hapus($lama, 'logo_kop');
    }

    /**
     * Reset data operasional TENANT INI. Users, roles, dan app_settings utuh.
     */
    public function resetData(Request $request)
    {
        if ($request->input('confirm') !== 'RESET') {
            return back()->with('error', 'Konfirmasi salah. Ketik "RESET" untuk melanjutkan.');
        }

        // Lewat Eloquent, BUKAN TRUNCATE: global scope organisasi membatasi
        // penghapusan ke tenant request, sedangkan TRUNCATE mengosongkan tabel
        // lintas tenant. Bonusnya kini bisa dibungkus transaksi (TRUNCATE
        // memicu implicit commit di MySQL, DELETE tidak).
        // Urutan dari anak ke induk; model ber-scope turunan (iuran, anggota)
        // wajib dihapus SEBELUM keluargas karena scope-nya subquery keluargas.
        $model = [
            \App\Models\IuranSampah::class, \App\Models\IuranPadaringan::class,
            \App\Models\SetorSampah::class, \App\Models\Transaksi::class,
            \App\Models\Pengeluaran::class, \App\Models\Sumbangan::class,
            \App\Models\Aduan::class, \App\Models\Surat::class,
            \App\Models\Kegiatan::class, \App\Models\Umkm::class,
            \App\Models\Pendaftaran::class, \App\Models\Anggota::class,
            \App\Models\Keluarga::class, \App\Models\AuditLog::class,
        ];

        // Path berkas dokumen dicatat sebelum barisnya hilang, lalu dihapus
        // setelah commit (tersaring scope: hanya milik tenant ini).
        $berkasKk = \App\Models\Keluarga::get(array_keys(PenyimpanBerkas::KOLOM_KK))->toArray();

        try {
            DB::transaction(function () use ($model) {
                foreach ($model as $kelas) {
                    $kelas::query()->delete();
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal reset data: ' . $e->getMessage());
        }

        app(PenyimpanBerkas::class)->hapusBerkasKeluarga($berkasKk);

        // Ditulis SETELAH penghapusan, supaya jejaknya tidak ikut terhapus.
        // Lewat AuditLogService, bukan AuditLog::create langsung: skema audit_logs
        // tidak punya kolom user_id/ip, dan penulisan langsung sebelumnya selalu
        // melempar exception sehingga reset yang berhasil dilaporkan sebagai gagal.
        $user = auth()->user();
        \App\Services\AuditLogService::log(
            'reset_data', 'pengaturan',
            'Seluruh data operasional direset oleh ' . ($user->namaLengkap ?? $user->username ?? 'admin')
            . ' (IP: ' . $request->ip() . ')'
        );

        return redirect()->route('pengaturan.index', ['tab' => 'data'])
                         ->with('success', 'Semua data operasional berhasil direset.');
    }

    /**
     * Hapus KK & anggota duplikat TENANT INI (yang paling awal dipertahankan).
     */
    public function removeDuplicates(Request $request)
    {
        DB::beginTransaction();
        try {
            // Deteksi lewat Eloquent supaya tersaring scope organisasi: duplikat
            // tenant lain bukan urusan tenant ini. Deretan KK satu tenant cukup
            // kecil untuk dibandingkan di memori (kolom seperlunya saja).
            $dupIds = [];
            $dupKeluargaIds = [];
            $berkasDup = [];
            $terlihat = [];
            // KK berstatus 'pindah' adalah ARSIP yang sengaja dipertahankan supaya
            // riwayat iuran & transaksinya (yang menunjuk id numerik baris ini)
            // tidak jadi yatim. Tanpa pengecualian ini, keluarga yang pindah lalu
            // kembali akan dianggap duplikat dari arsipnya sendiri, dan yang
            // dihapus justru baris yang aktif.
            $daftarKk = \App\Models\Keluarga::where('status', '!=', 'pindah')
                ->orderBy('id')->get(array_merge(['id', 'keluarga_id', 'nama', 'rt'], array_keys(PenyimpanBerkas::KOLOM_KK)));
            foreach ($daftarKk as $kk) {
                $kunci = $kk->nama . '|' . $kk->rt;
                if (isset($terlihat[$kunci])) {
                    $dupIds[] = $kk->id;
                    $dupKeluargaIds[] = $kk->keluarga_id;
                    $berkasDup[] = $kk;
                } else {
                    $terlihat[$kunci] = true;
                }
            }
            $countKeluarga = count($dupIds);

            if ($countKeluarga > 0) {
                \App\Models\Anggota::whereIn('keluarga_id', $dupKeluargaIds)->delete();

                // iuran_*.keluarga_id menyimpan id numerik keluargas.id (alur
                // bayar), tapi data lama bisa berisi ID bisnis string - hapus
                // lewat kedua bentuk rujukan.
                \App\Models\IuranSampah::whereIn('keluarga_id', $dupIds)->delete();
                \App\Models\IuranSampah::whereIn('keluarga_id', $dupKeluargaIds)->delete();
                \App\Models\IuranPadaringan::whereIn('keluarga_id', $dupIds)->delete();
                \App\Models\IuranPadaringan::whereIn('keluarga_id', $dupKeluargaIds)->delete();

                \App\Models\Keluarga::whereIn('id', $dupIds)->delete();
            }

            // Anggota duplikat (keluarga_id + nama sama) di tenant ini.
            $dupAnggotaIds = [];
            $terlihatAnggota = [];
            foreach (\App\Models\Anggota::orderBy('id')->get(['id', 'keluarga_id', 'nama']) as $a) {
                $kunci = $a->keluarga_id . '|' . $a->nama;
                if (isset($terlihatAnggota[$kunci])) {
                    $dupAnggotaIds[] = $a->id;
                } else {
                    $terlihatAnggota[$kunci] = true;
                }
            }
            $countDupAnggota = count($dupAnggotaIds);

            if ($countDupAnggota > 0) {
                \App\Models\Anggota::whereIn('id', $dupAnggotaIds)->delete();
            }

            DB::commit();
            app(PenyimpanBerkas::class)->hapusBerkasKeluarga($berkasDup);

            $msg = "Pembersihan duplikat selesai: $countKeluarga KK duplikat dan $countDupAnggota Anggota duplikat dihapus.";
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membersihkan duplikat: ' . $e->getMessage());
        }
    }
}
