<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Transaksi;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Pensiunnya sistem izin lama, dikunci supaya tidak diam-diam hidup lagi.
 *
 * Selama hierarki linier (`CheckRole`, `User::LEVEL_POWER`) dan matriks menu
 * lama (`role_permissions`) masih ada di kode dan database, ada dua cara
 * mengecek izin yang bisa memberi jawaban berbeda - dan penulis berikutnya
 * bisa memilih yang salah tanpa ada yang menghentikannya. Satu-satunya penjaga
 * sekarang adalah matriks kapabilitas (`izin:` dan `bolehkah()`).
 */
class PensiunHierarkiLamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function user(string $username, ?string $slug = null): User
    {
        $user = User::create([
            'user_id' => "u_{$username}", 'username' => $username,
            'namaLengkap' => ucfirst($username), 'pin' => Hash::make('123456'),
            'level' => 'warga', 'status' => 'aktif',
        ]);

        if ($slug !== null) {
            UserRoleAssignment::create([
                'user_id' => $user->id,
                'role_id' => Role::where('slug', $slug)->value('id'),
                'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
            ]);
        }

        return $user;
    }

    public function test_alias_middleware_role_sudah_tidak_terdaftar(): void
    {
        $this->assertArrayNotHasKey(
            'role',
            app('router')->getMiddleware(),
            'Alias `role:` masih terdaftar - dua penjaga izin hidup berdampingan.'
        );
    }

    public function test_tidak_ada_rute_yang_masih_memakai_role(): void
    {
        $sisa = [];
        foreach (Route::getRoutes()->getRoutes() as $rute) {
            foreach ($rute->gatherMiddleware() as $m) {
                if (is_string($m) && (str_starts_with($m, 'role:') || $m === 'role')) {
                    $sisa[] = '/'.$rute->uri();
                }
            }
        }

        $this->assertSame([], array_unique($sisa), implode(', ', array_unique($sisa)));
    }

    public function test_kelas_dan_konstanta_hierarki_lama_sudah_hilang(): void
    {
        $this->assertFalse(
            class_exists(\App\Http\Middleware\CheckRole::class),
            'CheckRole masih ada.'
        );
        $this->assertFalse(
            defined(User::class.'::LEVEL_POWER'),
            'User::LEVEL_POWER masih ada - urutan peran kini di MatriksKapabilitas::URUTAN_TAMPIL.'
        );

        // Helper berbasis nama level: penggantinya bolehkah('modul.aksi').
        foreach (['canVoid', 'canManageUsers', 'canManageFinance',
            'isKetuaRW', 'isBendahara', 'isPetugasRT', 'isSuperAdmin'] as $method) {
            $this->assertFalse(
                method_exists(User::class, $method),
                "User::{$method}() masih ada - pakai bolehkah() supaya izin punya satu sumber."
            );
        }
    }

    public function test_tombol_void_di_buku_kas_mengikuti_kapabilitas(): void
    {
        Transaksi::create([
            'transaksi_id' => 'TRX-'.uniqid(),
            'tanggal' => now()->toDateString(),
            'jenis' => 'masuk', 'jumlah' => 50000,
            'keterangan' => 'Setoran uji', 'kategori' => 'lain',
            'kas' => 'rw',
        ]);

        // Ketua memegang transaksi.void; bendahara sengaja tidak (pencatat
        // tidak membatalkan catatannya sendiri).
        $this->actingAs($this->user('ketuavoidkas', 'rw_admin'))
            ->get('http://localhost/bukukas')->assertOk()->assertSee('Void');

        $this->actingAs($this->user('bendaharavoidkas', 'rw_finance'))
            ->get('http://localhost/bukukas')->assertOk()->assertDontSee('openVoid(');
    }

    public function test_setting_role_permissions_dibersihkan_migrasi(): void
    {
        $migrasi = require database_path('migrations/2026_08_18_000002_hapus_setting_role_permissions.php');

        AppSetting::create([
            'key' => 'role_permissions',
            'value' => json_encode(['ketua_rw' => ['akun']]),
            'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
        ]);
        AppSetting::create([
            'key' => 'nama_rw', 'value' => 'RW 10',
            'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
        ]);

        $migrasi->up();

        $this->assertDatabaseMissing('app_settings', ['key' => 'role_permissions']);
        // Setting lain tidak boleh ikut terhapus.
        $this->assertDatabaseHas('app_settings', ['key' => 'nama_rw']);
    }
}
