<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengaturanController extends Controller
{
    public function index()
    {
        $settings = AppSetting::semuaEfektif();
        return view('admin.pengaturan', compact('settings'));
    }

    /**
     * Key yang boleh ditulis lewat form Pengaturan.
     *
     * Whitelist, bukan blacklist: tabel app_settings ikut menentukan otorisasi
     * (`role_permissions`) dan ambang kemiskinan, jadi menerima key apa pun dari
     * request berarti siapa pun yang bisa membuka halaman ini bisa menaikkan
     * haknya sendiri. `role_permissions` hanya lewat Manajemen Akun (superadmin),
     * `mpwa_templates` & `notif_*` hanya lewat halaman MPWA.
     */
    private const KEY_DIIZINKAN = [
        'nama_aplikasi', 'tagline_aplikasi', 'lokasi_singkat', 'alamat_portal',
        'nama_rw', 'ketua_rw', 'kelurahan', 'kecamatan', 'kabupaten',
        'nama_operator', 'tahun_aktif',
        'tarif_sampah', 'tarif_padaringan', 'garis_kemiskinan',
        'mpwa_api_key', 'mpwa_sender', 'mpwa_api_url',
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
            'nama_operator'    => 'nullable|string|max:100',
            'tahun_aktif'      => 'nullable|integer|min:2000|max:2100',
            'tarif_sampah'     => 'nullable|integer|min:0',
            'tarif_padaringan' => 'nullable|integer|min:0',
            'garis_kemiskinan' => 'nullable|integer|min:0',
            'mpwa_api_key'     => 'nullable|string|max:255',
            'mpwa_sender'      => 'nullable|string|max:50',
            'mpwa_api_url'     => 'nullable|url|max:255',
        ]);

        foreach (self::KEY_DIIZINKAN as $key) {
            if (!array_key_exists($key, $validated)) continue;
            AppSetting::simpan($key, $validated[$key]);
        }

        \App\Services\AuditLogService::log(
            'update', 'pengaturan',
            'Ubah pengaturan: ' . implode(', ', array_keys(array_intersect_key($validated, array_flip(self::KEY_DIIZINKAN))))
        );

        return redirect()->route('pengaturan.index', ['tab' => $tab])
                         ->with('success', 'Pengaturan berhasil disimpan.');
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

        try {
            DB::transaction(function () use ($model) {
                foreach ($model as $kelas) {
                    $kelas::query()->delete();
                }
            });
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal reset data: ' . $e->getMessage());
        }

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
            $terlihat = [];
            foreach (\App\Models\Keluarga::orderBy('id')->get(['id', 'keluarga_id', 'nama', 'rt']) as $kk) {
                $kunci = $kk->nama . '|' . $kk->rt;
                if (isset($terlihat[$kunci])) {
                    $dupIds[] = $kk->id;
                    $dupKeluargaIds[] = $kk->keluarga_id;
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

            $msg = "Pembersihan duplikat selesai: $countKeluarga KK duplikat dan $countDupAnggota Anggota duplikat dihapus.";
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal membersihkan duplikat: ' . $e->getMessage());
        }
    }
}
