<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase F: app_settings ber-scope organisasi + inheritance + feature flags.
 * Aturan §37 terakhir: mengubah branding tenant A tidak boleh mengubah
 * branding tenant B. Baris ber-organisasi menimpa default platform (NULL);
 * yang terdekat di rantai menang.
 */
class PengaturanTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $rwAsing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rwAsing = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
        Domain::create([
            'organization_id' => $this->rwAsing->id,
            'hostname' => 'rw99.desa.test', 'is_primary' => true,
        ]);

        $this->admin = User::create([
            'user_id' => 'u_set', 'username' => 'setadmin', 'namaLengkap' => 'Set Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
    }

    private function idRw10(): int
    {
        return Organization::where('slug', 'rw-10-sukakarya')->value('id');
    }

    public function test_identitas_per_tenant_mengikuti_hostname(): void
    {
        AppSetting::create([
            'key' => 'nama_aplikasi', 'value' => 'Kampung Sebelah',
            'organization_id' => $this->rwAsing->id,
        ]);

        // Tenant asing memakai identitasnya sendiri...
        $this->get('https://rw99.desa.test/login')
            ->assertOk()->assertSee('Kampung Sebelah', false);
        // ...dan tenant utama TIDAK ikut berubah (§37: isolasi setting).
        $this->get('https://paru.jabnet.id/login')
            ->assertOk()->assertSee('Kampung Paru', false)->assertDontSee('Kampung Sebelah');
    }

    public function test_default_platform_diwarisi_dan_ditimpa_baris_tenant(): void
    {
        // Baris NULL = default platform.
        AppSetting::create(['key' => 'garis_kemiskinan', 'value' => '444444']);

        $this->get('/login')->assertOk();
        $this->assertSame('444444', AppSetting::nilai('garis_kemiskinan'));

        // Baris tenant (lebih dekat di rantai) menang atas default platform.
        AppSetting::create([
            'key' => 'garis_kemiskinan', 'value' => '555555',
            'organization_id' => $this->idRw10(),
        ]);
        $this->get('/login')->assertOk(); // request baru: memo setting di-reset
        $this->assertSame('555555', AppSetting::nilai('garis_kemiskinan'));
    }

    public function test_tarif_tenant_lain_tidak_mempengaruhi_pembayaran_di_sini(): void
    {
        AppSetting::create([
            'key' => 'tarif_sampah', 'value' => '7000', 'organization_id' => $this->idRw10(),
        ]);
        AppSetting::create([
            'key' => 'tarif_sampah', 'value' => '9999', 'organization_id' => $this->rwAsing->id,
        ]);
        $kk = Keluarga::create([
            'keluarga_id' => 'kk_tarif01', 'nama' => 'Uji Tarif', 'alamat' => 'Jl. Uji',
            'rt' => '01', 'organization_id' => $this->idRw10(),
        ]);

        $this->actingAs($this->admin)->post("/sampah/bayar/{$kk->id}", [
            'tahun' => 2026, 'bulan_key' => 'JAN', 'minggu' => ['M1', 'M2'],
            'tanggal_bayar' => '2026-08-16',
        ])->assertRedirect();

        $this->assertSame(
            2 * 7000,
            (int) Transaksi::where('refKeluargaId', (string) $kk->id)->value('jumlah'),
            'Pembayaran harus memakai tarif tenant ini, bukan tarif tenant lain.'
        );
    }

    public function test_form_pengaturan_menulis_di_organisasi_tenant(): void
    {
        // Default platform yang sudah ada tidak boleh tertimpa form tenant.
        $platform = AppSetting::create(['key' => 'tarif_sampah', 'value' => '5000']);

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'tarif', 'tarif_sampah' => '8000',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'tarif_sampah', 'value' => '8000', 'organization_id' => $this->idRw10(),
        ]);
        $this->assertDatabaseHas('app_settings', [
            'id' => $platform->id, 'value' => '5000', 'organization_id' => null,
        ]);
    }

    public function test_fitur_nonaktif_menyembunyikan_menu_untuk_semua_level(): void
    {
        AppSetting::create([
            'key' => 'fitur_umkm', 'value' => '0', 'organization_id' => $this->idRw10(),
        ]);

        $this->actingAs($this->admin)->get('/')->assertOk();

        // Bahkan superadmin: ini ketersediaan modul per tenant, bukan izin.
        $this->assertFalse(userCan('umkm'));
        $this->assertTrue(userCan('sampah'), 'Modul tanpa flag tetap aktif.');
    }

    public function test_flag_tenant_lain_tidak_mematikan_modul_di_sini(): void
    {
        AppSetting::create([
            'key' => 'fitur_umkm', 'value' => '0', 'organization_id' => $this->rwAsing->id,
        ]);

        $this->actingAs($this->admin)->get('/')->assertOk();

        $this->assertTrue(userCan('umkm'));
    }
}
