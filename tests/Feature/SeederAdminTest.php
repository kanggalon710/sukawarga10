<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P0 kredensial seeder.
 *
 * Dulu DatabaseSeeder menulis PIN `463696` untuk `admin` dan `jabnet` di repo
 * PUBLIK, jadi setiap instalasi baru punya akun super admin dengan PIN yang
 * diketahui siapa pun. Sekarang satu admin platform dibuat dari
 * ADMIN_USERNAME/ADMIN_PIN, atau PIN acak yang dicetak sekali ke konsol.
 */
class SeederAdminTest extends TestCase
{
    use RefreshDatabase;

    private function seedDengan(?string $username, ?string $pin): string
    {
        config(['app.admin_awal' => ['username' => $username, 'pin' => $pin]]);
        Artisan::call('db:seed', ['--force' => true]);

        return Artisan::output();
    }

    private function adminPlatform(): ?User
    {
        $roleId = Role::where('slug', 'super_admin')->value('id');
        $platformId = Organization::where('slug', 'platform')->value('id');
        $userId = UserRoleAssignment::where('role_id', $roleId)
            ->where('organization_id', $platformId)->value('user_id');

        return $userId ? User::find($userId) : null;
    }

    public function test_tanpa_username_seeder_gagal_jelas(): void
    {
        try {
            $this->seedDengan(null, null);
            $this->fail('Seeder seharusnya menolak tanpa ADMIN_USERNAME.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ADMIN_USERNAME', $e->getMessage());
        }

        $this->assertSame(0, User::count());
    }

    public function test_membuat_admin_dari_env(): void
    {
        $keluaran = $this->seedDengan('operatorbaru', '482917');

        $admin = $this->adminPlatform();
        $this->assertNotNull($admin);
        $this->assertSame('operatorbaru', $admin->username);
        $this->assertTrue(Hash::check('482917', $admin->pin));
        $this->assertSame(1, User::count());
        $this->assertTrue((bool) $admin->isDefault, 'admin pertama dilindungi dari nonaktif/hapus');
        $this->assertStringNotContainsString('482917', $keluaran, 'PIN dari env tidak perlu dicetak');
    }

    public function test_tanpa_pin_dibuat_acak_dan_dicetak_sekali(): void
    {
        $keluaran = $this->seedDengan('operatorbaru', null);

        $this->assertMatchesRegularExpression('/PIN awal: (\d{6})/', $keluaran);
        preg_match('/PIN awal: (\d{6})/', $keluaran, $m);
        $this->assertTrue(Hash::check($m[1], $this->adminPlatform()->pin));
        $this->assertNotSame('463696', $m[1]);

        // Seed ulang tidak mencetak (apalagi mengganti) PIN lagi.
        $lagi = $this->seedDengan('operatorbaru', null);
        $this->assertDoesNotMatchRegularExpression('/PIN awal: \d{6}/', $lagi);
        $this->assertTrue(Hash::check($m[1], $this->adminPlatform()->fresh()->pin));
    }

    public function test_seed_ulang_tidak_mereset_pin_produksi(): void
    {
        $this->seedDengan('operatorbaru', '482917');
        $admin = $this->adminPlatform();
        $admin->forceFill(['pin' => Hash::make('730146')])->save();

        $this->seedDengan('operatorbaru', '591028');
        $this->seedDengan('operatorlain', '591028');

        $this->assertTrue(Hash::check('730146', $admin->fresh()->pin));
        $this->assertSame(1, User::count(), 'tidak ada akun kedua dari seed ulang');
        $this->assertSame(1, UserRoleAssignment::where('user_id', $admin->id)->count());
    }

    public function test_pin_lemah_ditolak(): void
    {
        foreach (['123456', '000000', '654321', '12345', 'abcdef'] as $pin) {
            try {
                $this->seedDengan('operatorbaru', $pin);
                $this->fail("PIN {$pin} seharusnya ditolak.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('ADMIN_PIN', $e->getMessage());
            }
        }
        $this->assertSame(0, User::count());
    }

    public function test_username_tidak_sah_ditolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->seedDengan('admin baru; drop', '482917');
    }

    public function test_tidak_ada_pin_literal_di_seeder(): void
    {
        $isi = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('463696', $isi);
        $this->assertDoesNotMatchRegularExpression("/'pin'\s*=>\s*'\d{6}'/", $isi);
    }
}
