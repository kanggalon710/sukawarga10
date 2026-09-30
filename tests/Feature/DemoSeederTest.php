<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Surat;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\MpwaService;
use App\Services\PembukaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Instalasi demo (demo-sukawarga.jabnet.id): data fiktif, WA mati, host
 * produksi dibuang dari database demo. DemoSeeder tidak boleh pernah
 * berjalan di produksi sungguhan.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'demo-sukawarga.jabnet.id';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function modeDemo(): void
    {
        config(['app.demo' => true, 'app.demo_host' => self::HOST, 'app.demo_pin' => '739184']);
    }

    private function seedDemo(): string
    {
        Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

        return Artisan::output();
    }

    // --- pengaman ------------------------------------------------------------------

    public function test_menolak_tanpa_mode_demo(): void
    {
        config(['app.demo' => false, 'app.demo_host' => self::HOST]);

        try {
            $this->seedDemo();
            $this->fail('DemoSeeder harus menolak tanpa DEMO_MODE.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('DEMO_MODE', $e->getMessage());
        }
        $this->assertSame(0, Keluarga::withoutGlobalScope('organisasi')->count());
    }

    public function test_menolak_database_yang_sudah_berisi_warga(): void
    {
        $this->modeDemo();
        Keluarga::create(['keluarga_id' => 'kk_nyata', 'nama' => 'Warga Nyata', 'alamat' => 'x', 'rt' => '01']);

        $this->expectException(\RuntimeException::class);
        $this->seedDemo();
    }

    public function test_menolak_tanpa_host_demo(): void
    {
        config(['app.demo' => true, 'app.demo_host' => null]);

        $this->expectException(\RuntimeException::class);
        $this->seedDemo();
    }

    // --- hasil ------------------------------------------------------------------------

    public function test_host_demo_terdaftar_dan_host_produksi_dibuang(): void
    {
        $this->modeDemo();
        $this->seedDemo();

        $this->assertSame([self::HOST], Domain::pluck('hostname')->all());
        $this->assertTrue((bool) Domain::where('hostname', self::HOST)->value('is_primary'));

        $this->get('https://'.self::HOST.'/login')->assertOk();
        $this->get('https://desa.jabnet.id/login')->assertNotFound();
        $this->get('https://paru.jabnet.id/login')->assertNotFound();
    }

    public function test_seluruh_data_jelas_fiktif(): void
    {
        $this->modeDemo();
        $this->seedDemo();

        $kk = Keluarga::withoutGlobalScope('organisasi')->get();
        $this->assertGreaterThanOrEqual(20, $kk->count());
        foreach ($kk as $baris) {
            $this->assertStringStartsWith('[Demo]', $baris->nama);
            $this->assertStringStartsWith('9999', (string) $baris->nik);
            $this->assertStringStartsWith('9999', (string) $baris->noKK);
            $this->assertStringStartsWith('62800000', (string) $baris->noHP);
        }
        foreach (Anggota::withoutGlobalScope('organisasi')->get() as $a) {
            $this->assertStringStartsWith('[Demo]', $a->nama);
        }
        $this->assertGreaterThan(0, Transaksi::withoutGlobalScope('organisasi')->count());
        $this->assertGreaterThan(0, Surat::withoutGlobalScope('organisasi')->count());

        $namaOrg = Organization::whereIn('type', ['desa', 'rw'])->pluck('name')->implode(' ');
        $this->assertStringNotContainsString('Sukakarya', $namaOrg);
        $this->assertSame('Portal Warga Demo', AppSetting::where('key', 'nama_aplikasi')->value('value'));
    }

    public function test_akun_demo_per_peran_bisa_masuk(): void
    {
        $this->modeDemo();
        $keluaran = $this->seedDemo();

        foreach (['demo.ketua', 'demo.sekretaris', 'demo.bendahara', 'demo.rt', 'demo.warga'] as $username) {
            $user = User::where('username', $username)->firstOrFail();
            $this->assertTrue(Hash::check('739184', $user->pin), $username);
        }
        $this->assertStringNotContainsString('739184', $keluaran, 'PIN dari env tidak dicetak');

        $this->post('https://'.self::HOST.'/login', ['username' => 'demo.ketua', 'pin' => '739184'])->assertRedirect();
        $this->assertAuthenticated();
        $this->get('https://'.self::HOST.'/warga')->assertOk()->assertSee('[Demo]');
    }

    public function test_pin_acak_dicetak_sekali_bila_tidak_ada_di_env(): void
    {
        config(['app.demo' => true, 'app.demo_host' => self::HOST, 'app.demo_pin' => null]);
        $keluaran = $this->seedDemo();

        $this->assertMatchesRegularExpression('/PIN akun demo: (\d{6})/', $keluaran);
        preg_match('/PIN akun demo: (\d{6})/', $keluaran, $m);
        $this->assertTrue(Hash::check($m[1], User::where('username', 'demo.warga')->value('pin')));
    }

    public function test_tidak_bisa_dijalankan_dua_kali(): void
    {
        $this->modeDemo();
        $this->seedDemo();

        $this->expectException(\RuntimeException::class);
        $this->seedDemo();
    }

    // --- mode demo --------------------------------------------------------------------

    public function test_mode_demo_tidak_pernah_mengirim_wa(): void
    {
        $this->modeDemo();
        AppSetting::simpanRahasia('mpwa_api_key', 'kunci-demo');
        AppSetting::simpan('mpwa_sender', '6280000000001');

        $this->assertFalse(MpwaService::send('081234567890', 'uji'));
        Http::assertNothingSent();
    }

    public function test_pita_demo_tampil_di_login_dan_aplikasi(): void
    {
        $this->modeDemo();
        $this->seedDemo();

        $this->get('https://'.self::HOST.'/login')->assertSee('Situs demo', false);
        $this->post('https://'.self::HOST.'/login', ['username' => 'demo.ketua', 'pin' => '739184']);
        $this->get('https://'.self::HOST.'/')->assertSee('Situs demo', false);
    }

    public function test_tanpa_mode_demo_tidak_ada_pita(): void
    {
        $this->get('/login')->assertDontSee('Situs demo', false);
    }

    // --- domain induk tenant ------------------------------------------------------------

    public function test_domain_induk_tenant_dari_konfigurasi(): void
    {
        config(['app.domain_tenant' => 'demo-sukawarga.jabnet.id']);

        $hasil = app(PembukaTenant::class)->buka('Desa Uji', 'ujicoba', 'Kec Uji', ['1'], buatAdmin: false);

        $this->assertStringEndsWith('.demo-sukawarga.jabnet.id', $hasil['baris'][0]['hostname']);
    }
}
