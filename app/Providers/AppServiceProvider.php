<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // scoped, bukan singleton: satu instance per request, di-reset antar
        // request supaya konteks tenant tidak pernah bocor lintas request.
        $this->app->scoped(\App\Services\TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge jumlah pendaftaran pending untuk sidebar.
        // Sebelumnya di-query langsung di dalam Blade layout, jadi ikut jalan di
        // setiap render halaman untuk semua pengguna. Di sini hanya dihitung bila
        // menunya memang tampil, dan logikanya keluar dari markup.
        View::composer('layouts.app', function ($view) {
            $pending = 0;
            if (auth()->check() && userCan('pendaftaran')) {
                $pending = \App\Models\Pendaftaran::where('status', 'pending')->count();
            }
            $view->with('pendaftaranPending', $pending);
        });

        $this->aturPembatasLajuPublik();
    }

    /**
     * Pembatas laju pintu publik (tanpa login): pendaftaran warga dan
     * pemulihan PIN. Masing-masing dua kunci: per IP (satu mesin menembak
     * banyak identitas) dan per identitas yang sudah dinormalisasi (banyak
     * mesin menembak satu identitas). Kunci identitas disidik ber-APP_KEY
     * supaya NIK/nomor tidak tersimpan mentah di cache.
     *
     * Batas berlaku SAMA untuk identitas terdaftar maupun tidak, jadi
     * jawaban 429 tidak membocorkan keberadaan akun.
     */
    private function aturPembatasLajuPublik(): void
    {
        $jsonTerlalu = fn () => fn (Request $r, array $headers) => response()->json([
            'success' => false,
            'message' => 'Terlalu banyak permintaan. Coba lagi dalam '.max(1, (int) ceil(($headers['Retry-After'] ?? 60) / 60)).' menit.',
        ], 429, $headers);

        $nomor = fn (Request $r) => sidikPribadi((string) normalizeWa($r->input('no_wa')));

        RateLimiter::for('pendaftaran', function (Request $r) {
            $tolak = fn (Request $r, array $headers) => back()
                ->with('error_register', 'Terlalu banyak pengajuan. Coba lagi dalam '.max(1, (int) ceil(($headers['Retry-After'] ?? 60) / 60)).' menit.')
                ->withInput($r->except('_token'));
            $nik = sidikPribadi((string) normalisasiIdentitas($r->input('nik')));

            return [
                Limit::perHour(5)->by('daftar-ip:'.$r->ip())->response($tolak),
                Limit::perDay(3)->by('daftar-nik:'.$nik)->response($tolak),
            ];
        });

        RateLimiter::for('pemulihan-pin', fn (Request $r) => [
            Limit::perMinutes(15, 5)->by('pulih-ip:'.$r->ip())->response($jsonTerlalu()),
            Limit::perHour(3)->by('pulih-wa:'.$nomor($r))->response($jsonTerlalu()),
        ]);

        // Longgar dari batas percobaan per kode (5, di PemulihanPin): lapisan
        // ini menahan tembakan lintas kode/lintas nomor dari satu sumber.
        RateLimiter::for('pemulihan-verifikasi', fn (Request $r) => [
            Limit::perMinutes(15, 20)->by('verif-ip:'.$r->ip())->response($jsonTerlalu()),
            Limit::perMinutes(15, 10)->by('verif-wa:'.$nomor($r))->response($jsonTerlalu()),
        ]);
    }
}
