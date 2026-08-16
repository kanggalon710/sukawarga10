<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase D: warga hanya bisa login di subdomain RW-nya sendiri. Penolakan
 * memakai pesan ramah yang menyebutkan alamat portal yang benar - warga yang
 * salah alamat dibimbing, bukan dibiarkan menatap dashboard kosong.
 */
class LoginTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01', '02'],
        ])->assertSuccessful();
    }

    private function org(string $slug): Organization
    {
        return Organization::where('slug', $slug)->firstOrFail();
    }

    /** Warga RW 01 Cibunar: akun ber-keluarga_id, tanpa assignment. */
    private function wargaRw01(): User
    {
        $kk = new Keluarga([
            'keluarga_id' => 'kk_asep01', 'nama' => 'Asep Sunandar',
            'alamat' => 'Jl. Cibunar', 'rt' => '01', 'status' => 'aktif',
        ]);
        $kk->organization_id = $this->org('rw-01-cibunar')->id;
        $kk->save();

        return User::create([
            'user_id' => 'u_asep', 'username' => 'asep', 'namaLengkap' => 'Asep Sunandar',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
            'keluarga_id' => 'kk_asep01',
        ]);
    }

    private function coba(string $host, string $username, string $pin = '123456')
    {
        return $this->post("https://{$host}/login", [
            'username' => $username, 'pin' => $pin,
        ]);
    }

    public function test_warga_ditolak_di_subdomain_rw_lain_dengan_alamat_yang_benar(): void
    {
        $this->wargaRw01();

        $respons = $this->coba('cibunar-rw02.desa.jabnet.id', 'asep');

        $respons->assertRedirect()->assertSessionHas('error');
        $this->assertGuest();
        $this->assertStringContainsString(
            'cibunar-rw01.desa.jabnet.id', session('error'),
            'Pesan penolakan harus menunjukkan alamat portal RW asal warga.'
        );
    }

    public function test_warga_berhasil_login_di_subdomain_rw_nya_sendiri(): void
    {
        $this->wargaRw01();

        $this->coba('cibunar-rw01.desa.jabnet.id', 'asep')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_admin_rw_ditolak_di_rw_tetangga_dan_diterima_di_rw_nya(): void
    {
        // Admin buatan tenant:buat ber-PIN acak; setel ulang supaya bisa diuji.
        $admin = User::where('username', 'cibunar-rw02')->firstOrFail();
        $admin->update(['pin' => Hash::make('654321')]);

        $ditolak = $this->coba('cibunar-rw01.desa.jabnet.id', 'cibunar-rw02', '654321');
        $ditolak->assertSessionHas('error');
        $this->assertGuest();
        $this->assertStringContainsString('cibunar-rw02.desa.jabnet.id', session('error'));

        $this->coba('cibunar-rw02.desa.jabnet.id', 'cibunar-rw02', '654321')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_super_admin_platform_bisa_login_di_semua_subdomain(): void
    {
        $staf = User::create([
            'user_id' => 'u_plat', 'username' => 'stafplatform', 'namaLengkap' => 'Staf Platform',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $staf->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => $this->org('platform')->id,
        ]);

        $this->coba('cibunar-rw01.desa.jabnet.id', 'stafplatform')->assertRedirect('/');
        $this->assertAuthenticated();
        $this->post('/logout');
        $this->coba('cibunar-rw02.desa.jabnet.id', 'stafplatform')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_akun_tanpa_jangkar_tetap_bisa_login(): void
    {
        // Akun lama tanpa keluarga_id dan tanpa assignment: jangan dikunci
        // dari mana-mana; perilaku lama dipertahankan.
        User::create([
            'user_id' => 'u_bebas', 'username' => 'akunlama', 'namaLengkap' => 'Akun Lama',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
        ]);

        $this->coba('cibunar-rw01.desa.jabnet.id', 'akunlama')->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_sidebar_menampilkan_nama_tenant_saat_ini(): void
    {
        // Badge sidebar dulunya menulis tetap "RW 10 Sukakarya"; kini
        // mengikuti tenant request - Cibunar melihat namanya sendiri.
        $this->actingAs($this->wargaRw01())
            ->get('https://cibunar-rw01.desa.jabnet.id/')
            ->assertOk()
            ->assertSee('RW 01')
            ->assertSee('DESA CIBUNAR', false)
            ->assertDontSee('RW 10 &bull; Tarogong Kidul', false);
    }

    public function test_statistik_halaman_login_tidak_menghitung_tenant_lain(): void
    {
        // Keluarga + anggota milik RW 10 (tenant lain bagi Cibunar).
        $kk = new Keluarga([
            'keluarga_id' => 'kk_rw10', 'nama' => 'Warga Sepuluh',
            'alamat' => 'Jl. Sepuluh', 'rt' => '01', 'status' => 'aktif', 'nik' => '3205000000000001',
        ]);
        $kk->organization_id = $this->org('rw-10-sukakarya')->id;
        $kk->save();
        Anggota::create([
            'anggota_id' => 'ag_rw10', 'keluarga_id' => 'kk_rw10',
            'nama' => 'Anak Sepuluh', 'statusKeluarga' => 'Anak', 'jenisKelamin' => 'L',
            'nik' => '3205000000000002',
        ]);

        $respons = $this->get('https://cibunar-rw01.desa.jabnet.id/login');
        $respons->assertOk();

        $this->assertSame(0, $respons->viewData('totalKK'));
        $this->assertSame(0, $respons->viewData('totalA'), 'Jumlah anggota bocor lintas tenant.');
        $this->assertSame(0, $respons->viewData('totalNIK'), 'Hitungan NIK bocor lintas tenant.');
    }
}
