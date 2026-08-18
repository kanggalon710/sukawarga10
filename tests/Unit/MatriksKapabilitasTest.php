<?php

namespace Tests\Unit;

use App\Services\MatriksKapabilitas as Matriks;
use PHPUnit\Framework\TestCase;

/**
 * Matriks kapabilitas: sumber tunggal "peran mana boleh apa", menggantikan
 * hierarki linier User::LEVEL_POWER. Tes ini mengunci ISI matriks bawaan -
 * mengubah pembagian tugas pengurus RW berarti mengubah tes ini dengan sadar,
 * bukan tanpa sengaja lewat edit satu baris array.
 *
 * Murni unit (tanpa database): matriks bawaan adalah konstanta kode.
 */
class MatriksKapabilitasTest extends TestCase
{
    public function test_superadmin_memegang_seluruh_katalog(): void
    {
        // Dibandingkan terhadap SELURUH katalog, bukan daftar yang ditulis
        // ulang: key baru yang ditambahkan nanti otomatis ikut, sehingga
        // keputusan "superadmin tidak dibatasi" tidak bisa lapuk diam-diam.
        $this->assertSame(Matriks::semua(), Matriks::untukPeran('superadmin'));
    }

    public function test_level_setara_superadmin_ikut_tak_terbatas(): void
    {
        // Akun bawaan aplikasi berlevel 'admin' (DatabaseSeeder), bukan
        // 'superadmin'. Kalau normalisasi ini lupa, akun itu jatuh ke lantai
        // warga dan mengunci semua orang.
        $this->assertSame(Matriks::semua(), Matriks::untukPeran('admin'));
    }

    public function test_ketua_menyetujui_surat_tapi_tidak_membuatnya(): void
    {
        $ketua = Matriks::untukPeran('ketua_rw');

        $this->assertContains('surat.ttdRw', $ketua);
        $this->assertContains('surat.tolak', $ketua);
        $this->assertNotContains('surat.buat', $ketua);
        $this->assertNotContains('surat.ubah', $ketua);
        $this->assertNotContains('surat.ubahIsi', $ketua);
        $this->assertNotContains('surat.hapus', $ketua);
    }

    public function test_ketua_melihat_semua_modul_tapi_hanya_enam_titik_ubah(): void
    {
        $ketua = Matriks::untukPeran('ketua_rw');

        foreach (['warga', 'sampah', 'padaringan', 'surat', 'umkm', 'bukukas',
            'pengeluaran', 'sumbangan', 'setor', 'aduan', 'mpwa', 'kegiatan',
            'laporan', 'log', 'pengaturan'] as $modul) {
            $this->assertContains("{$modul}.lihat", $ketua, "Ketua harus bisa melihat modul {$modul}");
        }

        // Aksi baca yang namanya bukan `.lihat`, jadi harus disebut manual.
        $membaca = ['surat.cetak', 'warga.cari', 'aduan.lapor'];
        $titikUbah = array_values(array_filter(
            $ketua,
            fn ($k) => ! str_ends_with($k, '.lihat') && ! in_array($k, $membaca, true)
        ));
        sort($titikUbah);

        $this->assertSame([
            'akun.kelola',
            'pendaftaran.putuskan',
            'pengaturan.ubah',
            'surat.tolak',
            'surat.ttdRw',
            'transaksi.void',
        ], $titikUbah);
    }

    public function test_sekretaris_memegang_seluruh_siklus_surat(): void
    {
        $sekretaris = Matriks::untukPeran('sekretaris');

        foreach (['surat.buat', 'surat.ubah', 'surat.ubahIsi', 'surat.hapus',
            'surat.cetak', 'surat.finalisasi'] as $kapabilitas) {
            $this->assertContains($kapabilitas, $sekretaris);
        }
    }

    public function test_sekretaris_tidak_bisa_menyentuh_uang(): void
    {
        $sekretaris = Matriks::untukPeran('sekretaris');

        foreach ($sekretaris as $kapabilitas) {
            $this->assertFalse(
                str_ends_with($kapabilitas, '.catat') || str_ends_with($kapabilitas, '.tagih'),
                "Sekretaris tidak boleh punya kapabilitas uang: {$kapabilitas}"
            );
        }
        $this->assertNotContains('transaksi.void', $sekretaris);
    }

    public function test_bendahara_tidak_ikut_modul_surat_sama_sekali(): void
    {
        foreach (Matriks::untukPeran('bendahara') as $kapabilitas) {
            $this->assertStringStartsNotWith('surat.', $kapabilitas);
        }
    }

    public function test_bendahara_memegang_seluruh_pencatatan_uang(): void
    {
        $bendahara = Matriks::untukPeran('bendahara');

        foreach (['bukukas.catat', 'pengeluaran.catat', 'sumbangan.catat',
            'setor.catat', 'sampah.tagih', 'padaringan.tagih'] as $kapabilitas) {
            $this->assertContains($kapabilitas, $bendahara);
        }
        // Void adalah pengawasan, bukan pencatatan: pencatat tidak membatalkan
        // catatannya sendiri.
        $this->assertNotContains('transaksi.void', $bendahara);
    }

    public function test_petugas_rt_mendata_warga_tapi_tidak_impor_ekspor(): void
    {
        $rt = Matriks::untukPeran('petugas_rt');

        $this->assertContains('warga.kelola', $rt);
        // CSV memuat PII seluruh tenant, bukan hanya RT-nya sendiri.
        $this->assertNotContains('warga.impor', $rt);
        $this->assertNotContains('warga.ekspor', $rt);
    }

    public function test_petugas_rt_hanya_menagih_dan_menyetor(): void
    {
        $rt = Matriks::untukPeran('petugas_rt');

        $this->assertContains('sampah.tagih', $rt);
        $this->assertContains('setor.catat', $rt);
        $this->assertNotContains('bukukas.catat', $rt);
        $this->assertNotContains('pengeluaran.catat', $rt);
    }

    public function test_warga_hanya_mengajukan_surat_dan_aduan_miliknya(): void
    {
        $warga = Matriks::untukPeran('warga');
        sort($warga);

        $this->assertSame([
            'aduan.lapor',
            'aduan.lihat',
            'dashboard.lihat',
            'surat.ajukan',
            'surat.cetak',
            'surat.lihat',
        ], $warga);
    }

    public function test_peran_tak_dikenal_tidak_mendapat_apa_pun(): void
    {
        // Fail-closed, meniru userCan() yang sudah fail-closed sejak Phase E1.
        $this->assertSame([], Matriks::untukPeran('humas'));
    }

    public function test_setiap_key_bawaan_terdaftar_di_katalog(): void
    {
        foreach (Matriks::BAWAAN as $peran => $daftar) {
            foreach ($daftar as $kapabilitas) {
                $this->assertArrayHasKey(
                    $kapabilitas,
                    Matriks::KATALOG,
                    "Peran {$peran} memegang key yang tidak ada di katalog: {$kapabilitas}"
                );
            }
        }
    }

    public function test_setiap_key_katalog_dipegang_seseorang_atau_khusus_superadmin(): void
    {
        $dipegang = array_unique(array_merge(...array_values(Matriks::BAWAAN)));

        foreach (array_keys(Matriks::KATALOG) as $kapabilitas) {
            $this->assertTrue(
                in_array($kapabilitas, $dipegang, true)
                    || in_array($kapabilitas, Matriks::KHUSUS_SUPERADMIN, true),
                "Key yatim: {$kapabilitas} tidak dipegang peran mana pun dan tidak "
                .'terdaftar sebagai khusus superadmin'
            );
        }
    }

    public function test_modul_setiap_key_konsisten_dengan_prefiksnya(): void
    {
        foreach (Matriks::KATALOG as $kapabilitas => $meta) {
            $this->assertSame(
                explode('.', $kapabilitas)[0],
                $meta['modul'],
                "Prefiks dan modul tidak cocok untuk {$kapabilitas}"
            );
        }
    }

    public function test_urutan_tampil_memuat_semua_peran_bawaan(): void
    {
        foreach (array_keys(Matriks::BAWAAN) as $peran) {
            $this->assertArrayHasKey($peran, Matriks::URUTAN_TAMPIL);
        }
        // Superadmin harus yang tertinggi supaya labelnya menang saat merangkap.
        $this->assertSame(max(Matriks::URUTAN_TAMPIL), Matriks::URUTAN_TAMPIL['superadmin']);
    }
}
