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
 * `php artisan izin:periksa` - jaring pengaman sesudah deploy pembagian tugas.
 * RW yang belum mengangkat sekretaris harus ketahuan dari konsol, bukan dari
 * keluhan warga yang suratnya mengendap.
 */
class PeriksaKapabilitasTest extends TestCase
{
    use RefreshDatabase;

    private function pasang(string $username, string $slug, ?Organization $org = null): void
    {
        $user = User::create([
            'user_id' => "u_{$username}", 'username' => $username,
            'namaLengkap' => ucfirst($username), 'pin' => Hash::make('123456'),
            'level' => 'warga', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'organization_id' => ($org ?? $this->rw())->id,
        ]);
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    public function test_melaporkan_kemampuan_tanpa_pemegang(): void
    {
        // Hanya ada ketua: seluruh pekerjaan sekretaris dan bendahara kosong.
        $this->pasang('ketuaperiksa', 'rw_admin');

        $this->artisan('izin:periksa', ['--rw' => 'rw-10-sukakarya'])
            ->expectsOutputToContain('rw-10-sukakarya')
            ->expectsOutputToContain('surat.buat')
            ->expectsOutputToContain('bukukas.catat')
            ->assertFailed();
    }

    public function test_lolos_bila_seluruh_peran_terisi(): void
    {
        $rt = Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01', 'slug' => 'rt-01-rw-10-sukakarya',
        ]);
        $this->pasang('ketualengkap', 'rw_admin');
        $this->pasang('sekretarislengkap', 'rw_secretary');
        $this->pasang('bendaharalengkap', 'rw_finance');
        $this->pasang('rtlengkap', 'rt_admin', $rt);
        $this->pasang('wargalengkap', 'warga');

        $this->artisan('izin:periksa', ['--rw' => 'rw-10-sukakarya'])
            ->expectsOutputToContain('semua kemampuan ada pemegangnya')
            ->assertSuccessful();
    }

    public function test_akun_nonaktif_tidak_dihitung_sebagai_pemegang(): void
    {
        $this->pasang('sekretarismati', 'rw_secretary');
        User::where('username', 'sekretarismati')->update(['status' => 'nonaktif']);

        $this->artisan('izin:periksa', ['--rw' => 'rw-10-sukakarya'])
            ->expectsOutputToContain('surat.buat')
            ->assertFailed();
    }
}
