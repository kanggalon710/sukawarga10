<?php

namespace App\Providers;

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
    }
}
