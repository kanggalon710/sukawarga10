<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keluarga;
use App\Models\Anggota;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ExportImportController extends Controller
{
    /**
     * Normalize RT format: '01', '1', 'RT01', 'RT 01' → 'RT 01'
     */
    private function normalizeRT(string $raw): string
    {
        $raw = trim($raw);
        // Strip "RT" prefix (case-insensitive) and any spaces
        $num = preg_replace('/^rt\s*/i', '', $raw);
        // Zero-pad to 2 digits
        $num = str_pad(ltrim($num, '0') ?: '0', 2, '0', STR_PAD_LEFT);
        return 'RT ' . $num;
    }

    // ==========================================
    // EXPORT
    // ==========================================

    public function exportKeluarga()
    {
        $fileName = 'Data_Keluarga_RW10_' . date('Ymd_His') . '.csv';
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Ensure these exact headers match the template in the plan
        $columns = [
            'No. Formulir', 'Tgl Pendataan', 'Nama Pendata', 'Nama KK', 'No. KK', 'NIK KK', 
            'Alamat', 'RT', 'RW', 'Kelurahan/Kecamatan', 'No. HP', 'Status Rumah', 
            'Tipe Bangunan', 'Luas Lantai (m²)', 'Jml Kamar Tidur', 'Bahan Lantai', 
            'Bahan Dinding', 'Bahan Atap', 'Sumber Air Minum', 'Sumber Air Mandi/Cuci', 
            'Sumber Masak', 'Kepemilikan Jamban', 'Pembuangan Tinja', 'Cara Buang Sampah', 
            'Penghasilan/Bulan', 'Sumber Pendapatan', 'Punya Tabungan', 'Punya Hutang Usaha', 
            'Akses Kredit', 'BPJS/JKN', 'PKH', 'BPNT/Sembako', 'BLT/Bantuan Tunai', 
            'Rutilahu', 'KIS (JKN PBI)', 'KIP', 'Catatan Khusus', 'Ikut Sampah', 'Ikut Padaringan'
        ];

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            $keluargas = Keluarga::orderBy('rt')->orderBy('nama')->get();

            foreach ($keluargas as $k) {
                // Determine values, using defaults where the system doesn't have exact matches
                $row = [
                    '', // No. Formulir (not natively tracked)
                    '', // Tgl Pendataan 
                    '', // Nama Pendata
                    $k->nama,
                    $k->noKK ?? '',
                    $k->nik ?? '',
                    $k->alamat,
                    $k->rt,
                    $k->rw,
                    $k->kelurahan . '/' . $k->kecamatan,
                    $k->noHP ?? '',
                    $k->statusRumah ?? '',
                    $k->tipeBangunan ?? '',
                    $k->luasLantai ?? '',
                    $k->jmlKamarTidur ?? '',
                    $k->bahanLantai ?? '',
                    $k->bahanDinding ?? '',
                    $k->bahanAtap ?? '',
                    $k->sumberAirMinum ?? '',
                    $k->sumberAirMandi ?? '',
                    $k->sumberMasak ?? '',
                    $k->kepemilikanJamban ?? '',
                    $k->pembuanganTinja ?? '',
                    $k->caraBuangSampah ?? '',
                    $k->penghasilan ?? '',
                    $k->pekerjaan ?? '',
                    $k->punyaTabungan ? 'Ya' : 'Tidak',
                    $k->punyaHutangUsaha ? 'Ya' : 'Tidak',
                    $k->aksesKredit ?? '', // Akses Kredit
                    !empty(($k->bansos ?? [])['bpjs']) ? 'Ya' : 'Tidak', // BPJS/JKN
                    !empty(($k->bansos ?? [])['pkh']) ? 'Ya' : 'Tidak',
                    !empty(($k->bansos ?? [])['bpnt']) ? 'Ya' : 'Tidak',
                    !empty(($k->bansos ?? [])['blt']) ? 'Ya' : 'Tidak',
                    !empty(($k->bansos ?? [])['rutilahu']) ? 'Ya' : 'Tidak',
                    !empty(($k->bansos ?? [])['kis']) ? 'Ya' : 'Tidak',
                    !empty(($k->bansos ?? [])['kip']) ? 'Ya' : 'Tidak',
                    $k->catatan ?? '',
                    $k->ikutSampah ? 'Ya' : 'Tidak',
                    $k->ikutPadaringan ? 'Ya' : 'Tidak'
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAnggota()
    {
        $fileName = 'Data_Anggota_Keluarga_RW10_' . date('Ymd_His') . '.csv';
        
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'No', 'Nama KK (Referensi)', 'RT', 'Nama Anggota', 'L/P', 
            'Tgl Lahir', 'Status Keluarga', 'Pekerjaan', 'BPJS', 'Keterangan'
        ];

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            $anggotas = Anggota::with('keluarga')->get()
                ->sortBy(function($a) {
                    return ($a->keluarga->rt ?? '') . ($a->keluarga->nama ?? '');
                });

            $no = 1;
            foreach ($anggotas as $a) {
                // Use NIK if available, else use Nama as reference
                $refKK = $a->keluarga->nik ?? $a->keluarga->nama ?? '';
                
                $row = [
                    $no++,
                    $refKK,
                    $a->keluarga->rt ?? '',
                    $a->nama,
                    substr($a->jenisKelamin ?? 'L', 0, 1),
                    $a->tanggalLahir ? $a->tanggalLahir->format('Y-m-d') : '',
                    $a->statusKeluarga,
                    $a->pekerjaan ?? '',
                    $a->statusBPJS ?? '',
                    '' // Keterangan
                ];

                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // DOWNLOAD TEMPLATE
    // ==========================================

    public function downloadTemplate($type)
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        if ($type === 'keluarga') {
            $fileName = 'Template_Import_Keluarga.csv';
            $columns = [
                'No. Formulir', 'Tgl Pendataan', 'Nama Pendata', 'Nama KK', 'No. KK', 'NIK KK', 
                'Alamat', 'RT', 'RW', 'Kelurahan/Kecamatan', 'No. HP', 'Status Rumah', 
                'Tipe Bangunan', 'Luas Lantai (m²)', 'Jml Kamar Tidur', 'Bahan Lantai', 
                'Bahan Dinding', 'Bahan Atap', 'Sumber Air Minum', 'Sumber Air Mandi/Cuci', 
                'Sumber Masak', 'Kepemilikan Jamban', 'Pembuangan Tinja', 'Cara Buang Sampah', 
                'Penghasilan/Bulan', 'Sumber Pendapatan', 'Punya Tabungan', 'Punya Hutang Usaha', 
                'Akses Kredit', 'BPJS/JKN', 'PKH', 'BPNT/Sembako', 'BLT/Bantuan Tunai', 
                'Rutilahu', 'KIS (JKN PBI)', 'KIP', 'Catatan Khusus', 'Ikut Sampah', 'Ikut Padaringan'
            ];
            $sampleRow = [
                'F-001', '2026-02-22', 'Petugas A', 'Joko Sudiro', '3205012345678901', '3205019876543210', 
                'Jl. Contoh No 1', '01', '10', 'Sukakarya/Tarogong Kidul', '08123456789', 'Milik Sendiri', 
                'Permanen', '60', '3', 'Keramik', 'Tembok', 'Genting', 'PDAM', 'Sumur', 
                'Gas/LPG', 'Sendiri', 'Septic Tank', 'Diangkut Petugas', 'Rp 3.000.000', 'Wiraswasta', 'Ya', 'Tidak', 
                'Tidak', 'Ya', 'Tidak', 'Tidak', 'Tidak', 
                'Tidak', 'Tidak', 'Tidak', '', 'Ya', 'Ya'
            ];
        } else {
            $fileName = 'Template_Import_Anggota.csv';
            $columns = [
                'No', 'Nama KK (Referensi)', 'RT', 'Nama Anggota', 'L/P', 
                'Tgl Lahir', 'Status Keluarga', 'Pekerjaan', 'BPJS', 'Keterangan'
            ];
            $sampleRow = [
                '1', '3205019876543210', '01', 'Siti Aminah', 'P', 
                '1985-05-15', 'Istri', 'Ibu Rumah Tangga', 'Ya', 'Keterangan contoh'
            ];
        }

        $headers["Content-Disposition"] = "attachment; filename=$fileName";

        $callback = function() use($columns, $sampleRow) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);
            fputcsv($file, $sampleRow);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // IMPORT
    // ==========================================

    public function importKeluarga(Request $request)
    {
        $request->validate([
            'file_keluarga' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file_keluarga');
        $handle = fopen($file->getRealPath(), "r");
        
        // Skip BOM if exists
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read headers
        $header = fgetcsv($handle, 1000, ",");
        if (!$header) {
            return back()->with('error', 'Format CSV tidak valid atau kosong.');
        }

        // Create map of column name to index
        $map = array_flip(array_map('trim', $header));

        // Required columns (at minimum)
        if (!isset($map['Nama KK']) || !isset($map['RT'])) {
            return back()->with('error', 'CSV harus memiliki kolom "Nama KK" dan "RT". Harap gunakan Template.');
        }

        DB::beginTransaction();
        try {
            $count = 0;
            $updated = 0;

            while (($row = fgetcsv($handle, 4000, ",")) !== FALSE) {
                // Ensure row has same columns as header
                if (count($row) < 2) continue; // Skip empty rows

                $namaKK = trim($row[$map['Nama KK']] ?? '');
                if (empty($namaKK)) continue;

                $nik = isset($map['NIK KK']) ? trim($row[$map['NIK KK']]) : '';
                $rtRaw = isset($map['RT']) ? trim($row[$map['RT']]) : '';
                $rt = !empty($rtRaw) ? $this->normalizeRT($rtRaw) : '';
                $noKK = isset($map['No. KK']) ? trim($row[$map['No. KK']]) : '';

                // Try to find existing KK (using normalized RT)
                $keluarga = null;
                if (!empty($nik)) {
                    $keluarga = Keluarga::where('nik', $nik)->first();
                }
                if (!$keluarga && !empty($noKK)) {
                    $keluarga = Keluarga::where('noKK', $noKK)->first();
                }
                if (!$keluarga && !empty($namaKK)) {
                    $keluarga = Keluarga::where('nama', $namaKK)->where('rt', $rt)->first();
                }

                $isNew = false;
                if (!$keluarga) {
                    $keluarga = new Keluarga();
                    $keluarga->keluarga_id = 'K-' . Str::uuid()->toString();
                    $isNew = true;
                    $count++;
                } else {
                    $updated++;
                }

                $keluarga->nama = $namaKK;
                if (!empty($nik)) $keluarga->nik = $nik;
                if (!empty($noKK)) $keluarga->noKK = $noKK;
                $keluarga->rt = $rt;
                
                if (isset($map['RW'])) $keluarga->rw = trim($row[$map['RW']] ?: '10');
                if (isset($map['Kelurahan/Kecamatan'])) {
                    $lokasi = explode('/', trim($row[$map['Kelurahan/Kecamatan']]));
                    $keluarga->kelurahan = trim($lokasi[0] ?? 'Sukakarya');
                    if (count($lokasi) > 1) {
                        $keluarga->kecamatan = trim($lokasi[1] ?? 'Tarogong Kidul');
                    }
                }
                
                if (isset($map['Alamat'])) $keluarga->alamat = trim($row[$map['Alamat']] ?: 'RW 10 Sukakarya');
                if (isset($map['No. HP'])) $keluarga->noHP = trim($row[$map['No. HP']]);
                if (isset($map['Pekerjaan'])) {
                    $keluarga->pekerjaan = trim($row[$map['Pekerjaan']]);
                } elseif (isset($map['Sumber Pendapatan'])) {
                    $keluarga->pekerjaan = trim($row[$map['Sumber Pendapatan']]);
                }
                if (isset($map['Sumber Pendapatan'])) $keluarga->sumberPendapatan = trim($row[$map['Sumber Pendapatan']]);
                if (isset($map['Penghasilan/Bulan'])) $keluarga->penghasilan = trim($row[$map['Penghasilan/Bulan']]);
                if (isset($map['Status Rumah'])) $keluarga->statusRumah = trim($row[$map['Status Rumah']]);
                if (isset($map['Akses Kredit'])) $keluarga->aksesKredit = trim($row[$map['Akses Kredit']]);
                if (isset($map['Catatan Khusus'])) $keluarga->catatan = trim($row[$map['Catatan Khusus']]);

                // Booleans / Flags
                $parseBool = fn($val) => in_array(strtolower(trim($val)), ['ya', 'benar', 'true', '1']);

                if (isset($map['Punya Tabungan'])) $keluarga->punyaTabungan = $parseBool($row[$map['Punya Tabungan']]);
                if (isset($map['Punya Hutang Usaha'])) $keluarga->punyaHutangUsaha = $parseBool($row[$map['Punya Hutang Usaha']]);
                if (isset($map['Ikut Sampah'])) $keluarga->ikutSampah = $parseBool($row[$map['Ikut Sampah']]);
                if (isset($map['Ikut Padaringan'])) $keluarga->ikutPadaringan = $parseBool($row[$map['Ikut Padaringan']]);

                // Kondisi rumah & sanitasi → KOLOM DB (sumber kanonik, sama dengan form web)
                $colMap = [
                    'Tipe Bangunan' => 'tipeBangunan', 'Luas Lantai (m²)' => 'luasLantai',
                    'Bahan Lantai' => 'bahanLantai', 'Bahan Dinding' => 'bahanDinding', 'Bahan Atap' => 'bahanAtap',
                    'Sumber Air Minum' => 'sumberAirMinum', 'Sumber Air Mandi/Cuci' => 'sumberAirMandi',
                    'Sumber Masak' => 'sumberMasak', 'Kepemilikan Jamban' => 'kepemilikanJamban',
                    'Pembuangan Tinja' => 'pembuanganTinja', 'Cara Buang Sampah' => 'caraBuangSampah',
                ];
                foreach ($colMap as $csvCol => $dbCol) {
                    if (isset($map[$csvCol]) && trim($row[$map[$csvCol]] ?? '') !== '') {
                        $keluarga->{$dbCol} = trim($row[$map[$csvCol]]);
                    }
                }
                if (isset($map['Jml Kamar Tidur']) && is_numeric(trim($row[$map['Jml Kamar Tidur']] ?? ''))) {
                    $keluarga->jmlKamarTidur = (int) trim($row[$map['Jml Kamar Tidur']]);
                }

                // Bansos → map boolean key kecil (kanonik, sama dgn form): {bpjs,pkh,bpnt,blt,rutilahu,kis,kip}
                $bansos = $keluarga->bansos ?? [];
                $bMap = [
                    'BPJS/JKN' => 'bpjs', 'PKH' => 'pkh', 'BPNT/Sembako' => 'bpnt', 'BLT/Bantuan Tunai' => 'blt',
                    'Rutilahu' => 'rutilahu', 'KIS (JKN PBI)' => 'kis', 'KIP' => 'kip',
                ];
                foreach ($bMap as $csvCol => $key) {
                    if (isset($map[$csvCol])) {
                        $v = strtolower(trim($row[$map[$csvCol]] ?? ''));
                        // BPJS/JKN bernilai "Semua Punya"/"Sebagian"/"Tidak ada"; bansos lain bernilai Ya/Tidak
                        $bansos[$key] = in_array($v, ['ya', 'benar', 'true', '1', 'semua punya', 'sebagian']);
                    }
                }
                $keluarga->bansos = $bansos;

                $keluarga->save();
            }
            fclose($handle);
            DB::commit();

            return back()->with('success', "Import Data Keluarga selesai. $count Data Baru, $updated Data Diperbarui.");
        } catch (\Exception $e) {
            DB::rollback();
            if (isset($handle) && is_resource($handle)) fclose($handle);
            return back()->with('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
        }
    }

    public function importAnggota(Request $request)
    {
        $request->validate([
            'file_anggota' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file_anggota');
        $handle = fopen($file->getRealPath(), "r");
        
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 1000, ",");
        if (!$header) {
            return back()->with('error', 'Format CSV tidak valid atau kosong.');
        }

        $map = array_flip(array_map('trim', $header));

        if (!isset($map['Nama KK (Referensi)']) || !isset($map['Nama Anggota'])) {
            return back()->with('error', 'CSV harus memiliki kolom "Nama KK (Referensi)" dan "Nama Anggota".');
        }

        DB::beginTransaction();
        try {
            $count = 0;
            $updated = 0;
            $unmatched = 0;

            while (($row = fgetcsv($handle, 4000, ",")) !== FALSE) {
                if (count($row) < 2) continue;

                $refKK = trim($row[$map['Nama KK (Referensi)']] ?? '');
                $namaAnggota = trim($row[$map['Nama Anggota']] ?? '');
                
                if (empty($refKK) || empty($namaAnggota)) continue;

                // Find KK ID by NIK, NoKK, or exact Nama Match
                $keluarga = Keluarga::where('nik', $refKK)
                            ->orWhere('noKK', $refKK)
                            ->orWhere('nama', $refKK)
                            ->first();

                if (!$keluarga) {
                    $unmatched++;
                    continue; // Skip if family not found
                }

                // Check if member already exists in this family
                $anggota = Anggota::where('keluarga_id', $keluarga->keluarga_id)
                                  ->where('nama', $namaAnggota)
                                  ->first();

                if (!$anggota) {
                    $anggota = new Anggota();
                    $anggota->anggota_id = 'A-' . Str::uuid()->toString();
                    $anggota->keluarga_id = $keluarga->keluarga_id;
                    $anggota->nama = $namaAnggota;
                    $count++;
                } else {
                    $updated++;
                }

                if (isset($map['L/P'])) {
                    $lp = strtoupper(trim($row[$map['L/P']]));
                    $anggota->jenisKelamin = ($lp === 'L' || $lp === 'LAKI-LAKI') ? 'Laki-laki' : 'Perempuan';
                }

                if (isset($map['Tgl Lahir'])) {
                    $tgl = trim($row[$map['Tgl Lahir']]);
                    if (!empty($tgl)) {
                        // Attempt to parse simple YYYY-MM-DD
                        $anggota->tanggalLahir = \Carbon\Carbon::parse($tgl);
                    }
                }

                if (isset($map['Status Keluarga'])) $anggota->statusKeluarga = trim($row[$map['Status Keluarga']]);
                if (isset($map['Pekerjaan'])) $anggota->pekerjaan = trim($row[$map['Pekerjaan']]);
                
                // Assuming statusBPJS handles 'Ya', text, etc.
                if (isset($map['BPJS'])) {
                    $bpjsRaw = trim($row[$map['BPJS']]);
                    if (!empty($bpjsRaw)) {
                         $anggota->statusBPJS = $bpjsRaw; 
                    }
                }

                $anggota->save();
            }
            fclose($handle);
            DB::commit();

            $msg = "Import Data Anggota selesai. $count Ditambahkan, $updated Diperbarui.";
            if ($unmatched > 0) {
                $msg .= " Peringatan: $unmatched Anggota dilewati karena Referensi Kepala Keluarga tidak ditemukan di sistem.";
                return back()->with('warning', $msg);
            }

            return back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollback();
            if (isset($handle) && is_resource($handle)) fclose($handle);
            return back()->with('error', 'Terjadi kesalahan saat import: ' . $e->getMessage());
        }
    }
}
