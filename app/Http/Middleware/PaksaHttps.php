<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Alihkan permintaan HTTP polos ke HTTPS.
 *
 * Insiden 2026-08-18: tenant baru yang sertifikatnya belum terbit tetap
 * melayani halaman login lewat HTTP. Karena `SESSION_SECURE_COOKIE=true`,
 * browser tidak pernah menyimpan cookie sesinya, sehingga setiap pengiriman
 * form menabrak "419 PAGE EXPIRED" tanpa petunjuk - dan PIN warga sempat
 * melintas tanpa enkripsi sebelum ditolak. Menyajikan portal di HTTP polos
 * tidak pernah benar; lebih baik dialihkan daripada rusak diam-diam.
 *
 * Dipasang sebagai middleware GLOBAL supaya berjalan sebelum sesi dan CSRF -
 * bukan di grup web yang sudah terlanjur menyentuh cookie.
 *
 * Mati di luar produksi supaya `php artisan serve` dan suite tes tetap jalan
 * di http://localhost, dan bisa dimatikan lewat `PAKSA_HTTPS=false` sebagai
 * katup darurat kalau deteksi skema meleset di suatu lingkungan.
 */
class PaksaHttps
{
    /**
     * Jalur validasi ACME. Penerbitan sertifikat tenant BARU justru terjadi
     * saat HTTPS-nya belum ada, jadi jalur ini tidak boleh ikut dialihkan.
     * Apache biasanya sudah menyajikan berkasnya lebih dulu (aturan -f di
     * public/.htaccess), tapi pengecualian ini membuat penerbitan tidak
     * bergantung pada detail itu maupun pada apakah klien ACME-nya mau
     * mengikuti redirect.
     */
    private const JALUR_DIKECUALIKAN = '.well-known/';

    public function handle(Request $request, Closure $next)
    {
        if (! config('app.paksa_https')
            || $request->isSecure()
            || str_starts_with(ltrim($request->path(), '/'), self::JALUR_DIKECUALIKAN)) {
            return $next($request);
        }

        // 301: alamat HTTPS adalah alamat yang benar dan permanen. HSTS
        // (SecurityHeaders) menindaklanjuti supaya kunjungan berikutnya tidak
        // perlu singgah di HTTP sama sekali.
        return redirect()->secure($request->getRequestUri(), 301);
    }
}
