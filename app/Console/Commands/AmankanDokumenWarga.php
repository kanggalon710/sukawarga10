<?php

namespace App\Console\Commands;

use App\Models\Keluarga;
use App\Services\PenyimpanBerkas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan dokumen warga warisan dari disk publik ke disk privat.
 *
 * Sebelum 2026-09-30 scan KK, foto rumah, dan PBB tersimpan di
 * storage/app/public/dokumen dan bisa dibuka siapa pun lewat /storage.
 * Perintah ini memindahkannya ke storage/app/private dengan path relatif
 * yang SAMA, jadi kolom database tidak perlu diubah. Tanpa --jalankan hanya
 * melapor. Keluaran hanya berupa angka: tidak ada path atau nama warga.
 */
class AmankanDokumenWarga extends Command
{
    protected $signature = 'dokumen:amankan
        {--jalankan : Benar-benar memindahkan/menghapus (tanpa ini hanya laporan)}
        {--hapus-yatim : Hapus berkas di folder dokumen yang tidak dirujuk KK mana pun}
        {--kembalikan : Rollback: pindahkan berkas yang dirujuk kembali ke disk publik}';

    protected $description = 'Pindahkan dokumen warga dari disk publik ke disk privat dan laporkan berkas yatim';

    public function handle(PenyimpanBerkas $penyimpan): int
    {
        $jalankan = (bool) $this->option('jalankan');
        [$asal, $tujuan] = $this->option('kembalikan') ? ['local', 'public'] : ['public', 'local'];

        // Lintas tenant dengan sadar: perintah konsol memindahkan berkas semua RW.
        $dirujuk = [];
        Keluarga::withoutGlobalScope('organisasi')
            ->select(array_keys(PenyimpanBerkas::KOLOM_KK))
            ->chunk(500, function ($baris) use (&$dirujuk) {
                foreach ($baris as $kk) {
                    foreach (array_keys(PenyimpanBerkas::KOLOM_KK) as $kolom) {
                        if ($kk->{$kolom}) {
                            $dirujuk[$kk->{$kolom}] = true;
                        }
                    }
                }
            });

        $dipindah = 0;
        $gagal = 0;
        foreach (array_keys($dirujuk) as $path) {
            if (! $this->pathDokumen($path) || ! Storage::disk($asal)->exists($path)) {
                continue;
            }
            if (Storage::disk($tujuan)->exists($path)) {
                continue; // sudah pernah dipindah; salinan asal dibiarkan untuk diperiksa manual
            }
            if (! $jalankan) {
                $dipindah++;

                continue;
            }

            $isi = Storage::disk($asal)->get($path);
            if ($isi !== null && Storage::disk($tujuan)->put($path, $isi)
                && hash('sha256', (string) Storage::disk($tujuan)->get($path)) === hash('sha256', $isi)) {
                Storage::disk($asal)->delete($path);
                $dipindah++;
            } else {
                $gagal++;
            }
        }

        $yatim = 0;
        foreach ($penyimpan->folderDokumenWarga() as [$disk, $folder]) {
            foreach (Storage::disk($disk)->allFiles($folder) as $path) {
                if (isset($dirujuk[$path])) {
                    continue;
                }
                $yatim++;
                if ($jalankan && $this->option('hapus-yatim')) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }

        $mode = $jalankan ? '' : ' (laporan saja, tambahkan --jalankan)';
        $this->info('Berkas dirujuk KK: '.count($dirujuk).$mode);
        $this->info(($jalankan ? 'Dipindah' : 'Akan dipindah')." {$asal} -> {$tujuan}: {$dipindah}");
        if ($gagal > 0) {
            $this->error("Gagal dipindah (salinan asal tetap ada): {$gagal}");
        }
        $this->info('Berkas yatim: '.$yatim.($jalankan && $this->option('hapus-yatim') ? ' (dihapus)' : ''));

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Hanya path relatif di folder dokumen yang dikenal; tidak pernah ../ */
    private function pathDokumen(string $path): bool
    {
        return ! str_contains($path, '..') && ! str_contains($path, "\0")
            && (str_starts_with($path, 'dokumen/') || str_starts_with($path, 'dokumen-warga/'));
    }
}
