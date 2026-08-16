<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Penjaga rute untuk feature flag per tenant (Phase F, §18).
 *
 * `userCan()` hanya menyembunyikan menu; tanpa middleware ini modul yang
 * dimatikan (`fitur_<modul> = 0`) tetap bisa dibuka lewat URL langsung.
 * Modul yang mati dijawab 404, bukan 403: bagi tenant itu modulnya memang
 * tidak ada, sama seperti resource tenant lain.
 */
class PastikanFiturAktif
{
    public function handle(Request $request, Closure $next, string $modul)
    {
        abort_unless(fiturAktif($modul), 404);

        return $next($request);
    }
}
