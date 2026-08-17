<?php

namespace Tests\Feature;

use App\Models\Surat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Edit isi surat ala Word (kolom surats.isi_kustom): pengurus (ketua RW ke
 * atas) menyunting badan surat + baris tanggal lewat editor, hasilnya
 * disanitasi di server lalu dirender apa adanya di halaman cetak. Kop,
 * nomor surat, dan blok tanda tangan tetap otomatis.
 */
class SuratIsiKustomTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $warga;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->ketua = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_isik1', 'username' => 'ketuaisi', 'namaLengkap' => 'Ketua Isi',
            'pin' => Hash::make('123456'), 'level' => 'ketua_rw', 'status' => 'aktif',
        ]));
        $this->warga = User::create([
            'user_id' => 'u_isik2', 'username' => 'wargaisi', 'namaLengkap' => 'Warga Isi',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
        ]);
    }

    private function surat(array $extra = []): Surat
    {
        return Surat::create(array_merge([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD',
            'tahun' => date('Y'),
            'nomorUrut' => 1,
            'nomorSurat' => '001/SKD/RW10/'.date('Y'),
            'tanggal' => now()->toDateString(),
            'pemohon' => 'Asep Suhendar',
            'keperluan' => 'Keperluan uji',
            'approval_step' => 'selesai',
            'status' => 'selesai',
        ], $extra));
    }

    public function test_pengurus_bisa_menyimpan_isi_kustom(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->ketua)->put("/surat/{$surat->id}/isi", [
            'isi' => '<p>Teks kustom hasil suntingan</p>',
        ])->assertRedirect(route('surat.cetak', $surat->id));

        $this->assertSame(
            '<p>Teks kustom hasil suntingan</p>',
            $surat->fresh()->isi_kustom
        );
    }

    public function test_isi_kustom_tampil_di_halaman_cetak(): void
    {
        $surat = $this->surat(['isi_kustom' => '<p>Paragraf hasil edit manual</p>']);

        $this->actingAs($this->ketua)->get("/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Paragraf hasil edit manual')
            ->assertDontSee('Yang bertanda tangan di bawah ini');
    }

    public function test_pengurus_melihat_tombol_edit_di_halaman_cetak(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->ketua)->get("/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Edit Isi');
    }

    public function test_isi_kustom_disanitasi_script_dan_atribut_event(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->ketua)->put("/surat/{$surat->id}/isi", [
            'isi' => '<p>aman</p><script>alert(1)</script><p onclick="x()">b</p><iframe src="x"></iframe>',
        ])->assertRedirect();

        $tersimpan = $surat->fresh()->isi_kustom;
        $this->assertStringContainsString('aman', $tersimpan);
        $this->assertStringNotContainsString('<script', $tersimpan);
        $this->assertStringNotContainsString('onclick', $tersimpan);
        $this->assertStringNotContainsString('<iframe', $tersimpan);
    }

    public function test_gaya_yang_diizinkan_dipertahankan(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->ketua)->put("/surat/{$surat->id}/isi", [
            'isi' => '<p class="ql-align-center"><span style="font-size: 18px;">Teks tengah</span></p>'
                .'<table class="data-diri"><tbody><tr><td>Nama</td><td>: <strong>Asep</strong></td></tr></tbody></table>',
        ])->assertRedirect();

        $tersimpan = $surat->fresh()->isi_kustom;
        $this->assertStringContainsString('ql-align-center', $tersimpan);
        $this->assertStringContainsString('font-size', $tersimpan);
        $this->assertStringContainsString('data-diri', $tersimpan);
        $this->assertStringContainsString('<td>Nama</td>', $tersimpan);
    }

    public function test_warga_tidak_bisa_menyimpan_isi_kustom(): void
    {
        $surat = $this->surat(['user_id' => $this->warga->id]);

        $this->actingAs($this->warga)->put("/surat/{$surat->id}/isi", [
            'isi' => '<p>coba ubah</p>',
        ])->assertForbidden();
    }

    public function test_warga_melihat_isi_kustom_miliknya_tanpa_tombol_edit(): void
    {
        $surat = $this->surat([
            'user_id' => $this->warga->id,
            'isi_kustom' => '<p>Isi khusus warga</p>',
        ]);

        $this->actingAs($this->warga)->get("/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Isi khusus warga')
            ->assertDontSee('Edit Isi');
    }

    public function test_kembalikan_ke_template_menghapus_isi_kustom(): void
    {
        $surat = $this->surat(['isi_kustom' => '<p>Isi lama</p>']);

        $this->actingAs($this->ketua)->put("/surat/{$surat->id}/isi", ['reset' => 1])
            ->assertRedirect(route('surat.cetak', $surat->id));

        $this->assertNull($surat->fresh()->isi_kustom);
        $this->actingAs($this->ketua)->get("/surat/{$surat->id}/cetak")
            ->assertSee('Yang bertanda tangan di bawah ini');
    }
}
