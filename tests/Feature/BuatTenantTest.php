<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D: perintah `tenant:buat` - satu perintah membuat desa + RW + domain
 * + akun admin per RW, idempotent, dan dua desa bernama sama boleh hidup
 * berdampingan selama labelnya berbeda.
 */
class BuatTenantTest extends TestCase
{
    use RefreshDatabase;

    private function jalankan(string $label, array $rw = ['01'], array $opsi = []): \Illuminate\Testing\PendingCommand|int
    {
        return $this->artisan('tenant:buat', array_merge([
            'nama' => 'Desa Cibunar',
            'label' => $label,
            '--kecamatan' => 'Tarogong Kidul',
            '--rw' => $rw,
        ], $opsi));
    }

    public function test_membuat_desa_rw_domain_dan_admin_lengkap(): void
    {
        $this->jalankan('cibunar', ['01', '02'])->assertSuccessful();

        $desa = Organization::where('slug', 'cibunar')->first();
        $this->assertNotNull($desa);
        $this->assertSame(Organization::TYPE_DESA, $desa->type);
        $this->assertSame('Desa Cibunar (Tarogong Kidul)', $desa->name);
        $this->assertSame(
            Organization::where('slug', 'platform')->value('id'), $desa->parent_id
        );

        $rw01 = Organization::where('slug', 'rw-01-cibunar')->first();
        $this->assertNotNull($rw01);
        $this->assertSame(Organization::TYPE_RW, $rw01->type);
        $this->assertSame($desa->id, $rw01->parent_id);

        $this->assertDatabaseHas('domains', [
            'hostname' => 'cibunar-rw01.desa.jabnet.id',
            'organization_id' => $rw01->id, 'is_primary' => true, 'status' => 'aktif',
        ]);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw02.desa.jabnet.id']);

        // Admin RW: akun + assignment rw_admin di organisasi RW-nya.
        $admin = User::where('username', 'cibunar-rw01')->first();
        $this->assertNotNull($admin);
        $this->assertSame('ketua_rw', $admin->levelEfektifUntuk($rw01));
    }

    public function test_diulang_tidak_menggandakan_dan_tidak_mereset_pin(): void
    {
        $this->jalankan('cibunar')->assertSuccessful();
        $pinAwal = User::where('username', 'cibunar-rw01')->value('pin');

        $this->jalankan('cibunar')->assertSuccessful();

        $this->assertSame(1, Organization::where('slug', 'cibunar')->count());
        $this->assertSame(1, Organization::where('slug', 'rw-01-cibunar')->count());
        $this->assertSame(1, Domain::where('hostname', 'cibunar-rw01.desa.jabnet.id')->count());
        $this->assertSame(1, User::where('username', 'cibunar-rw01')->count());
        $this->assertSame(
            $pinAwal, User::where('username', 'cibunar-rw01')->value('pin'),
            'PIN admin yang sudah ada tidak boleh direset diam-diam.'
        );
        $this->assertSame(
            1, UserRoleAssignment::where(
                'user_id', User::where('username', 'cibunar-rw01')->value('id')
            )->count()
        );
    }

    public function test_dua_desa_bernama_sama_hidup_berdampingan_lewat_label(): void
    {
        $this->jalankan('cibunar', ['01'])->assertSuccessful();
        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunarkota',
            '--kecamatan' => 'Garut Kota', '--rw' => ['01', '05'],
        ])->assertSuccessful();

        $this->assertSame(2, Organization::where('name', 'like', 'Desa Cibunar%')->count());
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw01.desa.jabnet.id']);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunarkota-rw01.desa.jabnet.id']);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunarkota-rw05.desa.jabnet.id']);

        // Dua RW 01 adalah organisasi berbeda di bawah desa masing-masing.
        $this->assertNotSame(
            Organization::where('slug', 'rw-01-cibunar')->value('parent_id'),
            Organization::where('slug', 'rw-01-cibunarkota')->value('parent_id')
        );
    }

    public function test_nomor_rw_dinormalisasi_dua_digit_dan_boleh_koma(): void
    {
        $this->jalankan('cibunar', ['1,2'])->assertSuccessful();

        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw01.desa.jabnet.id']);
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw02.desa.jabnet.id']);
        $this->assertNull(Organization::where('slug', 'rw-1-cibunar')->first());
    }

    public function test_opsi_tanpa_admin_tidak_membuat_akun(): void
    {
        $this->jalankan('cibunar', ['01'], ['--tanpa-admin' => true])->assertSuccessful();

        $this->assertNull(User::where('username', 'cibunar-rw01')->first());
        $this->assertDatabaseHas('domains', ['hostname' => 'cibunar-rw01.desa.jabnet.id']);
    }

    public function test_label_tidak_sah_ditolak(): void
    {
        // Label jadi bagian hostname; huruf besar/spasi/karakter aneh merusak DNS.
        $this->jalankan('Cibunar Kota!')->assertFailed();

        $this->assertSame(0, Organization::where('type', Organization::TYPE_DESA)
            ->where('name', 'like', '%Cibunar%')->count());
    }
}
