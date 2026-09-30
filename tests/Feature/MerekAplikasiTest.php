<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Merek aplikasi (logo, ikon, warna utama) yang bisa diganti dari
 * Pengaturan > Tampilan, per tenant, tanpa menyunting berkas yang dilacak git.
 *
 * Dulu logo/favicon adalah berkas tetap di public/ dan warna hijau ditulis
 * di CSS, sehingga instalasi kedua (demo) tidak bisa punya merek sendiri
 * tanpa mengubah berkas repo dan merusak git pull.
 */
class MerekAplikasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $rw99;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Storage::fake('public');

        $this->rw99 = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
        Domain::create(['organization_id' => $this->rw99->id, 'hostname' => 'rw99.desa.test', 'is_primary' => true]);

        $this->admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_merek', 'username' => 'merekadmin', 'namaLengkap' => 'Admin Merek',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    private function idRw10(): int
    {
        return Organization::where('slug', 'rw-10-sukakarya')->value('id');
    }

    private function milikRw10(string $key): ?string
    {
        // Query langsung hanya untuk asersi: yang diuji baris milik organisasi.
        return AppSetting::where('key', $key)->where('organization_id', $this->idRw10())->value('value');
    }

    private function png(int $lebar, int $tinggi, string $nama = 'logo.png'): UploadedFile
    {
        $gambar = imagecreatetruecolor($lebar, $tinggi);
        ob_start();
        imagepng($gambar);

        return UploadedFile::fake()->createWithContent($nama, ob_get_clean());
    }

    private function simpan(array $isi)
    {
        return $this->actingAs($this->admin)->post('/pengaturan', ['_active_tab' => 'tampilan'] + $isi);
    }

    /** Isi blok <style id="tema-merek"> atau null bila tidak ada. */
    private function blokTema(string $html): ?string
    {
        return preg_match('#<style id="tema-merek">(.*?)</style>#s', $html, $m) ? $m[1] : null;
    }

    // --- bawaan ---------------------------------------------------------------

    public function test_tanpa_pengaturan_tampilan_sama_seperti_sebelumnya(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('logo-sukawarga.svg', $html);
        $this->assertNull($this->blokTema($html), 'tanpa warna kustom, CSS bawaan tidak ditimpa');

        $html = $this->actingAs($this->admin)->get('/')->getContent();
        $this->assertStringContainsString('favicon-32.png', $html);
        $this->assertStringContainsString('content="#0F7A4D"', $html);
    }

    // --- logo ------------------------------------------------------------------

    public function test_unggah_logo_tampil_di_login_dan_sidebar(): void
    {
        $this->simpan(['merek_logo_file' => $this->png(1600, 400)])->assertSessionHasNoErrors();

        $path = $this->milikRw10('merek_logo');
        $this->assertMatchesRegularExpression('#^merek/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame(1024, imagesx(imagecreatefromstring(Storage::disk('public')->get($path))));

        $this->get('/login')->assertSee('storage/'.$path, false);
    }

    public function test_logo_svg_dan_berkas_menyamar_ditolak(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->simpan(['merek_logo_file' => $svg])->assertSessionHasErrors('merek_logo_file');

        $palsu = UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1; ?>');
        $this->simpan(['merek_logo_file' => $palsu])->assertSessionHasErrors('merek_logo_file');

        $this->assertNull($this->milikRw10('merek_logo'));
    }

    public function test_reset_logo_menghapus_berkas(): void
    {
        $this->simpan(['merek_logo_file' => $this->png(300, 100)]);
        $path = $this->milikRw10('merek_logo');

        $this->simpan(['merek_logo_aksi' => 'reset'])->assertSessionHasNoErrors();

        $this->assertSame('', $this->milikRw10('merek_logo'));
        Storage::disk('public')->assertMissing($path);
        $this->get('/login')->assertSee('logo-sukawarga.svg', false);
    }

    // --- ikon ----------------------------------------------------------------------

    public function test_ikon_harus_persegi(): void
    {
        $this->simpan(['merek_ikon_file' => $this->png(600, 300, 'ikon.png')])
            ->assertSessionHasErrors('merek_ikon_file');
    }

    public function test_ikon_persegi_menghasilkan_semua_ukuran_favicon(): void
    {
        $this->simpan(['merek_ikon_file' => $this->png(700, 700, 'ikon.png')])->assertSessionHasNoErrors();

        $dasar = $this->milikRw10('merek_ikon');
        $this->assertMatchesRegularExpression('#^merek/[0-9a-f-]{36}$#', $dasar);
        foreach ([16, 32, 180, 192, 512] as $ukuran) {
            $berkas = "{$dasar}-{$ukuran}.png";
            Storage::disk('public')->assertExists($berkas);
            $this->assertSame($ukuran, imagesx(imagecreatefromstring(Storage::disk('public')->get($berkas))));
        }

        $html = $this->actingAs($this->admin)->get('/')->getContent();
        $this->assertStringContainsString("storage/{$dasar}-32.png", $html);
        $this->assertStringNotContainsString('favicon-32.png', $html);

        // Reset menghapus seluruh varian.
        $this->simpan(['merek_ikon_aksi' => 'reset']);
        foreach ([16, 32, 180, 192, 512] as $ukuran) {
            Storage::disk('public')->assertMissing("{$dasar}-{$ukuran}.png");
        }
    }

    // --- warna ----------------------------------------------------------------------

    public function test_warna_tidak_sah_ditolak(): void
    {
        foreach (['red', '#12345', '#123456;}body{display:none', 'url(x)'] as $warna) {
            $this->simpan(['merek_warna' => $warna])->assertSessionHasErrors('merek_warna');
        }
        $this->assertNull($this->milikRw10('merek_warna_utama'));
    }

    public function test_warna_kontras_rendah_ditolak(): void
    {
        // Kuning muda: teks putih di atasnya tidak terbaca.
        $this->simpan(['merek_warna' => '#FDE047'])->assertSessionHasErrors('merek_warna');
    }

    public function test_warna_sah_menimpa_hanya_token_yang_diizinkan(): void
    {
        $this->simpan(['merek_warna' => '#1d4ed8'])->assertSessionHasNoErrors();
        $this->assertSame('#1D4ED8', $this->milikRw10('merek_warna_utama'));

        foreach (['/login', '/'] as $halaman) {
            $html = $this->actingAs($this->admin)->get($halaman)->getContent();
            $tema = $this->blokTema($html);
            $this->assertNotNull($tema, $halaman);
            $this->assertStringContainsString('--primary:#1D4ED8', $tema);

            // Setiap deklarasi adalah custom property yang dikenal dengan nilai hex.
            preg_match_all('/(--[a-z0-9-]+):([^;}]+)/', $tema, $m, PREG_SET_ORDER);
            $this->assertNotEmpty($m);
            foreach ($m as [, $properti, $nilai]) {
                $this->assertContains($properti, \App\Services\MerekAplikasi::TOKEN_WARNA);
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', trim($nilai));
            }
        }
        $this->get('/login')->assertSee('content="#1D4ED8"', false);
    }

    public function test_warna_tidak_berubah_tidak_membuat_baris(): void
    {
        // Input warna selalu terkirim; menyimpan tab lain tidak boleh
        // "membekukan" warna bawaan sebagai setting milik tenant.
        $this->simpan(['merek_warna' => '#0F7A4D', 'nama_aplikasi' => 'Portal Uji'])->assertSessionHasNoErrors();

        $this->assertNull($this->milikRw10('merek_warna_utama'));
    }

    public function test_reset_warna_kembali_ke_bawaan(): void
    {
        $this->simpan(['merek_warna' => '#1D4ED8']);
        $this->simpan(['merek_warna_aksi' => 'reset']);

        $this->assertNull($this->blokTema($this->get('/login')->getContent()));
    }

    // --- isolasi, manifest, izin -------------------------------------------------------

    public function test_merek_tenant_tidak_bocor_ke_tenant_lain(): void
    {
        $this->simpan(['merek_warna' => '#1D4ED8', 'merek_logo_file' => $this->png(400, 100)]);
        $path = $this->milikRw10('merek_logo');

        $html = $this->get('https://rw99.desa.test/login')->assertOk()->getContent();

        $this->assertNull($this->blokTema($html));
        $this->assertStringNotContainsString($path, $html);
        $this->assertStringContainsString('logo-sukawarga.svg', $html);
    }

    public function test_manifest_mengikuti_nama_warna_dan_ikon(): void
    {
        $bawaan = $this->get('/site.webmanifest')->assertOk()->json();
        $this->assertSame(namaAplikasi(), $bawaan['name']);
        $this->assertSame('#0F7A4D', $bawaan['theme_color']);
        $this->assertStringEndsWith('/icon-192.png', $bawaan['icons'][0]['src']);

        $this->simpan([
            'nama_aplikasi' => 'Portal Uji Merek', 'merek_warna' => '#1D4ED8',
            'merek_ikon_file' => $this->png(512, 512, 'ikon.png'),
        ])->assertSessionHasNoErrors();

        $manifest = $this->get('/site.webmanifest')->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('Portal Uji Merek', $manifest['name']);
        $this->assertSame('#1D4ED8', $manifest['theme_color']);
        $this->assertStringContainsString($this->milikRw10('merek_ikon').'-192.png', $manifest['icons'][0]['src']);
    }

    public function test_tanpa_izin_ubah_pengaturan_ditolak(): void
    {
        $sekretaris = User::create([
            'user_id' => 'u_sek_merek', 'username' => 'sekmerek', 'namaLengkap' => 'Sek',
            'pin' => Hash::make('123456'), 'level' => 'sekretaris', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $sekretaris->id,
            'role_id' => Role::where('legacy_level', 'sekretaris')->where('scope_type', 'rw')->value('id'),
            'organization_id' => $this->idRw10(),
        ]);

        $this->actingAs($sekretaris)->post('/pengaturan', ['merek_warna' => '#1D4ED8'])->assertForbidden();
        $this->assertNull($this->milikRw10('merek_warna_utama'));
    }

    public function test_tab_tampilan_ada_di_pengaturan(): void
    {
        $this->actingAs($this->admin)->get('/pengaturan')
            ->assertOk()
            ->assertSee('id="panelTampilan"', false)
            ->assertSee('for="merek_logo_file"', false)
            ->assertSee('for="merek_ikon_file"', false)
            ->assertSee('for="merek_warna"', false);
    }
}
