<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Merek visual aplikasi: logo, ikon (favicon), dan warna utama per tenant.
 *
 * Nilainya dari AppSetting (diwariskan platform -> desa -> RW seperti
 * identitas lain) dan diubah lewat Pengaturan > Tampilan. Tanpa setting,
 * semuanya jatuh ke berkas dan warna bawaan repo, jadi tampilan instalasi
 * yang tidak mengubah apa pun tetap persis seperti sebelumnya.
 *
 * Warna TIDAK PERNAH diterima sebagai CSS bebas: hanya satu hex #RRGGBB yang
 * lolos cek kontras, lalu turunannya dihitung di sini dan ditulis ke daftar
 * custom property yang tetap (TOKEN_WARNA).
 */
class MerekAplikasi
{
    public const WARNA_BAWAAN = '#0F7A4D';

    /** Ukuran varian ikon yang dibuat saat unggah (favicon, apple-touch, manifest). */
    public const UKURAN_IKON = [16, 32, 180, 192, 512];

    /** Custom property yang boleh ditimpa blok tema. Hanya ini, tidak ada CSS lain. */
    public const TOKEN_WARNA = [
        '--hijau', '--primary', '--primary-hover', '--primary-active', '--primary-dark',
        '--primary-soft', '--hijau-pale', '--sidebar-bg', '--sidebar-from', '--sidebar-to',
    ];

    /** Kontras minimum teks putih di atas warna utama (WCAG AA teks normal). */
    public const KONTRAS_MIN = 4.5;

    private const IKON_BAWAAN = [
        16 => 'favicon-16.png', 32 => 'favicon-32.png', 180 => 'apple-touch-icon.png',
        192 => 'icon-192.png', 512 => 'icon-512.png',
    ];

    /** URL logo lebar (halaman login/hero). */
    public static function logo(): string
    {
        $path = self::setting('merek_logo');

        return $path !== '' ? asset('storage/'.$path) : asset('logo-sukawarga.svg');
    }

    /** Apakah logo lebar diganti tenant. */
    public static function adaLogoKustom(): bool
    {
        return self::setting('merek_logo') !== '';
    }

    /**
     * URL ikon. Tanpa ukuran: ikon untuk tampilan (sidebar, kop cadangan),
     * yaitu varian 192 bila ada ikon kustom, atau SVG bawaan.
     */
    public static function ikon(?int $ukuran = null): string
    {
        $dasar = self::setting('merek_ikon');
        if ($dasar === '') {
            return asset($ukuran === null ? 'logo-sukawarga-icon.svg' : (self::IKON_BAWAAN[$ukuran] ?? 'icon-192.png'));
        }

        $ukuran ??= 192;
        if (! in_array($ukuran, self::UKURAN_IKON, true)) {
            $ukuran = 192;
        }

        return asset("storage/{$dasar}-{$ukuran}.png");
    }

    public static function adaIkonKustom(): bool
    {
        return self::setting('merek_ikon') !== '';
    }

    /** Warna utama efektif (#RRGGBB huruf besar). */
    public static function warna(): string
    {
        return self::warnaKustom() ?? self::WARNA_BAWAAN;
    }

    /** Warna utama yang diatur tenant, atau null bila memakai bawaan. */
    public static function warnaKustom(): ?string
    {
        $nilai = strtoupper(self::setting('merek_warna_utama'));

        return self::hexSah($nilai) ? $nilai : null;
    }

    /**
     * Token => hex untuk blok tema, atau [] bila warna bawaan dipakai (CSS
     * repo tidak ditimpa sama sekali).
     *
     * @return array<string, string>
     */
    public static function paletKustom(): array
    {
        $warna = self::warnaKustom();

        return $warna === null ? [] : self::palet($warna);
    }

    /** @return array<string, string> */
    public static function palet(string $hex): array
    {
        $hover = self::campur($hex, '#000000', 0.18);
        $aktif = self::campur($hex, '#000000', 0.45);
        $lembut = self::campur($hex, '#FFFFFF', 0.92);

        return [
            '--hijau' => $hex, '--primary' => $hex,
            '--primary-hover' => $hover, '--primary-active' => $aktif, '--primary-dark' => $aktif,
            '--primary-soft' => $lembut, '--hijau-pale' => $lembut,
            '--sidebar-bg' => $aktif, '--sidebar-from' => $hex, '--sidebar-to' => $aktif,
        ];
    }

    public static function hexSah(string $nilai): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $nilai);
    }

    /** Rasio kontras WCAG antara warna ini dan putih. */
    public static function kontrasDenganPutih(string $hex): float
    {
        return 1.05 / (self::luminans($hex) + 0.05);
    }

    // -------------------------------------------------------------------------

    private static function setting(string $key): string
    {
        try {
            return trim((string) AppSetting::nilai($key, ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private static function luminans(string $hex): float
    {
        $kanal = array_map(function (string $dua) {
            $c = hexdec($dua) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $kanal[0] + 0.7152 * $kanal[1] + 0.0722 * $kanal[2];
    }

    private static function campur(string $a, string $b, float $t): string
    {
        $ka = str_split(substr($a, 1), 2);
        $kb = str_split(substr($b, 1), 2);
        $hasil = '#';
        for ($i = 0; $i < 3; $i++) {
            $nilai = (int) round(hexdec($ka[$i]) * (1 - $t) + hexdec($kb[$i]) * $t);
            $hasil .= sprintf('%02X', max(0, min(255, $nilai)));
        }

        return $hasil;
    }
}
