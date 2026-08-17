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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kop surat per tenant: logo kop (upload/hapus/reset lewat Pengaturan) dan
 * field alamat_rw yang selama ini dibaca halaman cetak tapi tak punya form.
 * Satu key kop_logo bernilai tiga status: '' = logo bawaan, 'kop/...' = logo
 * upload, sentinel 'tanpa-logo' = kop tanpa logo. Path TIDAK pernah datang
 * dari klien (kop_logo bukan anggota whitelist form).
 */
class PengaturanKopTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_kop1', 'username' => 'kopadmin', 'namaLengkap' => 'Kop Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));

        // Admin tenant RW 99: assignment manual karena pasangPeranSetaraLevel
        // selalu memasang di RW 10.
        $this->adminRw99 = User::create([
            'user_id' => 'u_kop99', 'username' => 'kop99', 'namaLengkap' => 'Kop Sembilan',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminRw99->id,
            'role_id' => Role::where('legacy_level', 'superadmin')->where('scope_type', 'rw')->value('id')
                ?? Role::where('legacy_level', 'superadmin')->value('id'),
            'organization_id' => $this->rwAsing->id,
        ]);
    }

    private function idRw10(): int
    {
        return Organization::where('slug', 'rw-10-sukakarya')->value('id');
    }

    private function surat(array $extra = []): Surat
    {
        // organization_id eksplisit: setUp membuat RW kedua sehingga fallback
        // MilikOrganisasi (isi otomatis hanya bila tepat satu RW) tidak jalan.
        return Surat::create(array_merge([
            'organization_id' => $this->idRw10(),
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD',
            'tahun' => date('Y'),
            'nomorUrut' => 1,
            'nomorSurat' => '001/SKD/RW10/'.date('Y'),
            'tanggal' => now()->toDateString(),
            'pemohon' => 'Asep Suhendar',
            'keperluan' => 'Keperluan uji',
            'approval_step' => 'selesai',
            'status' => 'selesai',
        ], $extra));
    }

    /**
     * PNG 1x1 asli (bukan UploadedFile::fake()->image(): butuh ekstensi GD
     * yang tidak terpasang di mesin uji). Rule `image` memvalidasi lewat
     * fileinfo, jadi byte PNG sungguhan tetap lolos.
     */
    private function filePng(string $nama): \Illuminate\Http\Testing\File
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($nama, $png);
    }

    private function nilaiKopLogoRw10(): ?string
    {
        // Query langsung by-key hanya untuk asersi tes: yang diuji justru baris
        // MILIK organisasi, bukan nilai efektif hasil pewarisan.
        return AppSetting::where('key', 'kop_logo')
            ->where('organization_id', $this->idRw10())->value('value');
    }

    public function test_alamat_rw_tersimpan_dan_tampil_di_kop_cetak(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info', 'alamat_rw' => 'Jl. Melati No. 7, Kp. Cibolang',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'alamat_rw', 'value' => 'Jl. Melati No. 7, Kp. Cibolang',
            'organization_id' => $this->idRw10(),
        ]);

        $surat = $this->surat();
        $this->actingAs($this->admin)->get("/surat/{$surat->id}/cetak")
            ->assertOk()
            ->assertSee('Jl. Melati No. 7, Kp. Cibolang');
    }

    public function test_upload_logo_png_tersimpan_di_direktori_kop(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info',
            'kop_logo_file' => $this->filePng('logo-rw.png'),
        ])->assertRedirect(route('pengaturan.index', ['tab' => 'info']));

        $path = $this->nilaiKopLogoRw10();
        $this->assertNotNull($path);
        $this->assertStringStartsWith('kop/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_svg_ditolak(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info',
            'kop_logo_file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('kop_logo_file');

        $this->assertNull($this->nilaiKopLogoRw10());
    }

    public function test_upload_melebihi_1mb_ditolak(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info',
            'kop_logo_file' => $this->filePng('besar.png')->size(2048),
        ])->assertSessionHasErrors('kop_logo_file');

        $this->assertNull($this->nilaiKopLogoRw10());
    }

    public function test_aksi_hapus_menyimpan_sentinel_dan_menghapus_file_lama(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('kop/lama.png', 'isi-lama');
        AppSetting::create([
            'key' => 'kop_logo', 'value' => 'kop/lama.png',
            'organization_id' => $this->idRw10(),
        ]);

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info', 'kop_logo_aksi' => 'hapus',
        ])->assertRedirect();

        $this->assertSame('tanpa-logo', $this->nilaiKopLogoRw10());
        Storage::disk('public')->assertMissing('kop/lama.png');
    }

    public function test_aksi_reset_mengembalikan_logo_bawaan_dan_menghapus_file_lama(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('kop/lama.png', 'isi-lama');
        AppSetting::create([
            'key' => 'kop_logo', 'value' => 'kop/lama.png',
            'organization_id' => $this->idRw10(),
        ]);

        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info', 'kop_logo_aksi' => 'reset',
        ])->assertRedirect();

        $this->assertSame('', $this->nilaiKopLogoRw10());
        Storage::disk('public')->assertMissing('kop/lama.png');
    }

    public function test_field_kop_logo_mentah_dari_klien_diabaikan(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info', 'kop_logo' => '../../evil.php',
        ])->assertRedirect();

        $this->assertSame(0, AppSetting::where('key', 'kop_logo')->count());
    }

    /**
     * Asersi menyasar <img> kop (class="kop-logo") secara spesifik: layout
     * induk juga memuat logo-sukawarga-icon.svg (favicon + sidebar), jadi
     * assertDontSee global terhadap nama file itu mustahil lulus.
     */
    private function imgKop(string $html): ?string
    {
        return preg_match('/<img[^>]*class="kop-logo"[^>]*>/', $html, $m) ? $m[0] : null;
    }

    public function test_cetak_memakai_logo_bawaan_tanpa_setting(): void
    {
        $surat = $this->surat();

        $html = $this->actingAs($this->admin)->get("/surat/{$surat->id}/cetak")
            ->assertOk()->getContent();

        $this->assertNotNull($this->imgKop($html));
        $this->assertStringContainsString('logo-sukawarga-icon.svg', $this->imgKop($html));
    }

    public function test_cetak_memakai_logo_upload_bila_ada(): void
    {
        AppSetting::create([
            'key' => 'kop_logo', 'value' => 'kop/logo-rw.png',
            'organization_id' => $this->idRw10(),
        ]);
        $surat = $this->surat();

        $html = $this->actingAs($this->admin)->get("/surat/{$surat->id}/cetak")
            ->assertOk()->getContent();

        $this->assertNotNull($this->imgKop($html));
        $this->assertStringContainsString('storage/kop/logo-rw.png', $this->imgKop($html));
        $this->assertStringNotContainsString('logo-sukawarga-icon.svg', $this->imgKop($html));
    }

    public function test_cetak_tanpa_logo_bila_sentinel(): void
    {
        AppSetting::create([
            'key' => 'kop_logo', 'value' => 'tanpa-logo',
            'organization_id' => $this->idRw10(),
        ]);
        $surat = $this->surat();

        $html = $this->actingAs($this->admin)->get("/surat/{$surat->id}/cetak")
            ->assertOk()->getContent();

        $this->assertNull($this->imgKop($html), 'Kop tidak boleh memuat <img> saat sentinel tanpa-logo.');
    }

    public function test_logo_tenant_lain_tidak_bocor_ke_tenant_ini(): void
    {
        Storage::fake('public');

        // Surat RW 99 dibuat SEBELUM request pertama: setelah ada request,
        // TenantContext tertinggal aktif dan MilikOrganisasi menimpa
        // organization_id kiriman dengan tenant context (RW 10).
        $suratRw99 = Surat::withoutGlobalScopes()->create([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD', 'tahun' => date('Y'), 'nomorUrut' => 1,
            'nomorSurat' => '001/SKD/RW99/'.date('Y'),
            'tanggal' => now()->toDateString(), 'pemohon' => 'Dede Kurnia',
            'keperluan' => 'Keperluan uji', 'approval_step' => 'selesai',
            'status' => 'selesai', 'organization_id' => $this->rwAsing->id,
        ]);

        // Upload di tenant RW 10 (host localhost).
        $this->actingAs($this->admin)->post('/pengaturan', [
            '_active_tab' => 'info',
            'kop_logo_file' => $this->filePng('logo-rw.png'),
        ])->assertRedirect();

        $this->assertDatabaseHas('app_settings', [
            'key' => 'kop_logo', 'organization_id' => $this->idRw10(),
        ]);

        // Cetak di tenant RW 99 tetap logo bawaan.
        $html = $this->actingAs($this->adminRw99)
            ->get("https://rw99.desa.test/surat/{$suratRw99->id}/cetak")
            ->assertOk()->getContent();

        $this->assertStringContainsString('logo-sukawarga-icon.svg', $this->imgKop($html));
        $this->assertStringNotContainsString('storage/kop/', $html);
    }
}
