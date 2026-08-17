<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Surat;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Surat harus memakai identitas tenant request, bukan peninggalan RW 10:
 * nomor surat sempat hardcode "RW10" dan kop cetak sempat fallback ke
 * "10"/"Warudoyong"/"Kota Sukabumi" untuk semua tenant.
 */
class SuratTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $adminRw10;

    private User $adminRw99;

    private Organization $rwAsing;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->rwAsing = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
        Domain::create([
            'organization_id' => $this->rwAsing->id,
            'hostname' => 'rw99.desa.test', 'is_primary' => true,
        ]);

        $this->adminRw10 = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_sur10', 'username' => 'surat10', 'namaLengkap' => 'Admin Sepuluh',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));

        // Admin tenant RW 99: assignment manual karena pasangPeranSetaraLevel
        // selalu memasang di RW 10.
        $this->adminRw99 = User::create([
            'user_id' => 'u_sur99', 'username' => 'surat99', 'namaLengkap' => 'Admin Sembilan',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminRw99->id,
            'role_id' => Role::where('legacy_level', 'superadmin')->where('scope_type', 'rw')->value('id')
                ?? Role::where('legacy_level', 'superadmin')->value('id'),
            'organization_id' => $this->rwAsing->id,
        ]);
    }

    private function suratDiRw99(): Surat
    {
        return Surat::withoutGlobalScopes()->create([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD',
            'tahun' => date('Y'),
            'nomorUrut' => 1,
            'nomorSurat' => '001/SKD/RW99/'.date('Y'),
            'tanggal' => now()->toDateString(),
            'pemohon' => 'Dede Kurnia',
            'keperluan' => 'Keperluan uji',
            'approval_step' => 'selesai',
            'status' => 'selesai',
            'organization_id' => $this->rwAsing->id,
        ]);
    }

    public function test_nomor_surat_memakai_rw_tenant_bukan_rw10(): void
    {
        $this->actingAs($this->adminRw99)->post('https://rw99.desa.test/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Dede Kurnia', 'keperluan' => 'Uji nomor',
        ])->assertRedirect();

        $nomor = Surat::withoutGlobalScopes()->orderByDesc('id')->value('nomorSurat');
        $this->assertStringContainsString('/RW99/', $nomor);
        $this->assertStringNotContainsString('RW10', $nomor);
    }

    public function test_nomor_surat_tenant_utama_tetap_rw10(): void
    {
        $this->actingAs($this->adminRw10)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji nomor',
        ])->assertRedirect();

        $this->assertStringContainsString(
            '/RW10/',
            Surat::withoutGlobalScopes()->orderByDesc('id')->value('nomorSurat')
        );
    }

    public function test_kop_cetak_tanpa_setting_tidak_memakai_bawaan_sukabumi(): void
    {
        $surat = $this->suratDiRw99();

        $this->actingAs($this->adminRw99)
            ->get("https://rw99.desa.test/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('RUKUN WARGA 99')
            ->assertDontSee('Warudoyong')
            ->assertDontSee('Kota Sukabumi');
    }

    public function test_kop_cetak_memakai_setting_tenant(): void
    {
        AppSetting::create(['key' => 'kecamatan', 'value' => 'Cikole', 'organization_id' => $this->rwAsing->id]);
        AppSetting::create(['key' => 'kabupaten', 'value' => 'Kota Uji', 'organization_id' => $this->rwAsing->id]);
        $surat = $this->suratDiRw99();

        $this->actingAs($this->adminRw99)
            ->get("https://rw99.desa.test/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Cikole')
            ->assertSee('Kota Uji');
    }

    public function test_baris_tanggal_memakai_kelurahan_bila_kabupaten_kosong(): void
    {
        AppSetting::create(['key' => 'kelurahan', 'value' => 'Kelurahan Contoh', 'organization_id' => $this->rwAsing->id]);
        $surat = $this->suratDiRw99();

        $this->actingAs($this->adminRw99)
            ->get("https://rw99.desa.test/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Kelurahan Contoh,')
            ->assertDontSee('Kota Sukabumi');
    }
}
