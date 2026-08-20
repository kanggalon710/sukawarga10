<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * normalisasiIdentitas(): satu-satunya pintu masuk No. KK / NIK.
 *
 * Kasus nyata di bawah diambil dari data RW 07 Bagendit, tempat nomor 16 digit
 * rusak permanen karena Google Sheets memperlakukannya sebagai angka.
 */
class NormalisasiIdentitasTest extends TestCase
{
    public static function nilaiIdentitas(): array
    {
        return [
            'enam belas digit bersih' => ['9999990112200015', '9999990112200015'],
            'spasi dibuang' => ['9999 9901 1220 0015', '9999990112200015'],
            'tanda hubung dibuang' => ['9999-9901-1220-0015', '9999990112200015'],
            'diapit spasi' => ['  9999990112200015  ', '9999990112200015'],

            'kosong' => ['', null],
            'strip saja' => ['-', null],
            'null' => [null, null],

            // Notasi ilmiah: digit belakang sudah hilang, jangan dipungut sisanya.
            'notasi ilmiah No.KK' => ['9.999993190736E+016', null],
            'notasi ilmiah NIK' => ['9.99999170419858E+018', null],
            'notasi ilmiah huruf kecil' => ['9.999993190736e+016', null],

            'terpotong elipsis' => ['999999...', null],
            'lima belas digit' => ['999999200370001', null],
            'tiga belas digit' => ['9999991909960', null],
            'tujuh belas digit' => ['99999931907360006', null],
            'sembilan belas digit' => ['9999991704198580003', null],
            'ada huruf di ujung' => ['9999991909960b', null],
            'huruf di tengah' => ['9999992310715b4', null],
        ];
    }

    #[DataProvider('nilaiIdentitas')]
    public function test_normalisasi($masukan, ?string $harapan): void
    {
        $this->assertSame($harapan, normalisasiIdentitas($masukan));
    }

    public function test_kosong_bukan_rusak(): void
    {
        // Pendataan yang belum lengkap tidak boleh diperlakukan sebagai salah.
        $this->assertFalse(identitasRusak(''));
        $this->assertFalse(identitasRusak('-'));
        $this->assertFalse(identitasRusak(null));
    }

    public function test_diisi_tapi_tidak_terbaca_dianggap_rusak(): void
    {
        $this->assertTrue(identitasRusak('9.999993190736E+016'));
        $this->assertTrue(identitasRusak('999999200370001'));
        $this->assertFalse(identitasRusak('9999990112200015'));
    }
}
