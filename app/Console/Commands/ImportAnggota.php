<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Keluarga;
use App\Models\Anggota;

class ImportAnggota extends Command
{
    protected $signature = 'import:anggota {file : CSV anggota (kolom ref_nokk,rt,nama,jk,tgl_lahir,status,pekerjaan,bpjs,keterangan)} {--fresh : Hapus semua anggota lama dulu}';
    protected $description = 'Import anggota keluarga & link ke KK induk via No.KK referensi. Baris "Kepala Keluarga" memperbarui tgl lahir + jenis kelamin KK (tidak dibuat anggota).';

    public function handle(): int
    {
        $file = $this->argument('file');
        if (!is_file($file)) { $this->error("File tidak ditemukan: $file"); return self::FAILURE; }

        if ($this->option('fresh')) {
            $this->warn('Menghapus anggota lama...');
            Anggota::query()->delete();
        }

        $h = fopen($file, 'r');
        $bom = fread($h, 3); if ($bom !== "\xEF\xBB\xBF") rewind($h);
        $header = fgetcsv($h);
        if (!$header) { $this->error('CSV kosong/invalid.'); return self::FAILURE; }
        $map = array_flip(array_map('trim', $header));
        $get = fn($row, $k) => (isset($map[$k]) && isset($row[$map[$k]])) ? trim((string) $row[$map[$k]]) : '';

        $created = 0; $headUpd = 0; $unmatched = 0; $skip = 0; $unmatchedRefs = [];

        while (($row = fgetcsv($h)) !== false) {
            $nama = $get($row, 'nama');
            if ($nama === '') { $skip++; continue; }
            $ref = $get($row, 'ref_nokk');

            // Cari KK induk: No.KK → NIK
            $kk = null;
            if ($ref !== '') {
                $kk = Keluarga::where('noKK', $ref)->first() ?: Keluarga::where('nik', $ref)->first();
            }
            if (!$kk) { $unmatched++; if ($ref !== '') $unmatchedRefs[$ref] = true; continue; }

            $jk = strtoupper(substr($get($row, 'jk'), 0, 1)) === 'P' ? 'P' : 'L';
            $tgl = $get($row, 'tgl_lahir') ?: null;
            $status = $get($row, 'status') ?: 'Anggota';
            $bpjs = strtolower($get($row, 'bpjs')) === 'ya' ? 'Aktif' : 'Belum';

            if (stripos($status, 'kepala') === 0) {
                // Baris kepala keluarga = KK itu sendiri → lengkapi data KK, JANGAN buat anggota (hindari double-count)
                $upd = ['jenisKelaminKK' => $jk];
                if ($tgl) $upd['tanggalLahirKK'] = $tgl;
                $kk->update($upd);
                $headUpd++;
            } else {
                Anggota::create([
                    'anggota_id' => 'ag_' . Str::uuid()->toString(),
                    'keluarga_id' => $kk->keluarga_id,
                    'nama' => $nama,
                    'jenisKelamin' => $jk,
                    'statusKeluarga' => $status,
                    'tanggalLahir' => $tgl,
                    'pekerjaan' => $get($row, 'pekerjaan') ?: null,
                    'statusPekerjaan' => statusKerjaDariPekerjaan($get($row, 'pekerjaan')),
                    'statusBPJS' => $bpjs,
                ]);
                $created++;
            }
        }
        fclose($h);

        // Sinkronkan jumlahAnggota tiap KK (1 kepala + anggota)
        foreach (Keluarga::all() as $k) {
            $k->update(['jumlahAnggota' => 1 + $k->anggota()->count()]);
        }

        $this->newLine();
        $this->info('✅ Import anggota selesai.');
        $this->table(
            ['Anggota dibuat', 'KK (kepala) di-update', 'Ref tak cocok', 'Baris kosong', 'Total Anggota di DB'],
            [[$created, $headUpd, $unmatched, $skip, Anggota::count()]]
        );
        if (!empty($unmatchedRefs)) {
            $this->warn('No.KK referensi tidak ditemukan di DB (' . count($unmatchedRefs) . '): ' . implode(', ', array_slice(array_keys($unmatchedRefs), 0, 8)) . (count($unmatchedRefs) > 8 ? ' ...' : ''));
        }
        return self::SUCCESS;
    }
}
