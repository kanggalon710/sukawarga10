<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase G tahap 1: membuat desa/RW lewat website. Halaman Manajemen Desa
 * HANYA untuk pemegang super_admin di organisasi PLATFORM - superadmin
 * ber-scope tenant (buatan Manajemen Akun) mengelola satu RW, bukan
 * membuka desa baru untuk semua orang.
 */
class ManajemenTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $adminPlatform;

    private User $adminTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminPlatform = User::create([
            'user_id' => 'u_plat', 'username' => 'adminplatform', 'namaLengkap' => 'Admin Platform',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminPlatform->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);

        // Superadmin ber-scope tenant RW 10 (pola Manajemen Akun).
        $this->adminTenant = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_ten', 'username' => 'admintenant', 'namaLengkap' => 'Admin Tenant',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    public function test_halaman_terbuka_untuk_admin_platform_saja(): void
    {
        $this->actingAs($this->adminPlatform)->get('/tenant')
            ->assertOk()->assertSee('Sukakarya');

        $this->actingAs($this->adminTenant)->get('/tenant')->assertForbidden();
    }

    public function test_menu_manajemen_desa_hanya_tampil_untuk_admin_platform(): void
    {
        $this->actingAs($this->adminPlatform)->get('/')
            ->assertOk()->assertSee('Manajemen Desa');

        $this->actingAs($this->adminTenant)->get('/')
            ->assertOk()->assertDontSee('Manajemen Desa');
    }

    public function test_membuat_desa_lewat_form_dan_pin_tampil_sekali(): void
    {
        $respons = $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            'kecamatan' => 'Tarogong Kidul', 'rw' => '01,02',
        ]);
        $respons->assertRedirect(route('tenant.index'))->assertSessionHas('hasilTenant');

        $desa = Organization::where('slug', 'cibunar')->first();
        $this->assertNotNull($desa);
        $this->assertSame('Desa Cibunar (Tarogong Kidul)', $desa->name);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw01.desa.jabnet.id']);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw02.desa.jabnet.id']);

        $rw01 = Organization::where('slug', 'rw-01-cibunar')->first();
        $admin = User::where('username', 'cibunar-rw01')->first();
        $this->assertNotNull($admin);
        // Operator portal RW, bukan ketua - lihat komentar di BuatTenantTest.
        $this->assertSame('superadmin', $admin->levelEfektifUntuk($rw01));

        // PIN tampil di halaman hasil, lalu hilang pada kunjungan berikutnya.
        $pin = collect(session('hasilTenant')['baris'])->firstWhere('rw', '01')['pin'];
        $this->actingAs($this->adminPlatform)->get('/tenant')->assertSee($pin);
        $this->actingAs($this->adminPlatform)->get('/tenant')->assertDontSee($pin);
    }

    public function test_menambah_rw_ke_desa_yang_sudah_ada_tanpa_menggandakan(): void
    {
        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            'kecamatan' => 'Tarogong Kidul', 'rw' => '01',
        ])->assertRedirect(route('tenant.index'));
        $pinAwal = User::where('username', 'cibunar-rw01')->value('pin');

        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            'kecamatan' => 'Tarogong Kidul', 'rw' => '01,03',
        ])->assertRedirect(route('tenant.index'));

        $this->assertSame(1, Organization::where('slug', 'cibunar')->count());
        $this->assertSame(1, Domain::where('hostname', 'cibunar-rw01.desa.jabnet.id')->count());
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw03.desa.jabnet.id']);
        $this->assertSame(
            $pinAwal, User::where('username', 'cibunar-rw01')->value('pin'),
            'PIN admin yang sudah ada tidak boleh direset lewat form.'
        );
    }

    public function test_label_tidak_sah_ditolak_di_batas_masuk(): void
    {
        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'Cibunar Kota!', 'rw' => '01',
        ])->assertSessionHasErrors('label');

        $this->assertSame(0, Organization::where('type', Organization::TYPE_DESA)
            ->where('name', 'like', '%Cibunar%')->count());
    }

    private function buatCibunar(string $rw = '01'): Organization
    {
        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            'kecamatan' => 'Tarogong Kidul', 'rw' => $rw,
        ])->assertRedirect(route('tenant.index'));

        return Organization::where('slug', 'cibunar')->firstOrFail();
    }

    public function test_ubah_nama_desa_dan_penjaga_duplikatnya(): void
    {
        $desa = $this->buatCibunar();

        $this->actingAs($this->adminPlatform)->put("/tenant/{$desa->id}", [
            'nama' => 'Desa Cibunar Hilir', 'kecamatan' => 'Tarogong Kidul',
        ])->assertRedirect(route('tenant.index'));
        $this->assertSame('Desa Cibunar Hilir (Tarogong Kidul)', $desa->fresh()->name);

        // Ganti nama menjadi desa lain yang sudah ada = duplikat, tolak.
        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cimanuk', 'label' => 'cimanuk',
            'kecamatan' => 'Garut Kota', 'rw' => '01',
        ]);
        $this->actingAs($this->adminPlatform)->put("/tenant/{$desa->id}", [
            'nama' => 'Desa Cimanuk', 'kecamatan' => 'Garut Kota',
        ])->assertSessionHasErrors('nama');
        $this->assertSame('Desa Cibunar Hilir (Tarogong Kidul)', $desa->fresh()->name);
    }

    public function test_nonaktifkan_rw_menutup_portalnya(): void
    {
        $this->buatCibunar();
        $rw = Organization::where('slug', 'rw-01-cibunar')->firstOrFail();

        $this->actingAs($this->adminPlatform)
            ->post("/tenant/rw/{$rw->id}/toggle")->assertRedirect(route('tenant.index'));
        $this->assertSame('nonaktif', $rw->fresh()->status);
        $this->get('https://cibunar-rw01.desa.jabnet.id/login')->assertNotFound();

        // Host eksplisit: klien tes menempelkan host request terakhir, dan
        // host cibunar sedang nonaktif (404) - toggle dikirim lewat localhost.
        $this->actingAs($this->adminPlatform)
            ->post("http://localhost/tenant/rw/{$rw->id}/toggle")->assertRedirect(route('tenant.index'));
        $this->get('https://cibunar-rw01.desa.jabnet.id/login')->assertOk();
    }

    public function test_hapus_rw_kosong_membersihkan_domain_dan_assignment(): void
    {
        $this->buatCibunar('01,02');
        $rw = Organization::where('slug', 'rw-02-cibunar')->firstOrFail();
        $admin = User::where('username', 'cibunar-rw02')->firstOrFail();

        $this->actingAs($this->adminPlatform)
            ->delete("/tenant/rw/{$rw->id}")->assertRedirect(route('tenant.index'));

        $this->assertNull(Organization::find($rw->id));
        $this->assertNull(Domain::where('hostname', 'cibunar-rw02.desa.jabnet.id')->first());
        $this->assertSame(0, UserRoleAssignment::where('organization_id', $rw->id)->count());
        // Akun admin dibiarkan (tanpa assignment = warga biasa), bukan ikut lenyap.
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_hapus_rw_berisi_data_ditolak(): void
    {
        $this->buatCibunar();
        $rw = Organization::where('slug', 'rw-01-cibunar')->firstOrFail();
        $kk = new \App\Models\Keluarga([
            'keluarga_id' => 'kk_isi01', 'nama' => 'Keluarga Isi',
            'alamat' => 'Jl. Isi', 'rt' => '01',
        ]);
        $kk->organization_id = $rw->id;
        // saveQuietly: hook MilikOrganisasi menimpa organization_id dengan
        // tenant request (context sudah hidup dari POST di atas).
        $kk->saveQuietly();

        $this->actingAs($this->adminPlatform)
            ->delete("/tenant/rw/{$rw->id}")
            ->assertRedirect(route('tenant.index'))->assertSessionHas('error');

        $this->assertNotNull(Organization::find($rw->id));
    }

    public function test_hapus_desa_hanya_bila_tanpa_rw(): void
    {
        $desa = $this->buatCibunar();
        $rw = Organization::where('slug', 'rw-01-cibunar')->firstOrFail();

        $this->actingAs($this->adminPlatform)
            ->delete("/tenant/{$desa->id}")
            ->assertRedirect(route('tenant.index'))->assertSessionHas('error');
        $this->assertNotNull(Organization::find($desa->id));

        $this->actingAs($this->adminPlatform)->delete("/tenant/rw/{$rw->id}");
        $this->actingAs($this->adminPlatform)
            ->delete("/tenant/{$desa->id}")->assertRedirect(route('tenant.index'));
        $this->assertNull(Organization::find($desa->id));
    }

    public function test_aksi_crud_tertutup_untuk_admin_tenant(): void
    {
        $desa = $this->buatCibunar();
        $rw = Organization::where('slug', 'rw-01-cibunar')->firstOrFail();

        $this->actingAs($this->adminTenant)->put("/tenant/{$desa->id}", [
            'nama' => 'Desa Bajakan', 'kecamatan' => 'X',
        ])->assertForbidden();
        $this->actingAs($this->adminTenant)->post("/tenant/rw/{$rw->id}/toggle")->assertForbidden();
        $this->actingAs($this->adminTenant)->delete("/tenant/rw/{$rw->id}")->assertForbidden();
        $this->actingAs($this->adminTenant)->delete("/tenant/{$desa->id}")->assertForbidden();
    }

    public function test_admin_tenant_tidak_bisa_menembak_post_langsung(): void
    {
        $this->actingAs($this->adminTenant)->post('/tenant', [
            'nama' => 'Desa Selundupan', 'label' => 'selundupan', 'rw' => '01',
        ])->assertForbidden();

        $this->assertNull(Organization::where('slug', 'selundupan')->first());
    }
}
