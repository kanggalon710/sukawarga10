<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware — applied to all requests
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Resolver tenant hanya di grup web: health check `/up` dan rute api
        // (sisa Sanctum) tidak terikat hostname tenant. Resolver juga
        // membatasi host platform/desa ke halaman tingkatnya sendiri.
        $middleware->web(append: [\App\Http\Middleware\ResolveTenant::class]);

        // Resolver WAJIB berjalan sebelum auth: tanpa ini, sorting priority
        // Laravel mendahulukan Authenticate sehingga tamu di host/rute yang
        // seharusnya 404 malah di-redirect ke login. Targetnya KONTRAK
        // AuthenticatesRequests - itulah entri yang ada di priority list
        // bawaan, bukan kelas Authenticate.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\ResolveTenant::class,
        );

        $middleware->alias([
            // Penjaga rute izin, SATU-SATUNYA (alias `role:`/CheckRole sudah
            // dipensiunkan 2026-08-18). Selalu dipasang SESUDAH `fitur:`.
            'izin' => \App\Http\Middleware\PastikanBerizin::class,
            'fitur' => \App\Http\Middleware\PastikanFiturAktif::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
