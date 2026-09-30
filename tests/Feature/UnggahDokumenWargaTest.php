<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P0 unggahan dokumen warga: scan KK, foto rumah, dan dokumen PBB.
 *
 * Sebelum ini berkasnya masuk disk PUBLIK tanpa cek jenis maupun ukuran,
 * jadi siapa pun yang tahu/menebak namanya bisa membukanya dari /storage,
 * dan berkas .jpg berisi skrip diterima apa adanya. Sekarang semua lewat
 * PenyimpanBerkas (sniff isi, batas ukuran, kode ulang gambar, nama acak)
 * dan disajikan hanya lewat controller berizin.
 */
class UnggahDokumenWargaTest extends TestCase
{
    use RefreshDatabase;

    private Organization $rwAsing;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Storage::fake('local');
        Storage::fake('public');

        $this->rwAsing = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 98',
            'code' => 'RW98', 'slug' => 'rw-98-sukakarya',
        ]);
        Domain::create([
            'organization_id' => $this->rwAsing->id,
            'hostname' => 'rw98.desa.test', 'is_primary' => true,
        ]);
    }

    private function pengurus(): User
    {
        return $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sekre_up', 'username' => 'sekreup',
            'namaLengkap' => 'Sekretaris Unggah', 'pin' => Hash::make('123456'),
            'level' => 'sekretaris', 'status' => 'aktif', 'isDefault' => false,
        ]));
    }

    private function wargaPemilik(Keluarga $kk): User
    {
        return User::create([
            'user_id' => 'usr_w_'.uniqid(), 'username' => 'warga'.uniqid(),
            'namaLengkap' => 'Warga Pemilik', 'pin' => Hash::make('123456'),
            'level' => 'warga', 'status' => 'aktif', 'isDefault' => false,
            'keluarga_id' => $kk->keluarga_id,
        ]);
    }

    private function kk(array $ubah = []): Keluarga
    {
        return Keluarga::create(array_merge([
            'keluarga_id' => 'kk_'.uniqid(),
            'nama' => 'Bapak Unggah', 'alamat' => 'Kp. Uji', 'rt' => '01',
            // Eksplisit: dengan RW kedua, fallback "satu-satunya RW aktif" tidak berlaku.
            'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
        ], $ubah));
    }

    private function formKk(array $isi = []): array
    {
        return array_merge(['nama' => 'Bapak Baru', 'rt' => '01', 'alamat' => 'Kp. Uji', 'jumlahAnggota' => 1], $isi);
    }

    /** JPEG asli dengan segmen EXIF berisi penanda, untuk membuktikan metadata dibuang. */
    private function jpegBerExif(string $penanda = 'GPSRAHASIA-LOKASI'): UploadedFile
    {
        $gambar = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($gambar);
        $jpeg = ob_get_clean();

        $isi = "Exif\0\0".$penanda;
        $app1 = "\xFF\xE1".pack('n', strlen($isi) + 2).$isi;
        $jpeg = substr($jpeg, 0, 2).$app1.substr($jpeg, 2);

        return UploadedFile::fake()->createWithContent('rumah-bapak-budi.jpg', $jpeg);
    }

    private function pdf(int $kb = 10): UploadedFile
    {
        $isi = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n".str_repeat('%', $kb * 1024)."\n%%EOF\n";

        return UploadedFile::fake()->createWithContent('pbb.pdf', $isi);
    }

    // --- validasi isi berkas ----------------------------------------------

    public function test_jenis_berkas_tidak_diizinkan_ditolak(): void
    {
        $zip = UploadedFile::fake()->createWithContent('kk.zip', "PK\x03\x04".str_repeat('x', 200));

        $this->actingAs($this->pengurus())->post('/warga', $this->formKk(['fotoKK' => $zip]))
            ->assertSessionHasErrors('fotoKK');

        $this->assertDatabaseMissing('keluargas', ['nama' => 'Bapak Baru']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_skrip_menyamar_sebagai_jpg_ditolak(): void
    {
        $palsu = UploadedFile::fake()->createWithContent('foto.jpg', '<?php system($_GET["c"]); ?>');

        $this->actingAs($this->pengurus())->post('/warga', $this->formKk(['fotoRumah' => $palsu]))
            ->assertSessionHasErrors('fotoRumah');

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_jpeg_rusak_berkepala_asli_ditolak(): void
    {
        // Lolos cek kepala berkas tapi tidak bisa didekode: tetap ditolak.
        $rusak = UploadedFile::fake()->createWithContent('foto.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\0", 300));

        $this->actingAs($this->pengurus())->post('/warga', $this->formKk(['fotoRumah' => $rusak]))
            ->assertSessionHasErrors('fotoRumah');
    }

    public function test_pdf_untuk_foto_rumah_ditolak(): void
    {
        $this->actingAs($this->pengurus())->post('/warga', $this->formKk(['fotoRumah' => $this->pdf()]))
            ->assertSessionHasErrors('fotoRumah');
    }

    public function test_berkas_melebihi_batas_ditolak(): void
    {
        // PDF asli 6 MB: jenisnya boleh, ukurannya melewati batas PDF 5 MB.
        $this->actingAs($this->pengurus())->post('/warga', $this->formKk(['dokumenPBB' => $this->pdf(6 * 1024)]))
            ->assertSessionHasErrors(['dokumenPBB' => 'Ukuran PDF maksimal 5 MB.']);

        $this->assertDatabaseMissing('keluargas', ['nama' => 'Bapak Baru']);
    }

    public function test_satu_berkas_gagal_tidak_meninggalkan_berkas_lain(): void
    {
        $palsu = UploadedFile::fake()->createWithContent('pbb.pdf', 'bukan pdf');

        $this->actingAs($this->pengurus())->post('/warga', $this->formKk([
            'fotoKK' => UploadedFile::fake()->image('kk.png', 50, 50),
            'dokumenPBB' => $palsu,
        ]))->assertSessionHasErrors('dokumenPBB');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // --- penyimpanan --------------------------------------------------------

    public function test_berkas_sah_disimpan_privat_nama_acak_tanpa_exif(): void
    {
        $this->actingAs($this->pengurus())->post('/warga', $this->formKk([
            'fotoRumah' => $this->jpegBerExif(),
            'dokumenPBB' => $this->pdf(),
        ]))->assertSessionHasNoErrors();

        $kk = Keluarga::where('nama', 'Bapak Baru')->firstOrFail();

        $this->assertMatchesRegularExpression('#^dokumen-warga/[0-9a-f-]{36}\.jpg$#', $kk->fotoRumah);
        $this->assertMatchesRegularExpression('#^dokumen-warga/[0-9a-f-]{36}\.pdf$#', $kk->dokumenPBB);
        $this->assertStringNotContainsString('budi', $kk->fotoRumah);

        Storage::disk('local')->assertExists([$kk->fotoRumah, $kk->dokumenPBB]);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'dokumen warga tidak boleh di disk publik');

        $isi = Storage::disk('local')->get($kk->fotoRumah);
        $this->assertStringNotContainsString('GPSRAHASIA', $isi, 'EXIF/GPS harus dibuang oleh kode ulang');
        $this->assertNotFalse(imagecreatefromstring($isi));
    }

    public function test_gambar_besar_diperkecil(): void
    {
        $this->actingAs($this->pengurus())->post('/warga', $this->formKk([
            'fotoKK' => UploadedFile::fake()->image('kk.png', 3000, 1000),
        ]))->assertSessionHasNoErrors();

        $kk = Keluarga::where('nama', 'Bapak Baru')->firstOrFail();
        [$lebar, $tinggi] = getimagesizefromstring(Storage::disk('local')->get($kk->fotoKK));
        $this->assertSame(2000, $lebar);
        $this->assertLessThanOrEqual(667, $tinggi);
    }

    public function test_gambar_ditampilkan_inline_dengan_sandbox(): void
    {
        $gambar = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($gambar);
        Storage::disk('local')->put('dokumen-warga/rumah.png', ob_get_clean());
        $kk = $this->kk(['fotoRumah' => 'dokumen-warga/rumah.png']);

        $res = $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/dokumen/fotoRumah");

        $res->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('inline', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
    }

    public function test_resolusi_berlebih_ditolak_dengan_pesan(): void
    {
        // Hanya header yang dibaca sebelum dekode, jadi PNG 6000x6000 kosong cukup.
        $this->actingAs($this->pengurus())->post('/warga', $this->formKk([
            'fotoRumah' => UploadedFile::fake()->image('besar.png', 6000, 6000),
        ]))->assertSessionHasErrors('fotoRumah');
    }

    public function test_ganti_berkas_menghapus_berkas_lama(): void
    {
        Storage::disk('local')->put('dokumen-warga/lama.jpg', 'x');
        $kk = $this->kk(['fotoRumah' => 'dokumen-warga/lama.jpg']);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", $this->formKk([
            'nama' => 'Bapak Unggah', 'fotoRumah' => UploadedFile::fake()->image('baru.jpg', 30, 30),
        ]))->assertSessionHasNoErrors();

        $kk->refresh();
        $this->assertNotSame('dokumen-warga/lama.jpg', $kk->fotoRumah);
        Storage::disk('local')->assertMissing('dokumen-warga/lama.jpg');
        Storage::disk('local')->assertExists($kk->fotoRumah);
    }

    public function test_ganti_berkas_lama_di_disk_publik_ikut_dihapus(): void
    {
        Storage::disk('public')->put('dokumen/warisan.jpg', 'x');
        $kk = $this->kk(['fotoKK' => 'dokumen/warisan.jpg']);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", $this->formKk([
            'nama' => 'Bapak Unggah', 'fotoKK' => UploadedFile::fake()->image('baru.jpg', 30, 30),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing('dokumen/warisan.jpg');
    }

    public function test_unggahan_gagal_tidak_menghapus_berkas_lama(): void
    {
        Storage::disk('local')->put('dokumen-warga/lama.jpg', 'x');
        $kk = $this->kk(['fotoRumah' => 'dokumen-warga/lama.jpg']);

        $this->actingAs($this->pengurus())->put("/warga/{$kk->id}", $this->formKk([
            'nama' => 'Bapak Unggah',
            'fotoRumah' => UploadedFile::fake()->createWithContent('x.jpg', '<script>alert(1)</script>'),
        ]))->assertSessionHasErrors('fotoRumah');

        $this->assertSame('dokumen-warga/lama.jpg', $kk->fresh()->fotoRumah);
        Storage::disk('local')->assertExists('dokumen-warga/lama.jpg');
    }

    public function test_warga_mengganti_berkas_lewat_profil(): void
    {
        Storage::disk('local')->put('dokumen-warga/lama.pdf', '%PDF-1.4');
        $kk = $this->kk(['dokumenPBB' => 'dokumen-warga/lama.pdf']);

        $this->actingAs($this->wargaPemilik($kk))->put('/profil', [
            'dokumenPBB' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        $kk->refresh();
        $this->assertStringStartsWith('dokumen-warga/', $kk->dokumenPBB);
        Storage::disk('local')->assertMissing('dokumen-warga/lama.pdf');
        Storage::disk('local')->assertExists($kk->dokumenPBB);
    }

    public function test_profil_menolak_berkas_menyamar(): void
    {
        $kk = $this->kk();

        $this->actingAs($this->wargaPemilik($kk))->put('/profil', [
            'fotoKK' => UploadedFile::fake()->createWithContent('kk.pdf', '<html><script>x</script></html>'),
        ])->assertSessionHasErrors('fotoKK');

        $this->assertNull($kk->fresh()->fotoKK);
    }

    // --- penghapusan ---------------------------------------------------------

    public function test_hapus_kk_menghapus_berkasnya(): void
    {
        Storage::disk('local')->put('dokumen-warga/a.jpg', 'x');
        Storage::disk('public')->put('dokumen/b.pdf', 'x');
        $kk = $this->kk(['fotoKK' => 'dokumen-warga/a.jpg', 'dokumenPBB' => 'dokumen/b.pdf']);

        $this->actingAs($this->pengurus())->delete("/warga/{$kk->id}");

        $this->assertDatabaseMissing('keluargas', ['id' => $kk->id]);
        Storage::disk('local')->assertMissing('dokumen-warga/a.jpg');
        Storage::disk('public')->assertMissing('dokumen/b.pdf');
    }

    public function test_hapus_duplikat_menghapus_berkas_kk_duplikat_saja(): void
    {
        Storage::disk('local')->put('dokumen-warga/asli.jpg', 'x');
        Storage::disk('local')->put('dokumen-warga/dup.jpg', 'x');
        $this->kk(['nama' => 'Kembar', 'fotoKK' => 'dokumen-warga/asli.jpg']);
        $this->kk(['nama' => 'Kembar', 'fotoKK' => 'dokumen-warga/dup.jpg']);

        $admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sa_up', 'username' => 'saup', 'namaLengkap' => 'SA',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
        $this->actingAs($admin)->post('/pengaturan/remove-duplicates')->assertSessionHas('success');

        Storage::disk('local')->assertExists('dokumen-warga/asli.jpg');
        Storage::disk('local')->assertMissing('dokumen-warga/dup.jpg');
    }

    public function test_hapus_duplikat_memindahkan_dokumen_ke_baris_yang_dipertahankan(): void
    {
        Storage::disk('local')->put('dokumen-warga/scan-dup.jpg', 'x');
        $asli = $this->kk(['nama' => 'Kembar2']);
        $this->kk(['nama' => 'Kembar2', 'fotoKK' => 'dokumen-warga/scan-dup.jpg']);

        $admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sa_pd', 'username' => 'sapd', 'namaLengkap' => 'SA',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
        $this->actingAs($admin)->post('/pengaturan/remove-duplicates')->assertSessionHas('success');

        $this->assertSame('dokumen-warga/scan-dup.jpg', $asli->fresh()->fotoKK);
        Storage::disk('local')->assertExists('dokumen-warga/scan-dup.jpg');
    }

    public function test_reset_data_menghapus_berkas_tenant_ini_saja(): void
    {
        Storage::disk('local')->put('dokumen-warga/sini.jpg', 'x');
        Storage::disk('local')->put('dokumen-warga/sana.jpg', 'x');
        $this->kk(['fotoKK' => 'dokumen-warga/sini.jpg']);
        $this->kk(['fotoKK' => 'dokumen-warga/sana.jpg', 'organization_id' => $this->rwAsing->id]);

        $admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sa_rs', 'username' => 'sars', 'namaLengkap' => 'SA',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
        $this->actingAs($admin)->post('/pengaturan/reset-data', ['confirm' => 'RESET'])->assertSessionHas('success');

        Storage::disk('local')->assertMissing('dokumen-warga/sini.jpg');
        Storage::disk('local')->assertExists('dokumen-warga/sana.jpg');
    }

    // --- penyajian berizin ---------------------------------------------------

    public function test_pengurus_membuka_dokumen_lewat_controller(): void
    {
        Storage::disk('local')->put('dokumen-warga/kk.pdf', "%PDF-1.4\n%%EOF");
        $kk = $this->kk(['fotoKK' => 'dokumen-warga/kk.pdf']);

        $res = $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/dokumen/fotoKK");

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        // PDF diunduh, bukan inline: penampil PDF tidak jalan di bawah sandbox.
        $this->assertStringStartsWith('attachment', $res->headers->get('Content-Disposition'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_dokumen_warisan_di_disk_publik_tetap_bisa_dibuka_pengurus(): void
    {
        Storage::disk('public')->put('dokumen/lama.pdf', "%PDF-1.4\n%%EOF");
        $kk = $this->kk(['dokumenPBB' => 'dokumen/lama.pdf']);

        $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/dokumen/dokumenPBB")->assertOk();
    }

    public function test_tamu_tidak_bisa_membuka_dokumen(): void
    {
        Storage::disk('local')->put('dokumen-warga/kk.pdf', '%PDF-1.4');
        $kk = $this->kk(['fotoKK' => 'dokumen-warga/kk.pdf']);

        $this->get("/warga/{$kk->id}/dokumen/fotoKK")->assertRedirect('/login');
        $this->get('/profil/dokumen/fotoKK')->assertRedirect('/login');
    }

    public function test_warga_tidak_bisa_membuka_dokumen_lewat_rute_pengurus(): void
    {
        Storage::disk('local')->put('dokumen-warga/kk.pdf', '%PDF-1.4');
        $kk = $this->kk(['fotoKK' => 'dokumen-warga/kk.pdf']);

        $this->actingAs($this->wargaPemilik($kk))->get("/warga/{$kk->id}/dokumen/fotoKK")->assertForbidden();
    }

    public function test_warga_hanya_membuka_dokumen_miliknya(): void
    {
        Storage::disk('local')->put('dokumen-warga/milik.pdf', "%PDF-1.4\n%%EOF");
        Storage::disk('local')->put('dokumen-warga/orang.pdf', "%PDF-1.4\n%%EOF");
        $milik = $this->kk(['fotoKK' => 'dokumen-warga/milik.pdf']);
        $this->kk(['fotoKK' => 'dokumen-warga/orang.pdf']);

        $res = $this->actingAs($this->wargaPemilik($milik))->get('/profil/dokumen/fotoKK');
        $res->assertOk();
        $this->assertSame("%PDF-1.4\n%%EOF", $res->streamedContent());
    }

    public function test_dokumen_tenant_lain_404(): void
    {
        Storage::disk('local')->put('dokumen-warga/asing.pdf', '%PDF-1.4');
        $asing = $this->kk(['fotoKK' => 'dokumen-warga/asing.pdf', 'organization_id' => $this->rwAsing->id]);

        $this->actingAs($this->pengurus())->get("/warga/{$asing->id}/dokumen/fotoKK")->assertNotFound();
    }

    public function test_jenis_dokumen_di_luar_daftar_404(): void
    {
        $kk = $this->kk(['nama' => 'X']);

        $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/dokumen/nama")->assertNotFound();
    }

    public function test_path_traversal_di_kolom_tidak_disajikan(): void
    {
        $kk = $this->kk(['fotoKK' => '../../.env']);

        $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/dokumen/fotoKK")->assertNotFound();
    }

    public function test_halaman_edit_tidak_menautkan_storage_publik(): void
    {
        Storage::disk('local')->put('dokumen-warga/kk.jpg', 'x');
        $kk = $this->kk(['fotoKK' => 'dokumen-warga/kk.jpg']);

        $this->actingAs($this->pengurus())->get("/warga/{$kk->id}/edit")
            ->assertOk()
            ->assertDontSee('storage/dokumen', false)
            ->assertSee("/warga/{$kk->id}/dokumen/fotoKK", false);
    }

    // --- migrasi penyimpanan & berkas yatim ----------------------------------

    public function test_salinan_ganda_identik_publik_dihapus_berbeda_dilaporkan(): void
    {
        Storage::disk('public')->put('dokumen/sama.jpg', 'isi');
        Storage::disk('local')->put('dokumen/sama.jpg', 'isi');
        Storage::disk('public')->put('dokumen/beda.jpg', 'versi-a');
        Storage::disk('local')->put('dokumen/beda.jpg', 'versi-b');
        $this->kk(['fotoKK' => 'dokumen/sama.jpg', 'fotoRumah' => 'dokumen/beda.jpg']);

        $this->artisan('dokumen:amankan', ['--jalankan' => true])
            ->expectsOutputToContain('BERBEDA')
            ->assertFailed();

        Storage::disk('public')->assertMissing('dokumen/sama.jpg');
        Storage::disk('public')->assertExists('dokumen/beda.jpg');
        $this->assertSame('versi-b', Storage::disk('local')->get('dokumen/beda.jpg'));
    }

    public function test_migrasi_memindahkan_dan_rollback_mengembalikan(): void
    {
        Storage::disk('public')->put('dokumen/lama.pdf', '%PDF-1.4');
        $this->kk(['dokumenPBB' => 'dokumen/lama.pdf']);
        $migrasi = require database_path('migrations/2026_09_30_000003_pindahkan_dokumen_warga_ke_disk_privat.php');

        ob_start();
        $migrasi->up();
        ob_end_clean();
        Storage::disk('public')->assertMissing('dokumen/lama.pdf');
        Storage::disk('local')->assertExists('dokumen/lama.pdf');

        ob_start();
        $migrasi->down();
        ob_end_clean();
        Storage::disk('public')->assertExists('dokumen/lama.pdf');
        Storage::disk('local')->assertMissing('dokumen/lama.pdf');
    }

    public function test_perintah_amankan_memindahkan_berkas_warisan_dan_membersihkan_yatim(): void
    {
        Storage::disk('public')->put('dokumen/lama.jpg', 'isi-lama');
        Storage::disk('public')->put('dokumen/yatim.jpg', 'tak-dirujuk');
        Storage::disk('local')->put('dokumen-warga/yatim2.pdf', 'tak-dirujuk');
        $kk = $this->kk(['fotoKK' => 'dokumen/lama.jpg']);

        // Tanpa --jalankan: hanya laporan, tidak ada yang berubah.
        $this->artisan('dokumen:amankan')->assertSuccessful();
        Storage::disk('public')->assertExists('dokumen/lama.jpg');

        $this->artisan('dokumen:amankan', ['--jalankan' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('dokumen/lama.jpg');
        $this->assertSame('isi-lama', Storage::disk('local')->get('dokumen/lama.jpg'));
        $this->assertSame('dokumen/lama.jpg', $kk->fresh()->fotoKK);
        Storage::disk('public')->assertExists('dokumen/yatim.jpg');

        $this->artisan('dokumen:amankan', ['--jalankan' => true, '--hapus-yatim' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('dokumen/yatim.jpg');
        Storage::disk('local')->assertMissing('dokumen-warga/yatim2.pdf');
        Storage::disk('local')->assertExists('dokumen/lama.jpg');
    }
}
