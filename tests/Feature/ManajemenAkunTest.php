<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Manajemen Akun setelah fallback users.level pensiun: hak akses HANYA datang
 * dari assignment (user, peran, organisasi), jadi pembuatan dan pengubahan
 * akun wajib memelihara assignment-nya. Kolom users.level tinggal peran dasar
 * tercatat (tampilan & sasaran notifikasi), bukan sumber otorisasi.
 */
class ManajemenAkunTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_mgr', 'username' => 'mgradmin', 'namaLengkap' => 'Manajer Akun',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    private function buatLewatForm(string $username, string $level, ?string $rt = null): User
    {
        $this->actingAs($this->admin)->post('/akun', [
            'namaLengkap' => 'Akun '.$username, 'username' => $username,
            'pin' => '123456', 'level' => $level, 'rt' => $rt,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return User::where('username', $username)->firstOrFail();
    }

    public function test_membuat_akun_memasang_assignment_di_organisasi_tenant(): void
    {
        $bend = $this->buatLewatForm('bendbaru', 'bendahara');

        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $bend->id,
            'role_id' => Role::where('slug', 'rw_finance')->value('id'),
            'organization_id' => $this->rw()->id,
        ]);
        $this->assertSame('bendahara', $bend->levelEfektifUntuk($this->rw()));
    }

    public function test_membuat_petugas_rt_ditempatkan_di_organisasi_rt(): void
    {
        // RT ditulis '3': harus dinormalisasi '03' mengikuti konvensi seed B1.
        $rt = $this->buatLewatForm('rtbaru', 'petugas_rt', '3');

        $orgRt = Organization::where('slug', 'rt-03-rw-10-sukakarya')->first();
        $this->assertNotNull($orgRt, 'Organisasi RT dibuat bila belum ada.');
        $this->assertSame($this->rw()->id, $orgRt->parent_id);
        $this->assertDatabaseHas('user_role_assignments', [
            'user_id' => $rt->id,
            'role_id' => Role::where('slug', 'rt_admin')->value('id'),
            'organization_id' => $orgRt->id,
        ]);
        $this->assertSame('petugas_rt', $rt->levelEfektifUntuk($this->rw()));
    }

    public function test_membuat_petugas_rt_tanpa_rt_ditolak(): void
    {
        // Tanpa RT, assignment-nya tidak punya organisasi: tolak di batas masuk.
        $this->actingAs($this->admin)->post('/akun', [
            'namaLengkap' => 'RT Tanpa Wilayah', 'username' => 'rtkosong',
            'pin' => '123456', 'level' => 'petugas_rt',
        ])->assertSessionHasErrors('rt');

        $this->assertNull(User::where('username', 'rtkosong')->first());
    }

    public function test_akun_warga_tidak_diberi_assignment(): void
    {
        $warga = $this->buatLewatForm('wargabaru', 'warga');

        // Warga adalah lantai default levelEfektif(); baris assignment untuk
        // itu hanya menambah data tanpa menambah makna.
        $this->assertSame(0, UserRoleAssignment::where('user_id', $warga->id)->count());
        $this->assertSame('warga', $warga->levelEfektif());
    }

    public function test_mengubah_level_menyelaraskan_assignment_tenant(): void
    {
        $akun = $this->buatLewatForm('naikjabatan', 'bendahara');

        $this->actingAs($this->admin)->put("/akun/{$akun->id}", [
            'namaLengkap' => 'Akun naikjabatan', 'level' => 'ketua_rw',
        ])->assertRedirect();

        // Satu peran per tenant: yang lama diganti, bukan ditumpuk.
        $tersisa = UserRoleAssignment::where('user_id', $akun->id)->get();
        $this->assertCount(1, $tersisa);
        $this->assertSame(
            Role::where('slug', 'rw_admin')->value('id'),
            $tersisa->first()->role_id
        );
        $this->assertSame('ketua_rw', $akun->fresh()->levelEfektifUntuk($this->rw()));
    }

    public function test_akun_platform_tak_tersentuh_dari_form_tenant(): void
    {
        // Akun berjangkar platform berada DI LUAR cakupan kelola host RW:
        // form tenant tidak bisa mengubahnya sama sekali (404), apalagi
        // mencabut assignment platformnya.
        $staf = User::create([
            'user_id' => 'u_staf', 'username' => 'stafplat', 'namaLengkap' => 'Staf Platform',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        $diPlatform = UserRoleAssignment::create([
            'user_id' => $staf->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);

        $this->actingAs($this->admin)->put("/akun/{$staf->id}", [
            'namaLengkap' => 'Staf Platform', 'level' => 'warga',
        ])->assertNotFound();

        $this->assertDatabaseHas('user_role_assignments', ['id' => $diPlatform->id]);
        $this->assertSame('superadmin', $staf->fresh()->levelEfektifUntuk($this->rw()));
        $this->assertSame('superadmin', $staf->fresh()->level);
    }

    public function test_menghapus_akun_ikut_menghapus_assignment(): void
    {
        $akun = $this->buatLewatForm('akundihapus', 'bendahara');

        $this->actingAs($this->admin)->delete("/akun/{$akun->id}")->assertRedirect();

        $this->assertNull(User::find($akun->id));
        // Tabel assignment tanpa FK cascade; baris yatim = hak hantu bila id
        // user terpakai ulang.
        $this->assertSame(0, UserRoleAssignment::where('user_id', $akun->id)->count());
    }
}
