<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Pembaruan aplikasi dari branch rilis (Phase G): cek versi terbaru di
 * `origin/production` dan jalankan update (pull + composer bila perlu +
 * migrate + cache) dari halaman Pembaruan Sistem.
 *
 * Remote dan branch DIKUNCI sebagai konstanta - tidak pernah dari input
 * request, supaya tombol web ini tidak bisa disetir menarik kode dari
 * tempat lain. Pull memakai --ff-only: server yang berubah lokal membuat
 * update berhenti dengan pesan jelas, bukan merge diam-diam.
 */
class PembaruAplikasi
{
    public const KUNCI_STATUS = 'pembaruan.status';

    private const REMOTE = 'origin';

    private const BRANCH = 'production';

    /** ['hash' => ..., 'tanggal' => ..., 'judul' => ...] dari commit terpasang. */
    public function versiTerpasang(): array
    {
        // Format dikutip: tanpa kutip, shell menafsirkan `|` sebagai pipe.
        $hasil = Process::path(base_path())->run('git log -1 --format="%h|%cs|%s"');
        [$hash, $tanggal, $judul] = array_pad(explode('|', trim($hasil->output()), 3), 3, '');

        return ['hash' => $hash, 'tanggal' => $tanggal, 'judul' => $judul];
    }

    /** Ambil status terakhir dari cache (dibaca badge sidebar, tanpa kerja jaringan). */
    public static function statusTercatat(): array
    {
        return Cache::get(self::KUNCI_STATUS, [
            'tersedia' => false, 'jumlah' => 0, 'daftar' => [], 'dicek_pada' => null,
        ]);
    }

    /** Fetch remote lalu hitung ketinggalan; hasilnya dicatat ke cache. */
    public function cek(): array
    {
        $remote = self::REMOTE;
        $branch = self::BRANCH;

        $fetch = Process::path(base_path())->timeout(60)->run("git fetch {$remote} {$branch}");
        if ($fetch->failed()) {
            throw new \RuntimeException('Gagal menghubungi repositori: '.trim($fetch->errorOutput()));
        }

        $jumlah = (int) trim(Process::path(base_path())
            ->run("git rev-list --count HEAD..{$remote}/{$branch}")->output());

        $daftar = [];
        if ($jumlah > 0) {
            $log = Process::path(base_path())
                ->run("git log HEAD..{$remote}/{$branch} --format=\"%h %s\" -20")->output();
            $daftar = array_values(array_filter(array_map('trim', explode("\n", $log))));
        }

        $status = [
            'tersedia' => $jumlah > 0,
            'jumlah' => $jumlah,
            'daftar' => $daftar,
            'dicek_pada' => now()->toDateTimeString(),
        ];
        Cache::put(self::KUNCI_STATUS, $status, now()->addDays(7));

        return $status;
    }

    /**
     * Jalankan update. Mengembalikan log per langkah; melempar RuntimeException
     * dengan log yang sudah terkumpul bila ada langkah yang gagal.
     *
     * Composer hanya dijalankan bila composer.lock ikut berubah - langkah
     * termahal dan paling rawan timeout, jangan dibayar kalau tidak perlu.
     */
    public function jalankan(): array
    {
        $remote = self::REMOTE;
        $branch = self::BRANCH;
        $log = [];

        $lockBerubah = str_contains(
            Process::path(base_path())->run("git diff --name-only HEAD {$remote}/{$branch}")->output(),
            'composer.lock'
        );

        $langkah = [["git pull --ff-only {$remote} {$branch}", 300]];
        if ($lockBerubah) {
            $langkah[] = ['composer install --no-dev --optimize-autoloader --no-interaction', 600];
        }
        $php = PHP_BINARY;
        $langkah[] = ["{$php} artisan migrate --force", 300];
        foreach (['config:clear', 'config:cache', 'route:cache', 'view:cache'] as $artisan) {
            $langkah[] = ["{$php} artisan {$artisan}", 120];
        }

        foreach ($langkah as [$perintah, $timeout]) {
            $hasil = Process::path(base_path())->timeout($timeout)->run($perintah);
            $log[] = ['perintah' => $perintah, 'keluaran' => trim($hasil->output()."\n".$hasil->errorOutput())];
            if ($hasil->failed()) {
                throw new \RuntimeException(
                    "Gagal pada `{$perintah}`: ".trim($hasil->errorOutput() ?: $hasil->output()),
                    0,
                    new \Exception(json_encode($log))
                );
            }
        }

        // Sudah di versi terbaru: bersihkan penanda notifikasi.
        Cache::put(self::KUNCI_STATUS, [
            'tersedia' => false, 'jumlah' => 0, 'daftar' => [],
            'dicek_pada' => now()->toDateTimeString(),
        ], now()->addDays(7));

        return $log;
    }
}
