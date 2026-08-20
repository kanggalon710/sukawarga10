<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rilis 1: identitas warga dijaga di batas masuk.
 *
 * Sebelum ini tidak ada satu pun validasi keunikan NIK/No.KK, bahkan di dalam
 * satu RW, dan nomor rusak dari spreadsheet masuk apa adanya. Dua-duanya
 * terbukti di RW 07 Bagendit: satu orang tercatat di dua KK, dan 14 KK punya
 * identitas yang digitnya sudah hilang.
 */
class IdentitasWargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function pengurus(): User
    {
        return $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sekre', 'username' => 'sekre',
            'namaLengkap' => 'Sekretaris Uji', 'pin' => Hash::make('123456'),
            'level' => 'sekretaris', 'status' => 'aktif', 'isDefault' => false,
        ]));
    }

    private function kk(array $ubah = []): Keluarga
    {
        return Keluarga::create(array_merge([
            'keluarga_id' => 'kk_'.uniqid(),
            'nama' => 'Bapak Awal',
            'alamat' => 'Kp. Uji',
            'rt' => '01',
            'noKK' => '9999990112200015',
            'nik' => '9999995007610005',
        ], $ubah));
    }

    private function tambahKk(array $isi): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->pengurus())->post('/warga', array_merge([
            'nama' => 'Bapak Baru', 'rt' => '01',
            'alamat' => 'Kp. Uji', 'jumlahAnggota' => 1,
        ], $isi));
    }

    // --- bentuk identitas -------------------------------------------------

    public function test_nik_notasi_ilmiah_ditolak(): void
    {
        $this->tambahKk(['nik' => '9.99999170419858E+018'])
            ->assertSessionHasErrors('nik');

        $this->assertDatabaseMissing('keluargas', ['nama' => 'Bapak Baru']);
    }

    public function test_nik_kurang_dari_16_digit_ditolak(): void
    {
        $this->tambahKk(['nik' => '999999200370001'])->assertSessionHasErrors('nik');
    }

    public function test_no_kk_17_digit_ditolak(): void
    {
        $this->tambahKk(['noKK' => '99999931907360006'])->assertSessionHasErrors('noKK');
    }

    public function test_identitas_kosong_tetap_boleh(): void
    {
        // Pendataan lapangan bertahap: menolak KK tanpa NIK hanya memindahkan
        // masalah ke petugas RT.
        $this->tambahKk(['nik' => '', 'noKK' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('keluargas', ['nama' => 'Bapak Baru']);
    }

    public function test_spasi_dan_tanda_hubung_dibersihkan_saat_disimpan(): void
    {
        $this->tambahKk(['nik' => '9999-9901-1220-0015'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('keluargas', ['nama' => 'Bapak Baru', 'nik' => '9999990112200015']);
    }

    // --- duplikat dalam satu tenant ---------------------------------------

    public function test_nik_yang_sudah_dipakai_kepala_keluarga_lain_ditolak(): void
    {
        $this->kk(['nama' => 'Bapak Awal', 'nik' => '9999990303700005']);

        $this->tambahKk(['nik' => '9999990303700005'])->assertSessionHasErrors('nik');
    }

    public function test_pesan_duplikat_menyebut_pemiliknya(): void
    {
        $this->kk(['nama' => 'Bapak Awal', 'nik' => '9999990303700005']);

        $this->tambahKk(['nik' => '9999990303700005']);

        $this->assertStringContainsString('Bapak Awal', session('errors')->first('nik'));
    }

    public function test_nik_yang_sudah_dipakai_anggota_keluarga_lain_ditolak(): void
    {
        // Bentuk duplikat yang paling sering: satu orang tercatat sebagai istri
        // di satu KK, lalu didaftarkan lagi sebagai kepala keluarga baru.
        $kk = $this->kk();
        Anggota::create([
            'anggota_id' => 'ag_uji1', 'keluarga_id' => $kk->keluarga_id,
            'nama' => 'Ibu Uji', 'jenisKelamin' => 'P', 'statusKeluarga' => 'Istri',
            'nik' => '9999996707630001',
        ]);

        $this->tambahKk(['nik' => '9999996707630001'])->assertSessionHasErrors('nik');
    }

    public function test_no_kk_kembar_ditolak(): void
    {
        $this->kk(['noKK' => '9999990112200015']);

        $this->tambahKk(['noKK' => '9999990112200015'])->assertSessionHasErrors('noKK');
    }

    // --- menyunting data lama yang sudah telanjur rusak --------------------

    public function test_kk_beridentitas_rusak_tetap_bisa_disunting_untuk_kolom_lain(): void
    {
        // 14 KK RW 07 punya identitas rusak. Kalau validasi berlaku untuk nilai
        // yang TIDAK diubah, mereka jadi tidak bisa dibetulkan RT-nya sama sekali.
        $kk = $this->kk(['nik' => '9.99999170419858E+018', 'rt' => '01']);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", [
            'nama' => $kk->nama, 'rt' => '03', 'alamat' => $kk->alamat,
            'nik' => '9.99999170419858E+018',   // tidak diubah
            'noKK' => $kk->noKK,
        ])->assertSessionHasNoErrors();

        $this->assertSame('03', $kk->fresh()->rt);
    }

    public function test_membetulkan_identitas_rusak_jadi_16_digit_diterima(): void
    {
        $kk = $this->kk(['nik' => '9.99999170419858E+018']);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", [
            'nama' => $kk->nama, 'rt' => $kk->rt, 'alamat' => $kk->alamat,
            'nik' => '9999991704850003', 'noKK' => $kk->noKK,
        ])->assertSessionHasNoErrors();

        $this->assertSame('9999991704850003', $kk->fresh()->nik);
    }

    public function test_identitas_dibersihkan_juga_saat_menyunting(): void
    {
        $kk = $this->kk(['nik' => null]);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", [
            'nama' => $kk->nama, 'rt' => $kk->rt, 'alamat' => $kk->alamat,
            'nik' => '9999 9903 0370 0005', 'noKK' => $kk->noKK,
        ])->assertSessionHasNoErrors();

        $this->assertSame('9999990303700005', $kk->fresh()->nik);
    }

    // --- pintu belakang status 'pindah' -----------------------------------

    public function test_status_pindah_tidak_bisa_dipasang_lewat_form_ubah_kk(): void
    {
        // 'pindah' hanya boleh dipasang lewat alur pemindahan warga yang butuh
        // persetujuan pengelola desa. Tanpa penjagaan ini seluruh alur itu bisa
        // dilewati dari halaman Ubah KK.
        $kk = $this->kk();

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", [
            'nama' => $kk->nama, 'rt' => $kk->rt, 'alamat' => $kk->alamat,
            'status' => 'pindah',
        ])->assertSessionHasErrors('status');

        $this->assertSame('aktif', $kk->fresh()->status);
    }

    public function test_status_nonaktif_tetap_boleh(): void
    {
        $kk = $this->kk();

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", [
            'nama' => $kk->nama, 'rt' => $kk->rt, 'alamat' => $kk->alamat,
            'status' => 'nonaktif',
        ])->assertSessionHasNoErrors();

        $this->assertSame('nonaktif', $kk->fresh()->status);
    }

    // --- hapus KK tidak boleh memutus riwayat uang -------------------------

    public function test_kk_yang_masih_dirujuk_transaksi_tidak_bisa_dihapus(): void
    {
        // `transaksis.refKeluargaId` menyimpan id NUMERIK, dan destroy() hanya
        // cascade ke anggotas. Tanpa penjagaan ini riwayat kas jadi yatim tanpa
        // satu pun error muncul.
        $kk = $this->kk();
        $trx = \App\Models\Transaksi::create([
            'transaksi_id' => 'TRX-uji1', 'tanggal' => '2026-08-01',
            'jenis' => 'masuk', 'kas' => 'sampah', 'kategori' => 'Iuran Sampah',
            'keterangan' => 'Iuran uji', 'jumlah' => 5000, 'operator' => 'sekre',
        ]);
        $trx->forceFill(['refKeluargaId' => $kk->id])->save();

        $this->actingAs($this->pengurus())->delete("/warga/{$kk->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('keluargas', ['id' => $kk->id]);
    }

    public function test_kk_tanpa_jejak_uang_tetap_bisa_dihapus(): void
    {
        $kk = $this->kk();

        $this->actingAs($this->pengurus())->delete("/warga/{$kk->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('keluargas', ['id' => $kk->id]);
    }

    public function test_kk_berstatus_pindah_dilewati_hapus_duplikat(): void
    {
        // Keluarga yang pindah lalu kembali punya nama+RT sama dengan arsipnya.
        // Tanpa pengecualian, yang dihapus justru baris yang aktif.
        $arsip = $this->kk(['nama' => 'Bapak Pulang', 'noKK' => null, 'nik' => null]);
        $arsip->forceFill(['status' => 'pindah'])->save();
        $aktif = $this->kk(['nama' => 'Bapak Pulang', 'noKK' => null, 'nik' => null]);

        $superadmin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sa', 'username' => 'sa', 'namaLengkap' => 'Super',
            'pin' => Hash::make('123456'), 'level' => 'superadmin',
            'status' => 'aktif', 'isDefault' => false,
        ]));

        $this->actingAs($superadmin)->post('/pengaturan/remove-duplicates');

        $this->assertDatabaseHas('keluargas', ['id' => $aktif->id]);
        $this->assertDatabaseHas('keluargas', ['id' => $arsip->id]);
    }

    public function test_dropdown_status_tidak_lagi_menawarkan_pindah(): void
    {
        $kk = $this->kk();

        $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/edit")
            ->assertOk()
            ->assertDontSee('value="pindah"', false);
    }
}
