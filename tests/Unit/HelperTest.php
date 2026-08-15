<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Helper domain di app/helpers.php.
 *
 * Fungsi-fungsi ini dipakai bersama oleh form, importer, dan broadcast WA, jadi
 * perilakunya harus terkunci: kalau normalizeWa berubah diam-diam, nomor warga
 * salah kirim tanpa ada yang sadar.
 */
class HelperTest extends TestCase
{
    public static function nomorWa(): array
    {
        return [
            'awalan 08' => ['081234567890', '6281234567890'],
            'awalan 8' => ['81234567890', '6281234567890'],
            'awalan +62' => ['+6281234567890', '6281234567890'],
            'awalan 0062' => ['006281234567890', '6281234567890'],
            'sudah 62' => ['6281234567890', '6281234567890'],
            'pakai spasi & strip' => ['0812-3456 7890', '6281234567890'],
            'awalan 620' => ['6208123456789', '628123456789'],
        ];
    }

    #[DataProvider('nomorWa')]
    public function test_normalisasi_nomor_wa(string $masukan, string $harapan): void
    {
        $this->assertSame($harapan, normalizeWa($masukan));
    }

    public function test_nomor_kosong_menghasilkan_null(): void
    {
        $this->assertNull(normalizeWa(''));
        $this->assertNull(normalizeWa('-'));
        $this->assertNull(normalizeWa(null));
    }

    public function test_format_rupiah(): void
    {
        $this->assertSame('Rp 5.000', formatRupiah(5000));
        $this->assertSame('Rp 1.250.000', formatRupiah(1250000));
        $this->assertSame('15.000', formatRupiah(15000, false));
        $this->assertSame('Rp 0', formatRupiah(0));
    }

    public static function teksPekerjaan(): array
    {
        return [
            ['', 'Tidak Bekerja'],
            ['-', 'Tidak Bekerja'],
            ['Belum Bekerja', 'Tidak Bekerja'],
            ['Menganggur', 'Tidak Bekerja'],
            ['Balita', 'Tidak Bekerja'],
            ['Sedang mencari kerja', 'Mencari Kerja'],
            ['Pelajar SMA', 'Sekolah'],
            ['Mahasiswa', 'Sekolah'],
            ['Ibu Rumah Tangga', 'Mengurus Rumah Tangga'],
            ['IRT', 'Mengurus Rumah Tangga'],
            ['Pensiunan PNS', 'Pensiunan'],
            ['Wiraswasta', 'Bekerja'],
            ['Buruh Harian', 'Bekerja'],
        ];
    }

    #[DataProvider('teksPekerjaan')]
    public function test_klasifikasi_status_pekerjaan(string $teks, string $harapan): void
    {
        $this->assertSame($harapan, statusKerjaDariPekerjaan($teks));
    }

    public function test_titik_tengah_penghasilan(): void
    {
        $this->assertSame(0, incomeMidpoint(''));
        $this->assertSame(0, incomeMidpoint('-'));
        $this->assertSame(750000, incomeMidpoint('500rb-1jt'));
        $this->assertSame(1750000, incomeMidpoint('1 - 2.5 Juta'));
        $this->assertSame(3750000, incomeMidpoint('2.5 - 5 Juta'));
        $this->assertSame(6000000, incomeMidpoint('> 5 Juta'));
    }
}
