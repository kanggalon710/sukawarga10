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
     * Reset ALL operational data. Preserves users, roles, and app_settings.
     */
    public function resetData(Request $request)
    {
        if ($request->input('confirm') !== 'RESET') {
            return back()->with('error', 'Konfirmasi salah. Ketik "RESET" untuk melanjutkan.');
        }

        // TIDAK dibungkus transaksi: TRUNCATE memicu implicit commit di MySQL,
        // jadi beginTransaction/rollback di sini hanya memberi rasa aman palsu.
        // Operasi ini memang tidak bisa dibatalkan; konfirmasi "RESET" adalah
        // satu-satunya penjaga. Urutan dijaga dari tabel anak ke tabel induk.
        $tabel = [
            'iuran_sampahs', 'iuran_padaringans', 'setor_sampahs',
            'transaksis', 'pengeluarans', 'sumbangans',
            'aduans', 'surats', 'kegiatans', 'umkms',
            'pendaftarans', 'anggotas', 'keluargas', 'audit_logs',
        ];

        try {
            foreach ($tabel as $t) {
                DB::table($t)->truncate();
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal reset data: ' . $e->getMessage());
        }

        // Ditulis SETELAH truncate, supaya jejaknya tidak ikut terhapus.
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
     * Remove duplicate Keluarga entries (keep the earliest created one).
     */
    public function removeDuplicates(Request $request)
    {
        DB::beginTransaction();
        try {
            // Find keluargas with same nama + rt, keep the one with lowest id
            $duplicates = DB::select("
                SELECT k.id FROM keluargas k
                INNER JOIN (
                    SELECT nama, rt, MIN(id) as min_id
                    FROM keluargas
                    GROUP BY nama, rt
                    HAVING COUNT(*) > 1
                ) dup ON k.nama = dup.nama AND k.rt = dup.rt AND k.id != dup.min_id
            ");

            $dupIds = array_map(fn($d) => $d->id, $duplicates);
            $countKeluarga = count($dupIds);

            if ($countKeluarga > 0) {
                // Get keluarga_ids for these duplicate IDs
                $keluargaIds = DB::table('keluargas')->whereIn('id', $dupIds)->pluck('keluarga_id');
                
                // Delete related anggotas first
                $countAnggota = DB::table('anggotas')->whereIn('keluarga_id', $keluargaIds)->delete();
                
                // Delete related iuran
                DB::table('iuran_sampahs')->whereIn('keluarga_id', $keluargaIds)->delete();
                DB::table('iuran_padaringans')->whereIn('keluarga_id', $keluargaIds)->delete();
                
                // Delete the duplicate keluargas
                DB::table('keluargas')->whereIn('id', $dupIds)->delete();
            }

            // Also remove duplicate anggotas (same keluarga_id + nama)
            $dupAnggotas = DB::select("
                SELECT a.id FROM anggotas a
                INNER JOIN (
                    SELECT keluarga_id, nama, MIN(id) as min_id
                    FROM anggotas
                    GROUP BY keluarga_id, nama
                    HAVING COUNT(*) > 1
                ) dup ON a.keluarga_id = dup.keluarga_id AND a.nama = dup.nama AND a.id != dup.min_id
            ");

            $dupAnggotaIds = array_map(fn($d) => $d->id, $dupAnggotas);
            $countDupAnggota = count($dupAnggotaIds);

            if ($countDupAnggota > 0) {
                DB::table('anggotas')->whereIn('id', $dupAnggotaIds)->delete();
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
