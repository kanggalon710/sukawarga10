<?php

namespace Tests\Feature;

use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Halaman per tingkat hirarki: desa.jabnet.id (platform, dashboard owner),
 * {label}.desa.jabnet.id (profil desa publik), {label}-rw{nn}.desa.jabnet.id
 * (portal RW penuh). Domain root milik org PLATFORM sehingga tidak pernah
 * ikut terhapus bersama tenant - insiden nyata 2026-08-16: menghapus RW 10
 * ikut menghapus baris domain desa.jabnet.id dan portal root jadi 404.
 */
class HirarkiHalamanTest extends TestCase
{
    use RefreshDatabase;

    private User $adminPlatform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01'],
        ])->assertSuccessful();

        // KK di RW 01 Cibunar, dibuat diam-diam supaya organization_id utuh.
        $kk = new Keluarga([
            'keluarga_id' => 'kk_hier01', 'nama' => 'Keluarga Hirarki',
            'alamat' => 'Jl. Hirarki', 'rt' => '01', 'status' => 'aktif',
        ]);
        $kk->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
        $kk->saveQuietly();

        $this->adminPlatform = User::create([
            'user_id' => 'u_hier', 'username' => 'hieradmin', 'namaLengkap' => 'Hier Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminPlatform->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);
    }

    public function test_domain_root_terdaftar_milik_platform(): void
    {
        // Migrasi mendaftarkan desa.jabnet.id ke org PLATFORM: menghapus RW/desa
        // mana pun tidak akan pernah mematikan portal root lagi.
        $this->assertDatabaseHas('domains', [
            'hostname' => 'desa.jabnet.id',
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);

        $this->get('https://desa.jabnet.id/login')->assertOk();
    }

    public function test_tenant_buat_ikut_mendaftarkan_domain_desa(): void
    {
        $this->assertDatabaseHas('domains', [
            'hostname' => 'cibunar.desa.jabnet.id',
            'organization_id' => Organization::where('slug', 'cibunar')->value('id'),
        ]);
    }

    public function test_root_platform_tamu_diarahkan_ke_login(): void
    {
        $this->get('https://desa.jabnet.id/')->assertRedirect();
    }

    public function test_root_platform_menampilkan_dashboard_owner(): void
    {
        $this->actingAs($this->adminPlatform)->get('https://desa.jabnet.id/')
            ->assertOk()
            ->assertSee('Total desa')
            ->assertSee('Desa Cibunar')
            ->assertSee(route('tenant.index', absolute: false));
    }

    public function test_root_platform_mengarahkan_pengurus_rw_ke_portalnya(): void
    {
        $pengurus = User::create([
            'user_id' => 'u_prw', 'username' => 'pengurusrw', 'namaLengkap' => 'Pengurus RW',
            'pin' => Hash::make('123456'), 'level' => 'ketua_rw', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $pengurus->id,
            'role_id' => Role::where('slug', 'rw_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);

        $respons = $this->actingAs($pengurus)->get('https://desa.jabnet.id/');
        $respons->assertRedirect();
        $this->assertStringContainsString(
            'cibunar-rw01.desa.jabnet.id', $respons->headers->get('Location')
        );
    }

    public function test_halaman_desa_publik_memuat_daftar_rw_dan_jumlah_kk(): void
    {
        $this->get('https://cibunar.desa.jabnet.id/')
            ->assertOk()
            ->assertSee('Desa Cibunar')
            ->assertSee('RW 01')
            ->assertSee('cibunar-rw01.desa.jabnet.id')
            ->assertSee('1', false)
            ->assertDontSee('Keluarga Hirarki');
    }

    public function test_modul_tenant_tertutup_di_host_platform_dan_desa(): void
    {
        foreach (['https://desa.jabnet.id', 'https://cibunar.desa.jabnet.id'] as $basis) {
            // Juga untuk TAMU: 404, bukan redirect login - pembatasan berjalan
            // di resolver, sebelum sorting priority mendahulukan Authenticate.
            $this->get("{$basis}/warga")->assertNotFound();
            $this->actingAs($this->adminPlatform)->get("{$basis}/warga")->assertNotFound();
            $this->actingAs($this->adminPlatform)->get("{$basis}/bukukas")->assertNotFound();
            $this->actingAs($this->adminPlatform)->post("{$basis}/surat", [])->assertNotFound();
        }
    }

    public function test_manajemen_desa_dan_pembaruan_tetap_hidup_di_host_platform(): void
    {
        $this->actingAs($this->adminPlatform)
            ->get('https://desa.jabnet.id/tenant')->assertOk();
        \Illuminate\Support\Facades\Process::fake(['*' => \Illuminate\Support\Facades\Process::result('a|b|c')]);
        $this->actingAs($this->adminPlatform)
            ->get('https://desa.jabnet.id/pembaruan')->assertOk();
    }

    public function test_scope_fail_closed_di_host_non_rw(): void
    {
        $this->get('https://cibunar.desa.jabnet.id/')->assertOk();

        // Context desa (tanpa RW): query ber-scope TIDAK boleh fail-open.
        $this->assertSame(0, Keluarga::count());
        $this->assertSame(1, Keluarga::withoutGlobalScope('organisasi')->count());
    }

    public function test_portal_rw_tidak_berubah_perilakunya(): void
    {
        $this->get('https://cibunar-rw01.desa.jabnet.id/')->assertRedirect();
        $this->get('https://cibunar-rw01.desa.jabnet.id/login')->assertOk();
    }
}
