<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Web app manifest per tenant. Dulu berkas statis public/site.webmanifest
 * yang menulis tetap "Kampung Paru" dan warna hijau untuk semua tenant.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $nama = namaAplikasi();

        return response()->json([
            'name' => $nama,
            'short_name' => mb_substr($nama, 0, 24),
            'description' => 'Sistem informasi & keuangan komunitas '.$nama.', '.lokasiSingkat().'.',
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#F8FAFC',
            'theme_color' => warnaMerek(),
            'lang' => 'id',
            'icons' => [
                ['src' => ikonAplikasi(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => ikonAplikasi(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
