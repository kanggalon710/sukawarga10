<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Keluarga;
use App\Models\Pendaftaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P0 pendaftaran warga publik: tanpa pembatasan laju dan dengan jawaban
 * berbeda ("NIK sudah terdaftar" / "sudah ada pengajuan"), halaman ini bisa
 * dipakai menebak siapa yang terdaftar di sebuah RW. Sekarang jawabannya
 * seragam dan WA tanda terima ikut terkirim di semua kasus, supaya tidak
 * ada saluran samping.
 */
class PendaftaranPublikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDefer();
        AppSetting::simpanRahasia('mpwa_api_key', 'kunci-uji-daftar');
        AppSetting::simpan('mpwa_sender', '6280000000001');
        Http::fake(['*' => Http::response(['status' => true])]);
    }

    private function daftar(string $nik, string $ip = '10.9.0.1', array $ubah = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/login')->post('/login/register', array_merge([
            'nik' => $nik, 'nama_lengkap' => 'Calon Warga', 'rt' => '01', 'no_wa' => '081200000001',
        ], $ubah));
    }

    public function test_jawaban_seragam_untuk_baru_terdaftar_dan_menunggu(): void
    {
        Keluarga::create([
            'keluarga_id' => 'kk_ada', 'nama' => 'Sudah Ada', 'alamat' => 'x', 'rt' => '01',
            'nik' => '9999991111110001',
        ]);

        $baru = $this->daftar('9999991111110002', '10.9.0.1');
        $pesanBaru = session('success_register');
        $sudahWarga = $this->daftar('9999991111110001', '10.9.0.2');
        $pesanWarga = session('success_register');
        $menunggu = $this->daftar('9999991111110002', '10.9.0.3');
        $pesanMenunggu = session('success_register');

        foreach ([$baru, $sudahWarga, $menunggu] as $r) {
            $r->assertRedirect('/login')->assertSessionMissing('error_register');
        }
        $this->assertNotEmpty($pesanBaru);
        $this->assertSame($pesanBaru, $pesanWarga);
        $this->assertSame($pesanBaru, $pesanMenunggu);

        // Hanya pengajuan yang benar-benar baru yang dicatat.
        $this->assertSame(1, Pendaftaran::count());
    }

    public function test_tanda_terima_wa_terkirim_juga_untuk_nik_terdaftar(): void
    {
        Keluarga::create([
            'keluarga_id' => 'kk_ada2', 'nama' => 'Sudah Ada', 'alamat' => 'x', 'rt' => '01',
            'nik' => '9999991111110003',
        ]);

        $this->daftar('9999991111110003');

        Http::assertSentCount(1);
    }

    public function test_dibatasi_per_ip(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->daftar('999999222222000'.$i, '10.9.1.1')->assertSessionMissing('error_register');
        }

        $this->daftar('9999992222220009', '10.9.1.1')->assertSessionHas('error_register');
        $this->assertSame(5, Pendaftaran::count());
    }

    public function test_dibatasi_per_nik_lintas_ip(): void
    {
        foreach (['10.9.2.1', '10.9.2.2', '10.9.2.3'] as $ip) {
            $this->daftar('9999993333330001', $ip)->assertSessionMissing('error_register');
        }

        $this->daftar('9999993333330001', '10.9.2.4')->assertSessionHas('error_register');
    }

    public function test_pesan_publik_tidak_menyebut_status_nik(): void
    {
        $this->daftar('9999994444440001');

        $pesan = strtolower((string) session('success_register'));
        $this->assertStringNotContainsString('sudah terdaftar sebagai warga', $pesan);
        $this->assertStringNotContainsString('menunggu verifikasi. mohon', $pesan);
    }
}
