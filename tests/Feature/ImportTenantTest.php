<?php

namespace Tests\Feature;

use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Import/template KK dan persetujuan pendaftaran harus mengikuti WILAYAH
 * TENANT request - dulu default-nya menulis "RW 10 Sukakarya Tarogong Kidul"
 * sebagai data untuk semua tenant.
 */
class ImportTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $adminCibunar;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01'],
        ])->assertSuccessful();

        $this->adminCibunar = User::create([
            'user_id' => 'u_imp', 'username' => 'impadmin', 'namaLengkap' => 'Imp Admin',
            'pin' => Hash::make('123456'), 'level' => 'ketua_rw', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminCibunar->id,
            'role_id' => Role::where('slug', 'rw_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);
    }

    public function test_import_kk_memakai_wilayah_tenant_untuk_kolom_kosong(): void
    {
        $csv = "Nama KK,RT,RW,Kelurahan/Kecamatan,Alamat\n".
               "Asep Impor,01,,,\n";
        $berkas = UploadedFile::fake()->createWithContent('keluarga.csv', $csv);

        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => $berkas,
            ])->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')->where('nama', 'Asep Impor')->firstOrFail();
        $this->assertSame(
            Organization::where('slug', 'rw-01-cibunar')->value('id'),
            $kk->organization_id, 'Baris impor harus tercap tenant pengimpor.'
        );
        $this->assertSame('01', $kk->rt, "RT kanonik dua digit polos, bukan 'RT 01'.");
        $this->assertSame('01', $kk->rw, 'RW default harus dari tenant, bukan 10.');
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Tarogong Kidul', $kk->kecamatan);
        $this->assertStringNotContainsString('Sukakarya', (string) $kk->alamat);
    }

    public function test_template_impor_memakai_contoh_wilayah_tenant(): void
    {
        $respons = $this->actingAs($this->adminCibunar)
            ->get('https://cibunar-rw01.desa.jabnet.id/warga/template/keluarga');

        $respons->assertOk();
        $isi = $respons->streamedContent();
        $this->assertStringContainsString('Desa Cibunar/Tarogong Kidul', $isi);
        $this->assertStringNotContainsString('Sukakarya', $isi);
    }

    public function test_pendaftaran_disetujui_menulis_wilayah_tenant(): void
    {
        // Pendaftaran masuk lewat form publik tenant Cibunar.
        $this->post('https://cibunar-rw01.desa.jabnet.id/login/register', [
            'nik' => '3205111122223333', 'no_kk' => '3205111122224444',
            'nama_lengkap' => 'Calon Cibunar', 'rt' => '01',
        ])->assertRedirect();
        $daftar = Pendaftaran::withoutGlobalScope('organisasi')
            ->where('nik', '3205111122223333')->firstOrFail();

        $this->actingAs($this->adminCibunar)
            ->post("https://cibunar-rw01.desa.jabnet.id/pendaftaran/{$daftar->id}/approve")
            ->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')
            ->where('nik', '3205111122223333')->firstOrFail();
        $this->assertSame('01', $kk->rw);
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Tarogong Kidul', $kk->kecamatan);
        $this->assertStringNotContainsString('Sukakarya', (string) $kk->alamat);
        $this->assertStringContainsString('RT 01', (string) $kk->alamat);
    }

    public function test_setting_kelurahan_tenant_menimpa_turunan_nama_desa(): void
    {
        \App\Models\AppSetting::create([
            'key' => 'kelurahan', 'value' => 'Kelurahan Cibunar Asli',
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);

        $csv = "Nama KK,RT,Kelurahan/Kecamatan\nUjang Setting,02,\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => UploadedFile::fake()->createWithContent('k.csv', $csv),
            ])->assertRedirect();

        $this->assertSame(
            'Kelurahan Cibunar Asli',
            Keluarga::withoutGlobalScope('organisasi')->where('nama', 'Ujang Setting')->value('kelurahan')
        );
    }
}
