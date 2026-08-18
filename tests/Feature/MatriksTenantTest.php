<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\MatriksKapabilitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Editor matriks kapabilitas per tenant: HANYA admin platform.
 *
 * Pemilik menetapkan pembagian tugas pengurus sebagai bawaan yang tidak bisa
 * diubah sendiri oleh ketua, bendahara, maupun sekretaris. Penyimpanannya
 * berupa DELTA (key -> bool) di `app_settings` milik organisasi RW yang
 * bersangkutan, jadi kapabilitas baru yang ditambahkan rilis berikutnya tetap
 * mengikuti bawaan kode.
 */
class MatriksTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $adminPlatform;

    private User $ketuaRw;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->adminPlatform = User::create([
            'user_id' => 'u_mplat', 'username' => 'mplatform', 'namaLengkap' => 'Admin Platform',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminPlatform->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);

        $this->ketuaRw = User::create([
            'user_id' => 'u_mketua', 'username' => 'mketua', 'namaLengkap' => 'Ketua RW',
            'pin' => Hash::make('123456'), 'level' => 'ketua_rw', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->ketuaRw->id,
            'role_id' => Role::where('slug', 'rw_admin')->value('id'),
            'organization_id' => $this->rw()->id,
        ]);
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    private function url(string $sufiks = ''): string
    {
        return "https://desa.jabnet.id/tenant/rw/{$this->rw()->id}/matriks{$sufiks}";
    }

    public function test_admin_platform_membuka_halaman_matriks(): void
    {
        $this->actingAs($this->adminPlatform)->get($this->url())
            ->assertOk()
            ->assertSee('surat.buat')
            ->assertSee('Sekretaris');
    }

    public function test_pengurus_tenant_tidak_bisa_membuka_maupun_menyimpan(): void
    {
        $this->actingAs($this->ketuaRw)->get($this->url())->assertForbidden();
        $this->actingAs($this->ketuaRw)->post($this->url(), [
            'kapabilitas' => ['ketua_rw' => ['surat.buat' => '1']],
        ])->assertForbidden();

        $this->assertDatabaseMissing('app_settings', ['key' => MatriksKapabilitas::KEY_OVERRIDE]);
    }

    public function test_simpan_hanya_menulis_selisih_dari_bawaan(): void
    {
        // Kirim SELURUH matriks apa adanya kecuali dua perubahan; yang
        // tersimpan hanya dua key itu, bukan salinan penuh.
        $kapabilitas = $this->matriksBawaanSebagaiInput();
        $kapabilitas['ketua_rw']['surat.ttdRt'] = '1';
        unset($kapabilitas['ketua_rw']['transaksi.void']);

        $this->actingAs($this->adminPlatform)->post($this->url(), ['kapabilitas' => $kapabilitas])
            ->assertRedirect();

        $tersimpan = json_decode(AppSetting::where('key', MatriksKapabilitas::KEY_OVERRIDE)
            ->where('organization_id', $this->rw()->id)->value('value'), true);

        $this->assertSame([
            'ketua_rw' => ['surat.ttdRt' => true, 'transaksi.void' => false],
        ], $tersimpan);
    }

    public function test_matriks_yang_sama_dengan_bawaan_tidak_meninggalkan_baris(): void
    {
        $this->actingAs($this->adminPlatform)
            ->post($this->url(), ['kapabilitas' => $this->matriksBawaanSebagaiInput()])
            ->assertRedirect();

        $nilai = AppSetting::where('key', MatriksKapabilitas::KEY_OVERRIDE)
            ->where('organization_id', $this->rw()->id)->value('value');

        $this->assertSame('[]', $nilai, 'Tanpa perubahan seharusnya tidak menyimpan delta apa pun');
    }

    public function test_kapabilitas_platform_ditolak_dengan_pesan(): void
    {
        $kapabilitas = $this->matriksBawaanSebagaiInput();
        $kapabilitas['ketua_rw']['platform.tenant'] = '1';

        $this->actingAs($this->adminPlatform)->post($this->url(), ['kapabilitas' => $kapabilitas])
            ->assertSessionHasErrors('kapabilitas');

        $this->assertDatabaseMissing('app_settings', ['key' => MatriksKapabilitas::KEY_OVERRIDE]);
    }

    public function test_key_tak_dikenal_ditolak_saat_menulis(): void
    {
        $kapabilitas = $this->matriksBawaanSebagaiInput();
        $kapabilitas['ketua_rw']['modul.hantu'] = '1';

        $this->actingAs($this->adminPlatform)->post($this->url(), ['kapabilitas' => $kapabilitas])
            ->assertSessionHasErrors('kapabilitas');
    }

    public function test_perubahan_matriks_tercatat_di_log(): void
    {
        $kapabilitas = $this->matriksBawaanSebagaiInput();
        $kapabilitas['bendahara']['surat.lihat'] = '1';

        $this->actingAs($this->adminPlatform)->post($this->url(), ['kapabilitas' => $kapabilitas])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_matriks']);
    }

    public function test_matriks_satu_rw_tidak_mempengaruhi_rw_lain(): void
    {
        $rwLain = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);

        $kapabilitas = $this->matriksBawaanSebagaiInput();
        $kapabilitas['ketua_rw']['surat.buat'] = '1';
        $this->actingAs($this->adminPlatform)->post($this->url(), ['kapabilitas' => $kapabilitas])
            ->assertRedirect();

        $this->assertDatabaseMissing('app_settings', [
            'key' => MatriksKapabilitas::KEY_OVERRIDE,
            'organization_id' => $rwLain->id,
        ]);
    }

    /** Bentuk input form: peran => [kapabilitas => '1'] untuk yang tercentang. */
    private function matriksBawaanSebagaiInput(): array
    {
        $input = [];
        foreach (MatriksKapabilitas::BAWAAN as $peran => $daftar) {
            foreach ($daftar as $kapabilitas) {
                $input[$peran][$kapabilitas] = '1';
            }
        }

        return $input;
    }
}
