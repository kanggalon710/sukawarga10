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
 * Phase E1 multi-tenant: peran + scope menggantikan level global.
 * Prinsipnya: hak HANYA dari assignment yang relevan dengan tenant request;
 * tanpa assignment lantainya warga (fallback users.level dicabut 2026-08-16);
 * assignment di tenant LAIN tidak memberi hak apa-apa di sini.
 */
class PeranScopeTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $level, string $username): User
    {
        return User::create([
            'user_id' => "u_{$username}", 'username' => $username,
            'namaLengkap' => ucfirst($username), 'pin' => Hash::make('123456'),
            'level' => $level, 'status' => 'aktif',
        ]);
    }

    private function pasang(User $user, string $slug, Organization $org): void
    {
        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'organization_id' => $org->id,
        ]);
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    public function test_katalog_peran_tertanam_dengan_padanan_level(): void
    {
        $peta = Role::pluck('legacy_level', 'slug');

        $this->assertSame('superadmin', $peta['super_admin']);
        $this->assertSame('ketua_rw', $peta['rw_admin']);
        $this->assertSame('bendahara', $peta['rw_finance']);
        $this->assertSame('sekretaris', $peta['rw_secretary']);
        $this->assertSame('petugas_rt', $peta['rt_admin']);
        $this->assertSame('warga', $peta['warga']);
        // Tidak boleh ada peran bernama desa/RW tertentu (§8 anti-pattern).
        $this->assertTrue(Role::all()->every(fn ($r) => ! str_contains($r->slug, 'sukakarya')));
    }

    public function test_assignment_memberi_hak_melebihi_level_lama(): void
    {
        // Level tersimpan warga, tapi dipasang rw_admin di tenant ini:
        // assignment yang menentukan, bukan kolom level.
        $user = $this->buatUser('warga', 'naikperan');
        $this->pasang($user, 'rw_admin', $this->rw());

        $this->actingAs($user)->get('/pengaturan')->assertOk();
    }

    public function test_assignment_di_tenant_lain_tidak_berlaku_di_sini(): void
    {
        $rwLain = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);

        $user = $this->buatUser('warga', 'adminsana');
        $this->pasang($user, 'rw_admin', $rwLain);

        // rw_admin di RW 99 tetap warga biasa di tenant RW 10.
        $this->actingAs($user)->get('/pengaturan')->assertForbidden();
    }

    public function test_super_admin_platform_berlaku_di_tenant_bawahannya(): void
    {
        $user = $this->buatUser('warga', 'stafplatform');
        $this->pasang($user, 'super_admin', Organization::where('slug', 'platform')->first());

        // Platform adalah leluhur RW 10, jadi assignment-nya relevan di sini.
        $this->actingAs($user)->get('/akun')->assertOk();
    }

    public function test_rt_admin_berlaku_di_tenant_rw_induknya(): void
    {
        $rt = Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01', 'slug' => 'rt-01-rw-10-sukakarya',
        ]);
        $user = $this->buatUser('warga', 'pengurusrt');
        $this->pasang($user, 'rt_admin', $rt);

        // /search dijaga izin:warga.cari; rt_admin di RT bawahan RW 10 lolos.
        $this->actingAs($user)->get('/search?q=uji')->assertOk();
        // Tapi tidak ikut naik jadi pengurus RW.
        $this->actingAs($user)->get('/pengaturan')->assertForbidden();
    }

    public function test_kolom_level_tanpa_assignment_tidak_memberi_hak(): void
    {
        // Jembatan transisi fallback users.level sudah dicabut: tanpa
        // assignment, kolom level hanyalah catatan - user berhak sebagai warga.
        $user = $this->buatUser('ketua_rw', 'levellama');

        $this->actingAs($user)->get('/pengaturan')->assertForbidden();
        $this->actingAs($user)->get('/akun')->assertForbidden();
        // Akunnya sendiri tetap hidup di lantai warga.
        $this->actingAs($user)->get('/aduan')->assertOk();
    }

    public function test_diambil_yang_terkuat_bila_assignment_lebih_dari_satu(): void
    {
        $rt = Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 02', 'code' => 'RT02', 'slug' => 'rt-02-rw-10-sukakarya',
        ]);
        $user = $this->buatUser('warga', 'duaperan');
        $this->pasang($user, 'rt_admin', $rt);
        $this->pasang($user, 'rw_admin', $this->rw());

        $this->assertSame('ketua_rw', $user->levelEfektifUntuk($this->rw()));
    }

    public function test_daftar_surat_mengikuti_level_efektif_bukan_kolom_level(): void
    {
        $pemilik = $this->buatUser('warga', 'pemiliksurat');
        \App\Models\Surat::create([
            'surat_id' => 'SRT-peran01', 'kodeSurat' => 'SKD', 'tahun' => date('Y'),
            'nomorUrut' => 1, 'nomorSurat' => '001/SKD/RW10/'.date('Y'),
            'tanggal' => now()->toDateString(), 'pemohon' => 'Pemohon Orang Lain',
            'keperluan' => 'uji', 'user_id' => $pemilik->id,
            'approval_step' => 'diajukan', 'status' => 'draft',
        ]);

        $user = $this->buatUser('warga', 'diangkatsurat');
        $this->pasang($user, 'rw_admin', $this->rw());

        // Pengurus efektif melihat seluruh surat, bukan hanya miliknya.
        $this->actingAs($user)->get('/surat')->assertOk()->assertSee('Pemohon Orang Lain');
    }

    public function test_daftar_aduan_mengikuti_level_efektif_bukan_kolom_level(): void
    {
        $pemilik = $this->buatUser('warga', 'pemilikaduan');
        \App\Models\Aduan::create([
            'aduan_id' => 'ADU-peran01', 'tanggal' => now()->toDateString(),
            'pelapor' => 'Pelapor Lain', 'rt' => '01', 'user_id' => $pemilik->id,
            'isi' => 'Aduan milik orang lain', 'status' => 'masuk',
        ]);

        $user = $this->buatUser('warga', 'diangkataduan');
        $this->pasang($user, 'rw_admin', $this->rw());

        $this->actingAs($user)->get('/aduan')->assertOk()->assertSee('Aduan milik orang lain');
    }

    public function test_header_dan_label_peran_mengikuti_level_efektif(): void
    {
        $user = $this->buatUser('warga', 'diangkatrw');
        $this->pasang($user, 'rw_admin', $this->rw());

        // Kotak pencarian pengurus tampil (rutenya memang sudah lolos untuknya),
        // dan label peran di sidebar menunjukkan peran efektif, bukan kolom level.
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Cari warga...')
            ->assertSee('Ketua RW');
    }

    public function test_seeder_memasang_super_admin_untuk_akun_bawaan(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame('superadmin', $admin->levelEfektifUntuk($this->rw()));

        // Diulang tidak menggandakan assignment.
        $this->seed();
        $this->assertSame(1, UserRoleAssignment::where('user_id', $admin->id)->count());
    }
}
