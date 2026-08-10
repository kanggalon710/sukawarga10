<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengaturanController extends Controller
{
    public function index()
    {
        $settings = AppSetting::pluck('value', 'key')->toArray();
        return view('admin.pengaturan', compact('settings'));
    }

    public function update(Request $request)
    {
        $tab = $request->input('_active_tab', 'tarif');
        foreach ($request->except(['_token', '_active_tab']) as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
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

        DB::beginTransaction();
        try {
            // Order matters: delete children first, then parents
            DB::table('iuran_sampahs')->truncate();
            DB::table('iuran_padaringans')->truncate();
            DB::table('setor_sampahs')->truncate();
            DB::table('transaksis')->truncate();
            DB::table('pengeluarans')->truncate();
            DB::table('sumbangans')->truncate();
            DB::table('aduans')->truncate();
            DB::table('surats')->truncate();
            DB::table('kegiatans')->truncate();
            DB::table('umkms')->truncate();
            DB::table('pendaftarans')->truncate();
            DB::table('anggotas')->truncate();
            DB::table('keluargas')->truncate();
            DB::table('audit_logs')->truncate();

            DB::commit();

            // Log the reset action
            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'aksi' => 'reset_data',
                    'deskripsi' => 'Seluruh data operasional direset oleh ' . (auth()->user()->name ?? 'admin'),
                    'ip' => $request->ip(),
                ]);
            }

            return redirect()->route('pengaturan.index', ['tab' => 'data'])
                             ->with('success', 'Semua data operasional berhasil direset.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal reset data: ' . $e->getMessage());
        }
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
