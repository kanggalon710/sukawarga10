<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\MpwaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P0 rahasia MPWA.
 *
 * Dulu kunci API tersimpan polos di app_settings yang diwariskan, ikut
 * semuaEfektif() ke view, dan tampil di value="" form Pengaturan milik
 * tenant mana pun yang mewarisinya. Tenant juga bisa mengisi mpwa_api_url
 * sehingga kunci warisan dikirim ke host pilihannya (SSRF + kebocoran).
 */
class MpwaRahasiaTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI_PLATFORM = 'kunci-platform-RAHASIA-123';

    private const KUNCI_RW10 = 'kunci-rw10-RAHASIA-456';

    private Organization $rw99;

    /** Http::fake kedua tidak menimpa stub pertama; respons gateway diganti lewat ini. */
    private ?\Closure $gateway = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn (PermintaanHttp $r) => $this->gateway
            ? ($this->gateway)($r)
            : Http::response(['status' => true, 'id' => 'm1']));

        $this->rw99 = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
        Domain::create([
            'organization_id' => $this->rw99->id,
            'hostname' => 'rw99.desa.test', 'is_primary' => true,
        ]);

        // Di konsol (tanpa context) simpanRahasia menulis default platform.
        AppSetting::simpanRahasia('mpwa_api_key', self::KUNCI_PLATFORM);
        AppSetting::simpan('mpwa_sender', '6280000000001');
    }

    private function idRw10(): int
    {
        return Organization::where('slug', 'rw-10-sukakarya')->value('id');
    }

    private function pengurus(string $level, ?Organization $org = null): User
    {
        $user = User::create([
            'user_id' => 'u_'.$level.'_'.uniqid(), 'username' => $level.uniqid(),
            'namaLengkap' => 'Pengurus', 'pin' => Hash::make('123456'),
            'level' => $level, 'status' => 'aktif',
        ]);
        if ($org === null) {
            return $this->pasangPeranSetaraLevel($user);
        }
        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('legacy_level', $level)->where('scope_type', 'rw')->value('id'),
            'organization_id' => $org->id,
        ]);

        return $user;
    }

    private function kunciRw10Sendiri(): void
    {
        AppSetting::create([
            'key' => 'mpwa_api_key', 'organization_id' => $this->idRw10(),
            'value' => Crypt::encryptString(self::KUNCI_RW10),
        ]);
    }

    private function nilaiMilik(?int $orgId): ?string
    {
        // Query langsung hanya untuk asersi: yang diuji baris milik organisasi.
        $v = AppSetting::where('key', 'mpwa_api_key')->where('organization_id', $orgId)->value('value');

        return $v === null ? null : Crypt::decryptString($v);
    }

    // --- tidak pernah sampai ke browser ------------------------------------

    public function test_kunci_warisan_tidak_tampil_di_halaman_pengaturan_rw(): void
    {
        $this->actingAs($this->pengurus('ketua_rw'))->get('/pengaturan')
            ->assertOk()
            ->assertDontSee(self::KUNCI_PLATFORM, false)
            ->assertSee('Memakai kunci bawaan');
    }

    public function test_kunci_sendiri_tidak_dirender_ke_input(): void
    {
        $this->kunciRw10Sendiri();

        $this->actingAs($this->pengurus('ketua_rw'))->get('/pengaturan')
            ->assertOk()
            ->assertDontSee(self::KUNCI_RW10, false)
            ->assertDontSee(self::KUNCI_PLATFORM, false)
            ->assertSee('Tersimpan');
    }

    public function test_input_kunci_tidak_diisi_otomatis_pengelola_sandi(): void
    {
        $this->actingAs($this->pengurus('ketua_rw'))->get('/pengaturan')
            ->assertOk()
            ->assertSee('id="mpwaApiKeyInput" name="mpwa_api_key" value=""', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_kunci_tidak_tampil_di_halaman_mpwa(): void
    {
        $this->kunciRw10Sendiri();

        $this->actingAs($this->pengurus('sekretaris'))->get('/mpwa')
            ->assertOk()
            ->assertDontSee(self::KUNCI_RW10, false)
            ->assertDontSee(self::KUNCI_PLATFORM, false);
    }

    public function test_semua_efektif_tidak_memuat_rahasia(): void
    {
        $this->assertArrayNotHasKey('mpwa_api_key', AppSetting::semuaEfektif());
        $this->assertNull(AppSetting::nilai('mpwa_api_key'));
    }

    public function test_simpan_biasa_menolak_key_rahasia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AppSetting::simpan('mpwa_api_key', 'polos');
    }

    // --- penyimpanan lewat form -----------------------------------------------

    public function test_input_kosong_mempertahankan_kunci(): void
    {
        $this->kunciRw10Sendiri();

        $this->actingAs($this->pengurus('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_key' => '', 'mpwa_sender' => '6281111111111',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(self::KUNCI_RW10, $this->nilaiMilik($this->idRw10()));
    }

    public function test_kunci_baru_tersimpan_terenkripsi(): void
    {
        $this->actingAs($this->pengurus('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_key' => 'kunci-baru-789',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mentah = AppSetting::where('key', 'mpwa_api_key')->where('organization_id', $this->idRw10())->value('value');
        $this->assertNotSame('kunci-baru-789', $mentah);
        $this->assertSame('kunci-baru-789', Crypt::decryptString($mentah));
        $this->assertSame(self::KUNCI_PLATFORM, $this->nilaiMilik(null), 'kunci platform tidak ikut berubah');
    }

    public function test_centang_hapus_kembali_ke_kunci_warisan(): void
    {
        $this->kunciRw10Sendiri();

        $this->actingAs($this->pengurus('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_key_hapus' => '1',
        ])->assertRedirect();

        $this->assertNull($this->nilaiMilik($this->idRw10()));
        $this->assertSame(self::KUNCI_PLATFORM, $this->nilaiMilik(null));
    }

    public function test_tanpa_izin_ubah_pengaturan_ditolak_dan_kunci_utuh(): void
    {
        $this->kunciRw10Sendiri();

        // Sekretaris boleh MELIHAT pengaturan tapi tidak mengubahnya.
        $this->actingAs($this->pengurus('sekretaris'))->post('/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_key' => 'kunci-penyusup',
        ])->assertForbidden();

        $this->assertSame(self::KUNCI_RW10, $this->nilaiMilik($this->idRw10()));
    }

    public function test_tenant_lain_hanya_menulis_barisnya_sendiri(): void
    {
        $this->kunciRw10Sendiri();
        $ketua99 = $this->pengurus('ketua_rw', $this->rw99);

        $this->actingAs($ketua99)->post('https://rw99.desa.test/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_key' => 'kunci-rw99',
        ])->assertRedirect();

        $this->assertSame('kunci-rw99', $this->nilaiMilik($this->rw99->id));
        $this->assertSame(self::KUNCI_RW10, $this->nilaiMilik($this->idRw10()));

        $this->actingAs($ketua99)->get('https://rw99.desa.test/pengaturan')
            ->assertOk()
            ->assertDontSee(self::KUNCI_RW10, false)
            ->assertDontSee('kunci-rw99', false);
    }

    // --- host gateway -------------------------------------------------------------

    public function test_url_gateway_dari_request_diabaikan(): void
    {
        $this->actingAs($this->pengurus('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'wa', 'mpwa_api_url' => 'https://penyerang.test',
        ])->assertRedirect();

        $this->assertDatabaseMissing('app_settings', ['key' => 'mpwa_api_url']);

        MpwaService::send('081234567890', 'uji');
        Http::assertSent(fn (PermintaanHttp $r) => str_starts_with($r->url(), 'https://mpwa.jabnet.id/'));
        Http::assertNotSent(fn (PermintaanHttp $r) => str_contains($r->url(), 'penyerang'));
    }

    public function test_pengiriman_warisan_memakai_kunci_platform_ke_host_tepercaya(): void
    {
        $this->actingAs($this->pengurus('sekretaris'))
            ->postJson('/mpwa/test', ['test_number' => '081234567890'])
            ->assertJson(['success' => true]);

        Http::assertSent(fn (PermintaanHttp $r) => $r->url() === 'https://mpwa.jabnet.id/send-message'
            && $r['api_key'] === self::KUNCI_PLATFORM);
    }

    public function test_host_di_luar_allow_list_tidak_pernah_dihubungi(): void
    {
        config(['services.mpwa.url' => 'https://penyerang.test']);

        $this->assertFalse(MpwaService::send('081234567890', 'uji'));
        $this->actingAs($this->pengurus('sekretaris'))
            ->postJson('/mpwa/test', ['test_number' => '081234567890', 'api_key' => 'coba'])
            ->assertJson(['success' => false]);

        Http::assertNothingSent();
    }

    public function test_gateway_tanpa_https_ditolak(): void
    {
        config(['services.mpwa.url' => 'http://mpwa.jabnet.id']);

        $this->assertFalse(MpwaService::send('081234567890', 'uji'));
        Http::assertNothingSent();
    }

    public function test_url_gateway_dengan_kredensial_atau_query_ditolak(): void
    {
        foreach (['https://u@mpwa.jabnet.id', 'https://u:p@mpwa.jabnet.id', 'https://mpwa.jabnet.id?x=1', 'https://mpwa.jabnet.id#x'] as $url) {
            config(['services.mpwa.url' => $url]);
            $this->assertNull(MpwaService::baseUrl(), $url);
        }
        Http::assertNothingSent();
    }

    public function test_status_gateway_truthy_tetap_dianggap_terkirim(): void
    {
        foreach ([['status' => 1], ['status' => 'success'], ['status' => 'true'], ['id' => 'x']] as $jawaban) {
            $this->gateway = fn () => Http::response($jawaban);
            $this->assertTrue(MpwaService::send('081234567890', 'uji'), json_encode($jawaban));
        }
        foreach ([['status' => false], ['status' => 'false'], ['status' => 0]] as $jawaban) {
            $this->gateway = fn () => Http::response($jawaban);
            $this->assertFalse(MpwaService::send('081234567890', 'uji'), json_encode($jawaban));
        }
    }

    public function test_redirect_dari_gateway_tidak_diikuti(): void
    {
        $this->gateway = fn (PermintaanHttp $r) => str_contains($r->url(), 'penyerang')
            ? Http::response(['status' => true])
            : Http::response('', 302, ['Location' => 'https://penyerang.test/curi']);

        $this->assertFalse(MpwaService::send('081234567890', 'uji'));
        Http::assertSentCount(1);
        Http::assertNotSent(fn (PermintaanHttp $r) => str_contains($r->url(), 'penyerang'));
    }

    public function test_respons_uji_tidak_meneruskan_pesan_gateway_mentah(): void
    {
        $this->gateway = fn () => Http::response(['status' => false, 'message' => 'api_key kunci-bocor invalid for /home/u/app'], 400);

        $res = $this->actingAs($this->pengurus('sekretaris'))
            ->postJson('/mpwa/test', ['test_number' => '081234567890']);

        $res->assertJson(['success' => false]);
        $this->assertStringNotContainsString('kunci-bocor', $res->getContent());
        $this->assertStringNotContainsString('/home/', $res->getContent());
    }

    // --- migrasi --------------------------------------------------------------------

    public function test_migrasi_mengenkripsi_kunci_polos_dan_membuang_url(): void
    {
        DB::table('app_settings')->where('key', 'mpwa_api_key')->delete();
        DB::table('app_settings')->insert([
            ['key' => 'mpwa_api_key', 'value' => 'polos-lama', 'organization_id' => $this->idRw10()],
            ['key' => 'mpwa_api_url', 'value' => 'https://penyerang.test', 'organization_id' => $this->idRw10()],
        ]);

        $migrasi = require database_path('migrations/2026_09_30_000002_enkripsi_rahasia_mpwa.php');
        ob_start();
        $migrasi->up();
        $migrasi->up(); // idempoten: tidak mengenkripsi dua kali
        ob_end_clean();

        $this->assertSame('polos-lama', $this->nilaiMilik($this->idRw10()));
        $this->assertDatabaseMissing('app_settings', ['key' => 'mpwa_api_url']);

        $migrasi->down();
        $this->assertSame('polos-lama', AppSetting::where('key', 'mpwa_api_key')->value('value'));
    }
}
