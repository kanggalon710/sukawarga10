<?php

namespace App\Http\Middleware;

use App\Services\MatriksKapabilitas;
use Closure;
use Illuminate\Http\Request;

/**
 * Penjaga rute berbasis matriks kapabilitas: `izin:surat.ubah`.
 *
 * Beberapa argumen berarti OR MURNI - punya salah satu sudah cukup. Ini
 * bedanya dengan `role:a,b` lama (CheckRole) yang sesungguhnya berarti
 * "power >= max(power a, power b)", sehingga `role:superadmin,ketua_rw` hanya
 * meloloskan ketua_rw lewat kebetulan pencocokan nama persis.
 *
 * Bentuk respons sengaja dipertahankan sama dengan CheckRole (403, atau JSON
 * untuk request yang meminta JSON) supaya penukaran penjaga tidak mengubah
 * kontrak yang dilihat klien.
 *
 * Selalu dipasang SESUDAH `fitur:<modul>`: modul yang dimatikan tenant harus
 * menjawab 404 (modulnya memang tidak ada), bukan 403 yang justru membocorkan
 * keberadaannya.
 */
class PastikanBerizin
{
    public function handle(Request $request, Closure $next, string ...$kapabilitas)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (MatriksKapabilitas::userPunya($user, ...$kapabilitas)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}
