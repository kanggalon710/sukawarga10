<?php

namespace Tests\Feature;

use App\Models\AppSetting;
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
 * Jalur uang: pencatatan iuran dan pembatalannya.
 *
 * Ada karena seluruh Transaksi::create() sempat gagal diam-diam — $fillable
 * model tidak cocok dengan skema tabel, sehingga transaksi_id & kas dibuang
 * dan INSERT melanggar NOT NULL. Tes ini mengunci perilaku itu.
 */
class PencatatanIuranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // jangan pernah memanggil gateway WA sungguhan dari tes
        AppSetting::create(['key' => 'tarif_sampah', 'value' => '5000']);
        AppSetting::create(['key' => 'tarif_padaringan', 'value' => '15000']);
    }

    private function pengurus(string $level = 'superadmin'): User
    {
        return User::create([
            'user_id' => 'usr_'.$level,
            'username' => $level,
            'namaLengkap' => 'Pengurus '.$level,
            'pin' => Hash::make('123456'),
            'level' => $level,
            'status' => 'aktif',
            'isDefault' => false,
        ]);
    }

    private function keluarga(array $atribut = []): Keluarga
    {
        return Keluarga::create(array_merge([
            'keluarga_id' => 'kk_uji_1',
            'nama' => 'Asep Suhendar',
            'rt' => '01',
            'alamat' => 'Jl. Contoh No. 1',
            'jumlahAnggota' => 3,
            'status' => 'aktif',
            'ikutSampah' => true,
            'ikutPadaringan' => true,
        ], $atribut));
    }

    public function test_pembayaran_iuran_sampah_tersimpan_dengan_kolom_lengkap(): void
    {
        $kk = $this->keluarga();

        $this->actingAs($this->pengurus())
            ->post('/sampah/bayar/'.$kk->id, [
                'tahun' => 2026,
                'bulan_key' => 'AGU',
                'minggu' => [1, 2],
                'tanggal_bayar' => '2026-08-15',
            ])
            ->assertRedirect();

        $trx = Transaksi::first();

        $this->assertNotNull($trx, 'Transaksi iuran sampah tidak tersimpan.');
        $this->assertNotEmpty($trx->transaksi_id, 'transaksi_id ikut terbuang oleh mass assignment.');
        $this->assertSame('sampah', $trx->kas, 'kas ikut terbuang oleh mass assignment.');
        $this->assertSame('masuk', $trx->jenis);
        $this->assertSame(10000, $trx->jumlah, '2 minggu x tarif 5000');
        $this->assertSame($kk->id, (int) $trx->refKeluargaId);
        $this->assertSame(['AGU-M1', 'AGU-M2'], $trx->periode);
        $this->assertFalse($trx->voided);

        $iuran = IuranSampah::where('keluarga_id', $kk->id)->first();
        $this->assertSame('lunas', $iuran->weeks['AGU-M1']);
        $this->assertSame('lunas', $iuran->weeks['AGU-M2']);
    }

    public function test_pembayaran_padaringan_tersimpan_dengan_kolom_lengkap(): void
    {
        $kk = $this->keluarga();

        $this->actingAs($this->pengurus())
            ->post('/padaringan/bayar/'.$kk->id, [
                'tahun' => 2026,
                'bulans' => ['AGU', 'SEP'],
                'tanggal_bayar' => '2026-08-15',
            ])
            ->assertRedirect();

        $trx = Transaksi::first();

        $this->assertNotNull($trx);
        $this->assertSame('padaringan', $trx->kas);
        $this->assertSame(30000, $trx->jumlah);
        $this->assertSame(['AGU', 'SEP'], $trx->periode);
    }

    public function test_void_menandai_transaksi_dan_membuka_kembali_periode_sampah(): void
    {
        $kk = $this->keluarga();
        $pengurus = $this->pengurus();

        $this->actingAs($pengurus)->post('/sampah/bayar/'.$kk->id, [
            'tahun' => date('Y'),
            'bulan_key' => 'AGU',
            'minggu' => [1, 2],
            'tanggal_bayar' => date('Y').'-08-15',
        ]);

        $trx = Transaksi::first();

        $this->actingAs($pengurus)
            ->post('/transaksi/'.$trx->id.'/void', ['void_reason' => 'Salah input petugas'])
            ->assertRedirect();

        $trx->refresh();

        // Kolom void sengaja tidak fillable; void harus tetap tersimpan lewat forceFill.
        $this->assertTrue($trx->voided, 'Void tidak tersimpan (update() tunduk pada $fillable).');
        $this->assertSame('Salah input petugas', $trx->void_reason);
        $this->assertSame($pengurus->username, $trx->void_by);

        $iuran = IuranSampah::where('keluarga_id', $kk->id)->first();
        $this->assertArrayNotHasKey('AGU-M1', $iuran->weeks ?? []);
        $this->assertArrayNotHasKey('AGU-M2', $iuran->weeks ?? []);
    }

    /**
     * Regresi: rollback lama memakai str_contains atas `keterangan`, sehingga
     * nama warga yang memuat kode bulan ikut cocok dan periode lain ikut dibatalkan.
     */
    public function test_void_padaringan_tidak_membatalkan_bulan_lain_karena_nama_warga(): void
    {
        // "MEIsari" memuat "MEI"; "AGUng" memuat "AGU".
        $kk = $this->keluarga(['nama' => 'Meisari Agung', 'keluarga_id' => 'kk_uji_2']);
        $pengurus = $this->pengurus();

        // Bayar SEP saja.
        $this->actingAs($pengurus)->post('/padaringan/bayar/'.$kk->id, [
            'tahun' => date('Y'),
            'bulans' => ['SEP'],
            'tanggal_bayar' => date('Y').'-09-01',
        ]);

        // Lalu bayar MEI dan AGU lewat transaksi terpisah.
        $this->actingAs($pengurus)->post('/padaringan/bayar/'.$kk->id, [
            'tahun' => date('Y'),
            'bulans' => ['MEI', 'AGU'],
            'tanggal_bayar' => date('Y').'-05-01',
        ]);

        $trxSep = Transaksi::whereJsonContains('periode', 'SEP')->first();

        $this->actingAs($pengurus)
            ->post('/transaksi/'.$trxSep->id.'/void', ['void_reason' => 'Dobel bayar']);

        $iuran = IuranPadaringan::where('keluarga_id', $kk->id)->first();

        $this->assertArrayNotHasKey('SEP', $iuran->months ?? [], 'SEP seharusnya dibatalkan.');
        $this->assertTrue((bool) ($iuran->months['MEI'] ?? false), 'MEI tidak boleh ikut dibatalkan.');
        $this->assertTrue((bool) ($iuran->months['AGU'] ?? false), 'AGU tidak boleh ikut dibatalkan.');
    }

    public function test_warga_tidak_boleh_membatalkan_transaksi(): void
    {
        $kk = $this->keluarga();
        $this->actingAs($this->pengurus())->post('/sampah/bayar/'.$kk->id, [
            'tahun' => 2026, 'bulan_key' => 'AGU', 'minggu' => [1], 'tanggal_bayar' => '2026-08-15',
        ]);

        $warga = $this->pengurus('warga');
        $trx = Transaksi::first();

        $this->actingAs($warga)
            ->post('/transaksi/'.$trx->id.'/void', ['void_reason' => 'Iseng saja'])
            ->assertForbidden();

        $this->assertFalse($trx->fresh()->voided);
    }
}
