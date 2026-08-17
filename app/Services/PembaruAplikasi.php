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
     * Binari PHP CLI untuk menjalankan artisan dari langkah update.
     *
     * BUKAN PHP_BINARY mentah: dari web, PHP berjalan di bawah PHP-FPM dan
     * PHP_BINARY menunjuk daemonnya (/opt/cpanel/.../sbin/php-fpm) yang tidak
     * bisa menjalankan skrip - update produksi 2026-08-17 gagal karena ini.
     * Peta sbin/php-fpm -> bin/php memakai CLI seversi persis (pola
     * cPanel/EasyApache); bila tidak ada, PhpExecutableFinder mencari lewat
     * PATH. Parameter injeksi hanya untuk tes (SAPI tes selalu cli).
     */
    public static function binariPhpCli(?string $binary = null, ?string $sapi = null): string
    {
        $binary ??= PHP_BINARY;
        $sapi ??= PHP_SAPI;

        if ($sapi === 'cli' && $binary !== '') {
            return $binary;
        }

        $kandidat = preg_replace('#/sbin/php-fpm[^/]*$#', '/bin/php', $binary);
        if ($kandidat !== $binary && is_executable($kandidat)) {
            return $kandidat;
        }

        $ditemukan = (new \Symfony\Component\Process\PhpExecutableFinder)->find(false);

        return $ditemukan !== false ? $ditemukan : 'php';
    }

    /**
     * Jalankan update. Mengembalikan ['mutakhir' => bool, 'log' => langkah[]];
     * melempar RuntimeException dengan log yang sudah terkumpul bila ada
     * langkah yang gagal.
     *
     * Cek dilakukan DI SINI lebih dulu supaya tombol Perbarui cukup satu
     * klik tanpa Periksa: bila sudah mutakhir, berhenti tanpa membayar
     * pull/migrate/cache (badge sidebar ikut tersegarkan oleh cek()).
     * Composer hanya dijalankan bila composer.lock ikut berubah - langkah
     * termahal dan paling rawan timeout, jangan dibayar kalau tidak perlu.
     */
    public function jalankan(): array
    {
        if (! $this->cek()['tersedia']) {
            return ['mutakhir' => true, 'log' => []];
        }

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
        $php = self::binariPhpCli();
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

        return ['mutakhir' => false, 'log' => $log];
    }
}
