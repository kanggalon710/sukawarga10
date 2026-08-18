<?php

namespace Tests\Feature;

use App\Models\Surat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Nomor surat bisa diedit bebas per surat oleh sekretaris (izin:surat.ubah).
 * Nomor yang sudah dipakai surat lain di tenant yang sama ditolak; nomorUrut
 * dan tahun sengaja tidak disentuh supaya sekuens otomatis max()+1 tetap
 * monoton; store() menghindari bentrok dengan nomor hasil edit manual.
 */
class SuratNomorEditTest extends TestCase
{
    use RefreshDatabase;

    private User $sekretaris;

    private User $warga;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->sekretaris = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_nom1', 'username' => 'sekretarisnomor', 'namaLengkap' => 'Sekretaris Nomor',
            'pin' => Hash::make('123456'), 'level' => 'sekretaris', 'status' => 'aktif',
        ]));
        $this->warga = User::create([
            'user_id' => 'u_nom2', 'username' => 'warganomor', 'namaLengkap' => 'Warga Nomor',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
        ]);
    }

    private function surat(array $extra = []): Surat
    {
        static $urut = 0;
        $urut++;

        return Surat::create(array_merge([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD',
            'tahun' => date('Y'),
            'nomorUrut' => $urut,
            'nomorSurat' => sprintf('%03d/SKD/RW10/%s', $urut, date('Y')),
            'tanggal' => now()->toDateString(),
            'pemohon' => 'Asep Suhendar',
            'keperluan' => 'Keperluan uji',
            'approval_step' => 'selesai',
            'status' => 'selesai',
        ], $extra));
    }

    private function payload(Surat $surat, array $ubah = []): array
    {
        return array_merge([
            'pemohon' => $surat->pemohon,
            'keperluan' => $surat->keperluan,
            'kodeSurat' => $surat->kodeSurat,
            'nomorSurat' => $surat->nomorSurat,
        ], $ubah);
    }

    public function test_pengurus_bisa_mengubah_nomor_surat(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$surat->id}", $this->payload($surat, [
            'nomorSurat' => '045/SKD/RW10/'.date('Y'),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('045/SKD/RW10/'.date('Y'), $surat->fresh()->nomorSurat);
    }

    public function test_nomor_milik_surat_lain_ditolak(): void
    {
        $suratA = $this->surat();
        $suratB = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$suratA->id}", $this->payload($suratA, [
            'nomorSurat' => $suratB->nomorSurat,
        ]))->assertSessionHasErrors('nomorSurat');

        $this->assertNotSame($suratB->nomorSurat, $suratA->fresh()->nomorSurat);
    }

    public function test_menyimpan_ulang_nomor_sendiri_tetap_sukses(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$surat->id}", $this->payload($surat))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($surat->nomorSurat, $surat->fresh()->nomorSurat);
    }

    public function test_nomor_sama_dengan_tenant_lain_tidak_ditolak(): void
    {
        $nomorAsing = '077/SKD/RW99/'.date('Y');
        Surat::withoutGlobalScopes()->create([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD', 'tahun' => date('Y'), 'nomorUrut' => 77,
            'nomorSurat' => $nomorAsing, 'tanggal' => now()->toDateString(),
            'pemohon' => 'Dede Kurnia', 'keperluan' => 'Keperluan uji',
            'approval_step' => 'selesai', 'status' => 'selesai',
            'organization_id' => 999999,
        ]);
        $surat = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$surat->id}", $this->payload($surat, [
            'nomorSurat' => $nomorAsing,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($nomorAsing, $surat->fresh()->nomorSurat);
    }

    public function test_edit_nomor_tidak_mengubah_nomor_urut_dan_tahun(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$surat->id}", $this->payload($surat, [
            'nomorSurat' => '100/SKD/RW10/'.date('Y'),
        ]))->assertRedirect();

        $segar = $surat->fresh();
        // Cast int: model tanpa $casts mengembalikan string dari driver SQLite.
        $this->assertSame((int) $surat->nomorUrut, (int) $segar->nomorUrut);
        $this->assertSame((int) $surat->tahun, (int) $segar->tahun);
    }

    public function test_warga_tidak_bisa_mengubah_surat(): void
    {
        $surat = $this->surat(['user_id' => $this->warga->id]);

        $this->actingAs($this->warga)->put("/surat/{$surat->id}", $this->payload($surat, [
            'nomorSurat' => '999/SKD/RW10/'.date('Y'),
        ]))->assertForbidden();
    }

    public function test_kode_surat_di_luar_daftar_ditolak(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->sekretaris)->put("/surat/{$surat->id}", $this->payload($surat, [
            'kodeSurat' => 'HACK',
        ]))->assertSessionHasErrors('kodeSurat');
    }

    public function test_nomor_otomatis_berikutnya_melompati_nomor_hasil_edit(): void
    {
        // Surat pertama otomatis 001; nomornya diedit manual menjadi 002 -
        // nomor yang akan dihasilkan surat otomatis berikutnya.
        $this->actingAs($this->sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji urut',
        ])->assertRedirect();
        $pertama = Surat::orderByDesc('id')->first();
        $nomorDiedit = sprintf('002/SKD/RW10/%s', date('Y'));

        $this->actingAs($this->sekretaris)->put("/surat/{$pertama->id}", $this->payload($pertama, [
            'nomorSurat' => $nomorDiedit,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Dede Kurnia', 'keperluan' => 'Uji urut',
        ])->assertRedirect();

        $kedua = Surat::orderByDesc('id')->first();
        $this->assertNotSame($nomorDiedit, $kedua->nomorSurat);
        $this->assertSame(sprintf('003/SKD/RW10/%s', date('Y')), $kedua->nomorSurat);
    }
}
