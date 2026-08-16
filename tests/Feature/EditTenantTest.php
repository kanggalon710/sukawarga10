<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Edit identitas tenant dari Manajemen Desa: ganti nomor RW (hostname portal
 * ikut berganti, alamat lama jadi alias) dan edit desa dengan kabupaten.
 * Kasus nyata: tenant "RW 07 Bagendit" ternyata RW 10 Kec. Warudoyong
 * Kota Sukabumi - koreksi harus bisa lewat UI, bukan SQL manual.
 */
class EditTenantTest extends TestCase
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

        $this->adminTenant = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_ten', 'username' => 'admintenant', 'namaLengkap' => 'Admin Tenant',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    private function buatCibunar(string $rw = '01'): Organization
    {
        $this->actingAs($this->adminPlatform)->post('/tenant', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            'kecamatan' => 'Tarogong Kidul', 'rw' => $rw,
        ])->assertRedirect(route('tenant.index'));

        return Organization::where('slug', 'cibunar')->firstOrFail();
    }

    private function rw(string $slug = 'rw-01-cibunar'): Organization
    {
        return Organization::where('slug', $slug)->firstOrFail();
    }

    private function gantiNomor(Organization $rw, string $nomor)
    {
        return $this->actingAs($this->adminPlatform)
            ->put("/tenant/rw/{$rw->id}", ['nomor' => $nomor]);
    }

    private function buatKk(string $id, string $nama, int $orgId, array $atribut = []): Keluarga
    {
        $kk = new Keluarga(array_merge([
            'keluarga_id' => $id, 'nama' => $nama,
            'alamat' => 'Jl. Uji', 'rt' => '01', 'status' => 'aktif',
        ], $atribut));
        $kk->organization_id = $orgId;
        $kk->saveQuietly();

        return $kk;
    }

    public function test_ganti_nomor_rw_memutakhirkan_nama_code_dan_domain_primary(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();

        $this->gantiNomor($rw, '10')->assertRedirect(route('tenant.index'));

        $rw->refresh();
        $this->assertSame('RW 10', $rw->name);
        $this->assertSame('CIBUNAR-RW10', $rw->code);
        $this->assertDatabaseHas('domains', [
            'organization_id' => $rw->id, 'hostname' => 'cibunar-rw10.desa.jabnet.id',
            'is_primary' => true, 'status' => 'aktif',
        ]);
    }

    public function test_alamat_lama_jadi_alias_dan_tetap_bisa_dibuka(): void
    {
        $this->buatCibunar();
        $this->gantiNomor($this->rw(), '10');

        $this->assertDatabaseHas('domains', [
            'hostname' => 'cibunar-rw01.desa.jabnet.id',
            'is_primary' => false, 'status' => 'aktif',
        ]);
        $this->get('https://cibunar-rw01.desa.jabnet.id/login')->assertOk();
        $this->get('https://cibunar-rw10.desa.jabnet.id/login')->assertOk();
    }

    public function test_login_warga_lama_tetap_bisa_di_alamat_lama_dan_baru(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();
        $this->buatKk('kk_asep01', 'Asep Sunandar', $rw->id);
        User::create([
            'user_id' => 'u_asep', 'username' => 'asep', 'namaLengkap' => 'Asep Sunandar',
            'pin' => Hash::make('123456'), 'level' => 'warga', 'status' => 'aktif',
            'keluarga_id' => 'kk_asep01',
        ]);

        $this->gantiNomor($rw, '10');
        auth()->logout();

        $this->post('https://cibunar-rw01.desa.jabnet.id/login', [
            'username' => 'asep', 'pin' => '123456',
        ])->assertRedirect();
        $this->assertAuthenticated();

        $this->post('https://cibunar-rw01.desa.jabnet.id/logout');
        $this->assertGuest();

        $this->post('https://cibunar-rw10.desa.jabnet.id/login', [
            'username' => 'asep', 'pin' => '123456',
        ])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_slug_rw_tidak_berubah_saat_nomor_diganti(): void
    {
        // Slug beku by design: slug RT anak (rt-xx-rw-01-cibunar) dan lookup
        // NotificationService/AkunController dibangun darinya.
        $this->buatCibunar();
        $rw = $this->rw();

        $this->gantiNomor($rw, '10');

        $this->assertSame('rw-01-cibunar', $rw->fresh()->slug);
    }

    public function test_backfill_kk_rw_dan_alamat_hanya_untuk_rw_itu(): void
    {
        $this->buatCibunar('01,02');
        $rw01 = $this->rw();
        $rw02 = $this->rw('rw-02-cibunar');
        $kkA = $this->buatKk('kk_edit_a', 'KK Alfa', $rw01->id, [
            'rw' => '01', 'alamat' => 'RT 01 RW 01 Kp. Uji',
        ]);
        $kkB = $this->buatKk('kk_edit_b', 'KK Beta', $rw02->id, [
            'rw' => '02', 'alamat' => 'RT 01 RW 02 Kp. Uji',
        ]);

        $this->gantiNomor($rw01, '10');

        $kkA = Keluarga::withoutGlobalScope('organisasi')->find($kkA->id);
        $kkB = Keluarga::withoutGlobalScope('organisasi')->find($kkB->id);
        $this->assertSame('10', $kkA->rw);
        $this->assertSame('RT 01 RW 10 Kp. Uji', $kkA->alamat);
        $this->assertSame('02', $kkB->rw, 'KK RW tetangga tidak boleh tersentuh.');
        $this->assertSame('RT 01 RW 02 Kp. Uji', $kkB->alamat);
    }

    public function test_setting_nama_rw_dan_alamat_portal_ikut_terkoreksi_bila_ada(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();
        AppSetting::create(['key' => 'nama_rw', 'value' => 'RW 01', 'organization_id' => $rw->id]);
        AppSetting::create(['key' => 'alamat_portal', 'value' => 'cibunar-rw01.desa.jabnet.id', 'organization_id' => $rw->id]);

        $this->gantiNomor($rw, '10');

        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $rw->id, 'key' => 'nama_rw', 'value' => 'RW 10',
        ]);
        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $rw->id, 'key' => 'alamat_portal', 'value' => 'cibunar-rw10.desa.jabnet.id',
        ]);
    }

    public function test_setting_kustom_tidak_disentuh_dan_nama_rw_tidak_diciptakan(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();
        AppSetting::create(['key' => 'alamat_portal', 'value' => 'portal.milik-sendiri.id', 'organization_id' => $rw->id]);

        $this->gantiNomor($rw, '10');

        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $rw->id, 'key' => 'alamat_portal', 'value' => 'portal.milik-sendiri.id',
        ]);
        $this->assertDatabaseMissing('app_settings', [
            'organization_id' => $rw->id, 'key' => 'nama_rw',
        ]);
    }

    public function test_nomor_rw_bentrok_dengan_saudara_ditolak(): void
    {
        $this->buatCibunar('01,02');
        $rw01 = $this->rw();

        // '2' dinormalisasi jadi '02' - bentrok dengan RW 02 yang sudah ada.
        $this->gantiNomor($rw01, '2')->assertSessionHasErrors('nomor');

        $this->assertSame('RW 01', $rw01->fresh()->name);
        $this->assertDatabaseHas('domains', [
            'organization_id' => $rw01->id,
            'hostname' => 'cibunar-rw01.desa.jabnet.id', 'is_primary' => true,
        ]);
    }

    public function test_hostname_baru_milik_organisasi_lain_ditolak(): void
    {
        $desa = $this->buatCibunar();
        $rw = $this->rw();
        Domain::create([
            'organization_id' => $desa->id,
            'hostname' => 'cibunar-rw10.desa.jabnet.id', 'is_primary' => false, 'status' => 'aktif',
        ]);

        $this->gantiNomor($rw, '10')->assertSessionHasErrors('nomor');

        $this->assertSame('RW 01', $rw->fresh()->name);
    }

    public function test_nomor_sama_tidak_mengubah_apa_pun(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();

        $this->gantiNomor($rw, '01')->assertRedirect(route('tenant.index'));

        $this->assertSame('RW 01', $rw->fresh()->name);
        $this->assertSame(1, Domain::where('organization_id', $rw->id)->count());
        $this->assertDatabaseHas('domains', [
            'organization_id' => $rw->id,
            'hostname' => 'cibunar-rw01.desa.jabnet.id', 'is_primary' => true,
        ]);
    }

    public function test_ganti_nomor_kembali_ke_nomor_lama_memakai_domain_lama_lagi(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();

        $this->gantiNomor($rw, '10');
        $this->gantiNomor($rw, '01');

        $this->assertSame('RW 01', $rw->fresh()->name);
        $this->assertSame(2, Domain::where('organization_id', $rw->id)->count(),
            'Ganti-balik memakai baris domain lama, bukan menggandakan.');
        $this->assertDatabaseHas('domains', [
            'organization_id' => $rw->id,
            'hostname' => 'cibunar-rw01.desa.jabnet.id', 'is_primary' => true, 'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('domains', [
            'organization_id' => $rw->id,
            'hostname' => 'cibunar-rw10.desa.jabnet.id', 'is_primary' => false,
        ]);
    }

    public function test_edit_rw_tertutup_untuk_admin_tenant(): void
    {
        $this->buatCibunar();
        $rw = $this->rw();

        $this->actingAs($this->adminTenant)
            ->put("/tenant/rw/{$rw->id}", ['nomor' => '10'])->assertForbidden();

        $this->assertSame('RW 01', $rw->fresh()->name);
    }

    public function test_edit_desa_dengan_kabupaten_menulis_setting_di_org_desa(): void
    {
        $desa = $this->buatCibunar();

        $this->actingAs($this->adminPlatform)->put("/tenant/{$desa->id}", [
            'nama' => 'Desa Cibunar', 'kecamatan' => 'Warudoyong', 'kabupaten' => 'Kota Sukabumi',
        ])->assertRedirect(route('tenant.index'));

        $this->assertSame('Desa Cibunar (Warudoyong)', $desa->fresh()->name);
        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $desa->id, 'key' => 'kabupaten', 'value' => 'Kota Sukabumi',
        ]);
    }

    public function test_edit_desa_menimpa_override_kecamatan_di_subtree_dan_backfill_kk(): void
    {
        $desa = $this->buatCibunar();
        $rw = $this->rw();
        AppSetting::create(['key' => 'kecamatan', 'value' => 'Kecamatan Salah', 'organization_id' => $rw->id]);
        AppSetting::create(['key' => 'kelurahan', 'value' => 'Kelurahan Salah', 'organization_id' => $rw->id]);
        $kk = $this->buatKk('kk_edit_c', 'KK Gamma', $rw->id, [
            'kelurahan' => 'Kelurahan Salah', 'kecamatan' => 'Kecamatan Salah',
        ]);

        $this->actingAs($this->adminPlatform)->put("/tenant/{$desa->id}", [
            'nama' => 'Desa Cibunar', 'kecamatan' => 'Warudoyong',
        ])->assertRedirect(route('tenant.index'));

        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $rw->id, 'key' => 'kecamatan', 'value' => 'Warudoyong',
        ]);
        $this->assertDatabaseHas('app_settings', [
            'organization_id' => $rw->id, 'key' => 'kelurahan', 'value' => 'Desa Cibunar',
        ]);
        $kk = Keluarga::withoutGlobalScope('organisasi')->find($kk->id);
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Warudoyong', $kk->kecamatan);
    }

    public function test_tambah_kk_manual_memakai_wilayah_tenant_bukan_hardcode(): void
    {
        $this->buatCibunar();
        $adminRw = User::where('username', 'cibunar-rw01')->firstOrFail();

        $this->actingAs($adminRw)->post('https://cibunar-rw01.desa.jabnet.id/warga', [
            'nama' => 'KK Uji Wilayah', 'rt' => '01',
            'alamat' => 'Kp. Uji', 'jumlahAnggota' => '3',
        ])->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')
            ->where('nama', 'KK Uji Wilayah')->firstOrFail();
        $this->assertSame('01', $kk->rw, "RW dari tenant, bukan hardcode '10'.");
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Tarogong Kidul', $kk->kecamatan);
    }
}
