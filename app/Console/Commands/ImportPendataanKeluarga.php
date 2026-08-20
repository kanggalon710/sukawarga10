<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Keluarga;
use App\Models\Anggota;
use App\Models\Transaksi;
use App\Models\IuranSampah;
use App\Models\IuranPadaringan;

class ImportPendataanKeluarga extends Command
{
    protected $signature = 'import:keluarga {file : Path CSV pendataan} {--fresh : Hapus data keluarga/anggota/transaksi/iuran lama dulu}';
    protected $description = 'Import Data Keluarga RW 10 dari CSV pendataan (lewati baris judul, tulis ke kolom kanonik)';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("File tidak ditemukan: $file");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Menghapus data lama (keluarga, anggota, transaksi, iuran)...');
            Anggota::query()->delete();
            Keluarga::query()->delete();
            Transaksi::query()->delete();
            IuranSampah::query()->delete();
            IuranPadaringan::query()->delete();
        }

        $handle = fopen($file, 'r');
        // Lewati BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $map = null;
        $created = 0; $updated = 0; $skipped = 0; $identitasRusak = 0; $nonRw10 = 0; $tanpaKK = 0;

        $bool = fn($v) => in_array(strtolower(trim((string)$v)), ['ya', 'benar', 'true', '1']);

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            // Cari baris header (yang memuat "Nama KK"), lewati semua baris judul di atasnya
            if ($map === null) {
                $trimmed = array_map(fn($c) => trim((string)$c), $row);
                if (in_array('Nama KK', $trimmed)) {
                    $map = array_flip($trimmed);
                }
                continue;
            }

            $get = fn($key) => (isset($map[$key]) && isset($row[$map[$key]])) ? trim((string)$row[$map[$key]]) : '';

            $nama = $get('Nama KK');
            if ($nama === '') { $skipped++; continue; } // baris template kosong

            $rw = $get('RW') ?: '10';
            $lokasi = array_map('trim', explode('/', $get('Kelurahan/Kecamatan')));
            // Identitas dinormalisasi di batas masuk, sama seperti importir web.
            // Nomor rusak (notasi ilmiah, panjang salah) BUKAN cuma jelek disimpan:
            // ia dipakai sebagai kunci upsert di bawah, jadi satu keluarga bisa
            // terpecah jadi dua baris tiap kali importir dijalankan ulang.
            $noKKMentah = $get('No. KK');
            $nikMentah  = $get('NIK KK');
            if (identitasRusak($noKKMentah) || identitasRusak($nikMentah)) {
                $this->warn("  Dilewati (No.KK/NIK tidak sah): {$nama}");
                $identitasRusak++;
                $skipped++;
                continue;
            }
            $noKK = normalisasiIdentitas($noKKMentah) ?? '';
            $nik  = normalisasiIdentitas($nikMentah) ?? '';
            $bpjs = strtolower($get('BPJS/JKN'));

            $data = [
                'nama' => $nama,
                'noKK' => $noKK ?: null,
                'nik' => $nik ?: null,
                'alamat' => $get('Alamat') ?: ('RW ' . $rw),
                'rt' => $get('RT'),
                'rw' => $rw,
                'kelurahan' => $lokasi[0] ?? 'Sukakarya',
                'kecamatan' => $lokasi[1] ?? 'Tarogong Kidul',
                'noHP' => normalizeWa($get('No. HP')),
                'statusRumah' => $get('Status Rumah') ?: null,
                'tipeBangunan' => $get('Tipe Bangunan') ?: null,
                'luasLantai' => $get('Luas Lantai (m²)') ?: null,
                'jmlKamarTidur' => is_numeric($get('Jml Kamar Tidur')) ? (int) $get('Jml Kamar Tidur') : null,
                'bahanLantai' => $get('Bahan Lantai') ?: null,
                'bahanDinding' => $get('Bahan Dinding') ?: null,
                'bahanAtap' => $get('Bahan Atap') ?: null,
                'sumberAirMinum' => $get('Sumber Air Minum') ?: null,
                'sumberAirMandi' => $get('Sumber Air Mandi/Cuci') ?: null,
                'sumberMasak' => $get('Sumber Masak') ?: null,
                'kepemilikanJamban' => $get('Kepemilikan Jamban') ?: null,
                'pembuanganTinja' => $get('Pembuangan Tinja') ?: null,
                'caraBuangSampah' => $get('Cara Buang Sampah') ?: null,
                'penghasilan' => $get('Penghasilan/Bulan') ?: null,
                'sumberPendapatan' => $get('Sumber Pendapatan') ?: null,
                'pekerjaan' => $get('Sumber Pendapatan') ?: null,
                'punyaTabungan' => $bool($get('Punya Tabungan')),
                'punyaHutangUsaha' => $bool($get('Punya Hutang Usaha')),
                'aksesKredit' => $get('Akses Kredit') ?: null,
                'bansos' => [
                    'bpjs' => in_array($bpjs, ['semua punya', 'sebagian', 'ya']),
                    'pkh' => $bool($get('PKH')),
                    'bpnt' => $bool($get('BPNT/Sembako')),
                    'blt' => $bool($get('BLT/Bantuan Tunai')),
                    'rutilahu' => $bool($get('Rutilahu')),
                    'kis' => $bool($get('KIS (JKN PBI)')),
                    'kip' => $bool($get('KIP')),
                ],
                'catatan' => $get('Catatan Khusus') ?: null,
                'status' => 'aktif',
                'jumlahAnggota' => 1,
                'ikutSampah' => true,
                'ikutPadaringan' => true,
            ];

            // Upsert berdasar NIK lalu No.KK (idempotent, hindari ganda saat dijalankan ulang)
            $existing = null;
            if ($nik) $existing = Keluarga::where('nik', $nik)->first();
            if (!$existing && $noKK) $existing = Keluarga::where('noKK', $noKK)->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                $data['keluarga_id'] = 'kk_' . Str::uuid()->toString();
                Keluarga::create($data);
                $created++;
            }

            if ($rw !== '10') $nonRw10++;
            if (!$noKK) $tanpaKK++;
        }
        fclose($handle);

        $this->newLine();
        $this->info("✅ Import selesai.");
        $this->table(
            ['Dibuat', 'Diperbarui', 'Dilewati', 'Identitas tidak sah', 'Bukan RW 10', 'Tanpa No.KK', 'Total KK di DB'],
            [[$created, $updated, $skipped, $identitasRusak, $nonRw10, $tanpaKK, Keluarga::count()]]
        );

        // Ringkas per RT (RW 10 saja)
        $perRt = Keluarga::where('rw', '10')->selectRaw('rt, COUNT(*) as n')->groupBy('rt')->orderBy('rt')->pluck('n', 'rt');
        $this->line('Per RT (RW 10): ' . $perRt->map(fn($n, $rt) => "$rt=$n")->implode(', '));

        return self::SUCCESS;
    }
}
