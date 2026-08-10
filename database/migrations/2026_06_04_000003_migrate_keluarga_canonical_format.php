<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Keluarga;

/**
 * Migrasi DATA (bukan skema): menyelaraskan baris lama ke format kanonik.
 * - Sanitasi/rumah yang sebelumnya tersimpan di JSON `aset` → dipindah ke KOLOM DB.
 * - `bansos` yang berupa array string kapital (PKH, KIS PBI, ...) → diubah ke map boolean {pkh,kis,...}.
 * Idempotent: aman dijalankan ulang (kunci sanitasi sudah dihapus dari aset; bansos map dilewati).
 */
return new class extends Migration
{
    public function up(): void
    {
        $asetSanitasiKeys = [
            'tipeBangunan' => 'tipeBangunan', 'luasLantai' => 'luasLantai', 'kamarTidur' => 'jmlKamarTidur',
            'bahanLantai' => 'bahanLantai', 'bahanDinding' => 'bahanDinding', 'bahanAtap' => 'bahanAtap',
            'sumberAirMinum' => 'sumberAirMinum', 'sumberAirMandi' => 'sumberAirMandi', 'sumberMasak' => 'sumberMasak',
            'jamban' => 'kepemilikanJamban', 'tinja' => 'pembuanganTinja', 'sampah' => 'caraBuangSampah',
        ];
        $bansosLabelToKey = [
            'PKH' => 'pkh', 'BPNT' => 'bpnt', 'BLT' => 'blt', 'Rutilahu' => 'rutilahu',
            'KIS PBI' => 'kis', 'KIS' => 'kis', 'KIP' => 'kip', 'BPJS' => 'bpjs',
        ];

        Keluarga::query()->chunkById(200, function ($rows) use ($asetSanitasiKeys, $bansosLabelToKey) {
            foreach ($rows as $k) {
                $changed = false;

                // 1) Pindahkan sanitasi dari aset JSON → kolom (hanya jika kolom masih kosong)
                $aset = $k->aset ?? [];
                if (is_array($aset)) {
                    foreach ($asetSanitasiKeys as $asetKey => $col) {
                        if (array_key_exists($asetKey, $aset) && $aset[$asetKey] !== '' && $aset[$asetKey] !== null) {
                            if (empty($k->{$col})) {
                                if ($col === 'jmlKamarTidur') {
                                    if (is_numeric($aset[$asetKey])) $k->{$col} = (int) $aset[$asetKey];
                                } else {
                                    $k->{$col} = $aset[$asetKey];
                                }
                            }
                            unset($aset[$asetKey]); // sisakan hanya motor/mobil/tanah/sawah/ternak/komputer
                            $changed = true;
                        }
                    }
                    if ($changed) $k->aset = $aset;
                }

                // 2) bansos array string kapital → map boolean
                $bansos = $k->bansos ?? [];
                if (is_array($bansos) && array_is_list($bansos) && count($bansos) > 0) {
                    $mapB = [];
                    foreach ($bansos as $label) {
                        $key = $bansosLabelToKey[trim((string) $label)] ?? null;
                        if ($key) $mapB[$key] = true;
                    }
                    $k->bansos = $mapB;
                    $changed = true;
                }

                if ($changed) $k->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Migrasi data — tidak dibalik otomatis.
    }
};
