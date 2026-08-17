<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\PembaruAplikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Pembaruan Sistem dari website: cek versi terbaru di branch production dan
 * jalankan update satu klik. HANYA untuk admin platform - tombol yang
 * menjalankan git/composer/migrate bukan untuk admin tenant.
 * Seluruh perintah shell dipalsukan lewat Process::fake.
 */
class PembaruanTest extends TestCase
{
    use RefreshDatabase;

    private User $adminPlatform;

    private User $adminTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminPlatform = User::create([
            'user_id' => 'u_upd', 'username' => 'updadmin', 'namaLengkap' => 'Admin Pembaruan',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminPlatform->id,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'platform')->value('id'),
        ]);
        $this->adminTenant = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_updt', 'username' => 'updtenant', 'namaLengkap' => 'Admin Tenant',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
    }

    public function test_halaman_pembaruan_hanya_untuk_admin_platform(): void
    {
        Process::fake(['*' => Process::result('abc1234|2026-08-16|uji')]);

        $this->actingAs($this->adminPlatform)->get('/pembaruan')->assertOk();
        $this->actingAs($this->adminTenant)->get('/pembaruan')->assertForbidden();
    }

    public function test_halaman_menampilkan_versi_terpasang(): void
    {
        Process::fake([
            'git log*' => Process::result("ab12cd3|2026-08-16|Rilis fitur pembaruan\n"),
        ]);

        $this->actingAs($this->adminPlatform)->get('/pembaruan')
            ->assertOk()
            ->assertSee('ab12cd3')
            ->assertSee('Rilis fitur pembaruan');
    }

    public function test_cek_menemukan_pembaruan_dan_menyimpan_status(): void
    {
        Process::fake([
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("2\n"),
            'git log HEAD..*' => Process::result("cc11 Perbaikan tarif\ndd22 Fitur baru\n"),
            'git log*' => Process::result("ab12cd3|2026-08-16|Rilis awal\n"),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/cek')
            ->assertRedirect(route('pembaruan.index'));

        $status = Cache::get(PembaruAplikasi::KUNCI_STATUS);
        $this->assertTrue($status['tersedia']);
        $this->assertSame(2, $status['jumlah']);

        $this->actingAs($this->adminPlatform)->get('/pembaruan')
            ->assertSee('Perbaikan tarif')
            ->assertSee('Fitur baru');
    }

    public function test_cek_saat_sudah_mutakhir(): void
    {
        Process::fake([
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("0\n"),
            'git log*' => Process::result("ab12cd3|2026-08-16|Rilis awal\n"),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/cek')->assertRedirect();

        $this->assertFalse(Cache::get(PembaruAplikasi::KUNCI_STATUS)['tersedia']);
        $this->actingAs($this->adminPlatform)->get('/pembaruan')->assertSee('mutakhir');
    }

    public function test_tombol_perbarui_tampil_walau_belum_pernah_cek(): void
    {
        Process::fake(['git log*' => Process::result("ab12cd3|2026-08-16|Rilis awal\n")]);

        $this->actingAs($this->adminPlatform)->get('/pembaruan')
            ->assertOk()
            ->assertSee('Perbarui Sekarang');
    }

    public function test_jalankan_saat_mutakhir_berhenti_tanpa_langkah_update(): void
    {
        Process::fake([
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("0\n"),
            '*' => Process::result(''),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/jalankan')
            ->assertRedirect(route('pembaruan.index'))
            ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, 'mutakhir'));

        Process::assertDidntRun(fn ($p) => str_contains($p->command, 'git pull'));
        Process::assertDidntRun(fn ($p) => str_contains($p->command, 'migrate --force'));
    }

    public function test_jalankan_update_tanpa_composer_bila_lock_tidak_berubah(): void
    {
        Process::fake([
            'git diff*' => Process::result("app/helpers.php\n"),
            'git pull*' => Process::result("Updating ab12cd3..ee55ff6\n"),
            '*artisan migrate*' => Process::result('Nothing to migrate.'),
            '*artisan*' => Process::result('ok'),
            'git log HEAD..*' => Process::result("ee55 Rilis baru\n"),
            'git log*' => Process::result("ee55ff6|2026-08-16|Rilis baru\n"),
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("1\n"),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/jalankan')
            ->assertRedirect(route('pembaruan.index'));

        Process::assertRan(fn ($p) => str_contains($p->command, 'git pull --ff-only origin production'));
        Process::assertRan(fn ($p) => str_contains($p->command, 'migrate --force'));
        Process::assertDidntRun(fn ($p) => str_contains($p->command, 'composer install'));
        // Status pembaruan dibersihkan setelah update sukses.
        $this->assertFalse(Cache::get(PembaruAplikasi::KUNCI_STATUS)['tersedia']);
    }

    public function test_jalankan_update_dengan_composer_bila_lock_berubah(): void
    {
        Process::fake([
            'git diff*' => Process::result("composer.lock\napp/helpers.php\n"),
            'git pull*' => Process::result("Updating\n"),
            'composer install*' => Process::result('Generating autoload files'),
            '*artisan*' => Process::result('ok'),
            'git log HEAD..*' => Process::result("ee55 Rilis baru\n"),
            'git log*' => Process::result("ee55ff6|2026-08-16|Rilis baru\n"),
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("1\n"),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/jalankan')->assertRedirect();

        Process::assertRan(fn ($p) => str_contains($p->command, 'composer install --no-dev'));
    }

    public function test_pull_gagal_dilaporkan_tanpa_melanjutkan_migrasi(): void
    {
        Process::fake([
            'git diff*' => Process::result(''),
            'git pull*' => Process::result(output: '', errorOutput: 'error: local changes', exitCode: 1),
            'git log HEAD..*' => Process::result("ee55 Rilis baru\n"),
            'git log*' => Process::result("ab12cd3|2026-08-16|Rilis\n"),
            'git fetch*' => Process::result(''),
            'git rev-list*' => Process::result("1\n"),
        ]);

        $this->actingAs($this->adminPlatform)->post('/pembaruan/jalankan')
            ->assertRedirect()->assertSessionHas('error');

        Process::assertDidntRun(fn ($p) => str_contains($p->command, 'migrate --force'));
    }

    public function test_tombol_update_tertutup_untuk_admin_tenant(): void
    {
        Process::fake(['*' => Process::result('')]);

        $this->actingAs($this->adminTenant)->post('/pembaruan/cek')->assertForbidden();
        $this->actingAs($this->adminTenant)->post('/pembaruan/jalankan')->assertForbidden();
    }

    public function test_notifikasi_menu_tampil_bila_ada_pembaruan_tercatat(): void
    {
        Cache::put(PembaruAplikasi::KUNCI_STATUS, [
            'tersedia' => true, 'jumlah' => 2, 'daftar' => [], 'dicek_pada' => now()->toDateTimeString(),
        ]);

        // Admin platform melihat menu + penanda; admin tenant tidak sama sekali.
        $this->actingAs($this->adminPlatform)->get('/')
            ->assertSee('Pembaruan Sistem')
            ->assertSee('pembaruan-badge');
        $this->actingAs($this->adminTenant)->get('/')
            ->assertDontSee('Pembaruan Sistem');
    }
}
