<?php

namespace Tests\Feature;

use App\Models\IuranPadaringan;
use App\Models\IuranSampah;
use App\Models\Keluarga;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regresi: halaman Iuran Sampah/Padaringan mengirim form ke
 * /sampah/bayar/{data-id}, dengan data-id = ID bisnis kk_..., padahal
 * controller mencari keluarga lewat id numerik. Tes lama memanggil rute
 * dengan id numerik langsung, jadi tidak pernah meniru form sungguhan.
 * Tes ini mengambil id persis seperti JavaScript halaman: dari data-id.
 */
class BayarIuranLewatHalamanTest extends TestCase
{
    use RefreshDatabase;

    private User $bendahara;

    private Keluarga $kk;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->bendahara = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_bayar_ui', 'username' => 'bayarui', 'namaLengkap' => 'Bendahara',
            'pin' => Hash::make('123456'), 'level' => 'bendahara', 'status' => 'aktif',
        ]));
        $this->kk = Keluarga::create([
            'keluarga_id' => 'kk_uiuji', 'nama' => 'KK Uji UI', 'alamat' => 'x', 'rt' => '01',
            'status' => 'aktif', 'ikutSampah' => true, 'ikutPadaringan' => true,
        ]);
    }

    private function dataIdDari(string $halaman): string
    {
        $html = $this->actingAs($this->bendahara)->get($halaman)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-id="([^"]+)" data-nama="KK Uji UI"/', $html);
        preg_match('/data-id="([^"]+)" data-nama="KK Uji UI"/', $html, $m);

        return $m[1];
    }

    public function test_bayar_sampah_memakai_id_dari_halaman(): void
    {
        $id = $this->dataIdDari('/sampah');

        $this->actingAs($this->bendahara)->post("/sampah/bayar/{$id}", [
            'tahun' => now()->year, 'bulan_key' => 'JAN', 'minggu' => [1, 2], 'tanggal_bayar' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, Transaksi::where('kas', 'sampah')->count());
        $this->assertSame('lunas', IuranSampah::where('keluarga_id', $this->kk->id)->first()->weeks['JAN-M1']);

        // Status pembayaran di halaman yang sama ikut menunjukkan lunas.
        $this->actingAs($this->bendahara)->get('/sampah?bulan=1')->assertOk()->assertViewHas('iuran',
            fn ($iuran) => isset($iuran[$this->kk->id]));
    }

    public function test_pembayaran_masuk_ranking_rt_di_laporan(): void
    {
        $id = $this->dataIdDari('/sampah');
        $this->actingAs($this->bendahara)->post("/sampah/bayar/{$id}", [
            'tahun' => now()->year, 'bulan_key' => 'JAN', 'minggu' => [1], 'tanggal_bayar' => now()->toDateString(),
        ]);

        $this->actingAs($this->bendahara)->get('/laporan/ringkasan')->assertOk()
            ->assertViewHas('rankingRT', fn ($r) => $r->contains(fn ($x) => $x->rt === '01' && $x->total > 0));
    }

    public function test_pesan_tidak_mengaku_wa_terkirim_bila_gagal(): void
    {
        // Tanpa kunci gateway: pengiriman gagal, pesan tidak boleh mengaku berhasil.
        $this->kk->update(['noHP' => '6281200000001']);

        $this->actingAs($this->bendahara)->post("/sampah/bayar/{$this->kk->id}", [
            'tahun' => now()->year, 'bulan_key' => 'JAN', 'minggu' => [1], 'tanggal_bayar' => now()->toDateString(),
        ])->assertSessionHas('success', fn ($pesan) => str_contains($pesan, 'tidak terkirim') && ! str_contains($pesan, 'Bukti dikirim'));
    }

    public function test_bayar_padaringan_memakai_id_dari_halaman(): void
    {
        $id = $this->dataIdDari('/padaringan');

        $this->actingAs($this->bendahara)->post("/padaringan/bayar/{$id}", [
            'tahun' => now()->year, 'bulans' => ['JAN'], 'tanggal_bayar' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, Transaksi::where('kas', 'padaringan')->count());
        $this->assertTrue((bool) IuranPadaringan::where('keluarga_id', $this->kk->id)->first()->months['JAN']);
    }
}
