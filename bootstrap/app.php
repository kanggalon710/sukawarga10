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
        // (sisa Sanctum) tidak terikat hostname tenant.
        $middleware->web(append: [\App\Http\Middleware\ResolveTenant::class]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
