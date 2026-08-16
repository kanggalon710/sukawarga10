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
 * Manajemen Akun bertingkat: owner (admin platform) mengelola SEMUA akun di
 * desa.jabnet.id, admin desa mengelola akun desa + RW-RW-nya di host desa,
 * admin RW mengelola akun tenantnya saja. Sebelumnya /akun menampilkan dan
 * bisa mengubah SEMUA user lintas tenant (model User memang tidak di-scope).
 */
class KelolaAkunHirarkiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminPlatform;

    private User $adminDesa;

    private User $adminRw01;

    private User $wargaRw01;

    private User $wargaRw02;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01', '02'],
        ])->assertSuccessful();

        $this->adminPlatform = $this->buatUser('adminplat', 'Boss Platform', 'superadmin');
        $this->pasang($this->adminPlatform, 'super_admin', 'platform');

        $this->adminDesa = $this->buatUser('admindesa', 'Pengelola Desa', 'ketua_rw');
        $this->pasang($this->adminDesa, 'desa_admin', 'cibunar');

        $this->adminRw01 = $this->buatUser('adminrw01', 'Pengelola RW Satu', 'superadmin');
        $this->pasang($this->adminRw01, 'super_admin', 'rw-01-cibunar');

        $this->wargaRw01 = $this->buatWarga('wargasatu', 'Warga Satu', 'rw-01-cibunar', 'kk_ws1');
        $this->wargaRw02 = $this->buatWarga('wargadua', 'Warga Dua', 'rw-02-cibunar', 'kk_ws2');
    }

    private function buatUser(string $username, string $nama, string $level): User
    {
        return User::create([
            'user_id' => 'u_'.$username, 'username' => $username, 'namaLengkap' => $nama,
            'pin' => Hash::make('123456'), 'level' => $level, 'status' => 'aktif',
        ]);
    }

    private function pasang(User $user, string $slug, string $orgSlug): void
    {
        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'organization_id' => Organization::where('slug', $orgSlug)->value('id'),
        ]);
    }

    private function buatWarga(string $username, string $nama, string $orgSlug, string $kkId): User
    {
        $kk = new Keluarga([
            'keluarga_id' => $kkId, 'nama' => $nama, 'alamat' => 'Jl. Uji', 'rt' => '01',
        ]);
        $kk->organization_id = Organization::where('slug', $orgSlug)->value('id');
        $kk->saveQuietly();

        $user = $this->buatUser($username, $nama, 'warga');
        $user->update(['keluarga_id' => $kkId]);

        return $user;
    }

    public function test_generate_akun_warga_untuk_kk_yang_belum_punya(): void
    {
        // KK ketiga di rw01 tanpa akun; wargaRw01 (kk_ws1) SUDAH punya akun.
        $kk = new Keluarga([
            'keluarga_id' => 'kk_ws3', 'nama' => 'Cecep Tanpa Akun',
            'alamat' => 'Jl. Uji', 'rt' => '01', 'noHP' => '081234567890',
        ]);
        $kk->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
        $kk->saveQuietly();

        $respons = $this->actingAs($this->adminRw01)
            ->post('https://cibunar-rw01.desa.jabnet.id/akun/generate-warga');
        $respons->assertRedirect()->assertSessionHas('hasilAkunWarga');

        // Akun lahir tertaut KK-nya, level warga, wa ikut dari noHP.
        $akun = User::where('keluarga_id', 'kk_ws3')->firstOrFail();
        $this->assertSame('warga', $akun->level);
        $this->assertSame('6281234567890', $akun->wa);
        // PIN tampil sekali di hasil.
        $baris = collect(session('hasilAkunWarga'))->firstWhere('keluarga', 'Cecep Tanpa Akun');
        $this->assertNotNull($baris['pin']);
        $this->assertTrue(Hash::check($baris['pin'], $akun->pin));

        // KK yang sudah punya akun TIDAK dibuatkan lagi (kk_ws1 dan kk_ws2
        // sudah ber-akun dari setUp; kk_ws2 milik tenant lain pula).
        $this->assertSame(1, User::where('keluarga_id', 'kk_ws1')->count());
        $this->assertSame(1, User::where('keluarga_id', 'kk_ws2')->count());

        // Diulang: tidak ada akun baru.
        $this->actingAs($this->adminRw01)
            ->post('https://cibunar-rw01.desa.jabnet.id/akun/generate-warga')->assertRedirect();
        $this->assertSame(1, User::where('keluarga_id', 'kk_ws3')->count());
    }

    public function test_generate_akun_warga_menangani_nama_kembar(): void
    {
        foreach (['kk_km1', 'kk_km2'] as $id) {
            $kk = new Keluarga([
                'keluarga_id' => $id, 'nama' => 'Asep Kembar', 'alamat' => 'Jl. Uji', 'rt' => '01',
            ]);
            $kk->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
            $kk->saveQuietly();
        }

        $this->actingAs($this->adminRw01)
            ->post('https://cibunar-rw01.desa.jabnet.id/akun/generate-warga')->assertRedirect();

        $this->assertSame(2, User::whereIn('keluarga_id', ['kk_km1', 'kk_km2'])->count());
        $this->assertSame(2, User::whereIn('keluarga_id', ['kk_km1', 'kk_km2'])
            ->distinct('username')->count('username'));
    }

    public function test_generate_akun_warga_hanya_di_host_rw(): void
    {
        $this->actingAs($this->adminPlatform)
            ->post('https://desa.jabnet.id/akun/generate-warga')
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_akun_warga_hasil_generate_bisa_login_di_portalnya(): void
    {
        $kk = new Keluarga([
            'keluarga_id' => 'kk_login', 'nama' => 'Dudung Login', 'alamat' => 'Jl. Uji', 'rt' => '01',
        ]);
        $kk->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
        $kk->saveQuietly();

        $this->actingAs($this->adminRw01)
            ->post('https://cibunar-rw01.desa.jabnet.id/akun/generate-warga');
        $baris = collect(session('hasilAkunWarga'))->firstWhere('keluarga', 'Dudung Login');

        $this->post('https://cibunar-rw01.desa.jabnet.id/logout');
        $this->post('https://cibunar-rw01.desa.jabnet.id/login', [
            'username' => $baris['username'], 'pin' => $baris['pin'],
        ])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_admin_rw_hanya_melihat_akun_tenantnya(): void
    {
        $this->actingAs($this->adminRw01)
            ->get('https://cibunar-rw01.desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('Warga Satu')
            ->assertDontSee('Warga Dua')
            ->assertDontSee('Boss Platform');
    }

    public function test_admin_rw_tidak_bisa_mengubah_pin_akun_tenant_lain(): void
    {
        $this->actingAs($this->adminRw01)
            ->post("https://cibunar-rw01.desa.jabnet.id/akun/{$this->wargaRw02->id}/pin", ['pin' => '999999'])
            ->assertNotFound();

        $this->assertTrue(Hash::check('123456', $this->wargaRw02->fresh()->pin));
    }

    public function test_admin_desa_mengelola_akun_seluruh_rw_di_desanya(): void
    {
        $this->actingAs($this->adminDesa)
            ->get('https://cibunar.desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('Warga Satu')
            ->assertSee('Warga Dua')
            ->assertDontSee('Boss Platform');

        $this->actingAs($this->adminDesa)
            ->post("https://cibunar.desa.jabnet.id/akun/{$this->wargaRw02->id}/pin", ['pin' => '888888'])
            ->assertRedirect();
        $this->assertTrue(Hash::check('888888', $this->wargaRw02->fresh()->pin));
    }

    public function test_admin_rw_tidak_boleh_membuka_akun_di_host_desa(): void
    {
        // rw_admin/superadmin ber-scope RW bukan pengelola tingkat desa.
        $this->actingAs($this->adminRw01)
            ->get('https://cibunar.desa.jabnet.id/akun')->assertForbidden();
    }

    public function test_owner_mengelola_semua_akun_di_host_platform(): void
    {
        $this->actingAs($this->adminPlatform)
            ->get('https://desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('Warga Satu')
            ->assertSee('Warga Dua')
            ->assertSee('Pengelola Desa');

        $this->actingAs($this->adminPlatform)
            ->post("https://desa.jabnet.id/akun/{$this->adminDesa->id}/pin", ['pin' => '777777'])
            ->assertRedirect();
        $this->assertTrue(Hash::check('777777', $this->adminDesa->fresh()->pin));
    }

    public function test_admin_tenant_tidak_boleh_membuka_akun_di_host_platform(): void
    {
        $this->actingAs($this->adminRw01)
            ->get('https://desa.jabnet.id/akun')->assertForbidden();
    }

    public function test_owner_bisa_mengganti_username_dengan_penjaga_unik(): void
    {
        $this->actingAs($this->adminPlatform)->put(
            "https://desa.jabnet.id/akun/{$this->wargaRw01->id}",
            ['namaLengkap' => 'Warga Satu', 'level' => 'warga', 'username' => 'wargasatubaru']
        )->assertRedirect();
        $this->assertSame('wargasatubaru', $this->wargaRw01->fresh()->username);

        // Username milik akun lain ditolak di batas masuk.
        $this->actingAs($this->adminPlatform)->put(
            "https://desa.jabnet.id/akun/{$this->wargaRw01->id}",
            ['namaLengkap' => 'Warga Satu', 'level' => 'warga', 'username' => 'wargadua']
        )->assertSessionHasErrors('username');
        $this->assertSame('wargasatubaru', $this->wargaRw01->fresh()->username);
    }

    public function test_level_tidak_berubah_lewat_host_non_rw(): void
    {
        // Sinkronisasi assignment butuh tenant RW; di host platform/desa kolom
        // level dibiarkan supaya tidak lepas sinkron dengan assignment.
        $this->actingAs($this->adminPlatform)->put(
            "https://desa.jabnet.id/akun/{$this->wargaRw01->id}",
            ['namaLengkap' => 'Warga Satu', 'level' => 'superadmin']
        )->assertRedirect();

        $this->assertSame('warga', $this->wargaRw01->fresh()->level);
    }

    public function test_matriks_permission_dari_host_platform_menjadi_bawaan_platform(): void
    {
        $perms = getDefaultPermissions();
        $this->actingAs($this->adminPlatform)
            ->postJson('https://desa.jabnet.id/akun/permissions', ['permissions' => $perms])
            ->assertOk();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'role_permissions',
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);
    }

    public function test_tombol_buat_admin_desa_di_manajemen_desa(): void
    {
        $desa = Organization::where('slug', 'cibunar')->firstOrFail();

        $this->actingAs($this->adminPlatform)
            ->post("https://desa.jabnet.id/tenant/{$desa->id}/admin")
            ->assertRedirect()->assertSessionHas('hasilAdminDesa');

        $admin = User::where('username', 'cibunar-admin')->firstOrFail();
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $admin->id,
            'role_id' => Role::where('slug', 'desa_admin')->value('id'),
            'organization_id' => $desa->id,
        ]);

        // Diulang: tidak menggandakan, PIN tidak direset.
        $pin = $admin->pin;
        $this->actingAs($this->adminPlatform)
            ->post("https://desa.jabnet.id/tenant/{$desa->id}/admin")->assertRedirect();
        $this->assertSame(1, User::where('username', 'cibunar-admin')->count());
        $this->assertSame($pin, $admin->fresh()->pin);
    }

    public function test_halaman_akun_rw_menampilkan_alamat_portal_warga(): void
    {
        $this->actingAs($this->adminRw01)
            ->get('https://cibunar-rw01.desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('Untuk akses login warga')
            ->assertSee('https://cibunar-rw01.desa.jabnet.id', false);
    }

    public function test_halaman_akun_platform_menampilkan_daftar_portal_rw(): void
    {
        $this->actingAs($this->adminPlatform)
            ->get('https://desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('cibunar-rw01.desa.jabnet.id')
            ->assertSee('cibunar-rw02.desa.jabnet.id');
    }

    public function test_instruksi_portal_di_host_desa_hanya_rw_desanya(): void
    {
        $this->artisan('tenant:buat', [
            'nama' => 'Desa Lain', 'label' => 'lain',
            '--kecamatan' => 'Garut Kota', '--rw' => ['01'],
        ])->assertSuccessful();

        $this->actingAs($this->adminDesa)
            ->get('https://cibunar.desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('cibunar-rw01.desa.jabnet.id')
            ->assertDontSee('lain-rw01.desa.jabnet.id');
    }

    public function test_instruksi_portal_melewatkan_rw_nonaktif(): void
    {
        Organization::where('slug', 'rw-02-cibunar')->update(['status' => 'nonaktif']);

        $this->actingAs($this->adminPlatform)
            ->get('https://desa.jabnet.id/akun')
            ->assertOk()
            ->assertSee('cibunar-rw01.desa.jabnet.id')
            ->assertDontSee('cibunar-rw02.desa.jabnet.id');
    }
}
