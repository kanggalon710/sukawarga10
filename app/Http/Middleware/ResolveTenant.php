<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Petakan hostname request ke tenant, satu kali per request (Phase B2).
 *
 * Hostname tak terdaftar atau nonaktif ditolak 404 - TANPA fallback
 * diam-diam ke tenant lain (aturan §14 dokumen arsitektur). Hostname yang
 * boleh masuk didaftarkan lewat tabel `domains` (produksi, legacy, dev),
 * bukan di kode.
 *
 * Query-nya satu lookup ber-index pada tabel kecil; belum perlu cache, dan
 * cache di sini butuh cerita invalidasi saat operator mengubah domain.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $hostname = strtolower($request->getHost());

        $domain = Domain::with('organization')
            ->where('hostname', $hostname)
            ->where('status', '!=', 'nonaktif')
            ->first();

        if (! $domain || ! $domain->organization || $domain->organization->status !== 'aktif') {
            abort(404, 'Alamat ini tidak terdaftar pada layanan mana pun.');
        }

        app(TenantContext::class)->tetapkan($domain);

        return $next($request);
    }
}
