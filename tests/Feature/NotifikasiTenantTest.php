<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prasyarat terakhir Phase D: penyaluran notifikasi WA mengikuti assignment
 * ber-scope tenant, bukan kolom users.level yang berlaku lintas tenant.
 * Aduan yang masuk di RW A tidak boleh membunyikan WhatsApp pengurus RW B.
 */
class NotifikasiTenantTest extends TestCase
{
    use RefreshDatabase;

    private Organization $rwAsing;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        // Kredensial gateway di default platform supaya pengiriman benar-benar
        // sampai ke lapisan HTTP dan penerimanya bisa diperiksa.
        AppSetting::create(['key' => 'mpwa_api_key', 'value' => 'kunci-uji']);
        AppSetting::create(['key' => 'mpwa_sender', 'value' => '628000000000']);

        $this->rwAsing = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
    }

    private function userDenganWa(string $username, string $wa, string $level = 'warga'): User
    {
        return User::create([
            'user_id' => 'u_'.$username, 'username' => $username,
            'namaLengkap' => ucfirst($username), 'pin' => Hash::make('123456'),
            'level' => $level, 'status' => 'aktif', 'wa' => $wa,
        ]);
    }

    private function pasang(User $user, string $slug, Organization $org): void
    {
        UserRoleAssignment::create([
            'user_id' => $user->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'organization_id' => $org->id,
        ]);
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    private function terkirimKe(string $wa): bool
    {
        foreach (Http::recorded() as [$request]) {
            if (($request['number'] ?? null) === $wa) {
                return true;
            }
        }

        return false;
    }

    public function test_notifikasi_pengurus_hanya_menyasar_pengurus_tenant_ini(): void
    {
        $lokal = $this->userDenganWa('penguruslokal', '628110000001');
        $this->pasang($lokal, 'rw_admin', $this->rw());
        $asing = $this->userDenganWa('pengurusasing', '628110000002');
        $this->pasang($asing, 'rw_admin', $this->rwAsing);
        // Kolom level tanpa assignment tidak lagi menjadikan seseorang sasaran.
        $this->userDenganWa('kolomsaja', '628110000003', 'ketua_rw');

        $this->get('/login')->assertOk(); // context tenant: RW 10

        NotificationService::notifyPengurus('uji pesan pengurus');

        $this->assertTrue($this->terkirimKe('628110000001'), 'Pengurus tenant ini harus menerima.');
        $this->assertFalse($this->terkirimKe('628110000002'), 'Pengurus tenant lain tidak boleh menerima.');
        $this->assertFalse($this->terkirimKe('628110000003'), 'Kolom level tanpa assignment bukan sasaran.');
    }

    public function test_super_admin_platform_ikut_menerima_notifikasi_pengurus(): void
    {
        $staf = $this->userDenganWa('stafplatform', '628110000004');
        $this->pasang($staf, 'super_admin', Organization::where('slug', 'platform')->first());

        $this->get('/login')->assertOk();

        NotificationService::notifyPengurus('uji pesan');

        // Platform adalah leluhur tenant ini; pemegang kuasanya relevan.
        $this->assertTrue($this->terkirimKe('628110000004'));
    }

    public function test_notifikasi_per_level_mengikuti_assignment(): void
    {
        $bendLokal = $this->userDenganWa('bendlokal', '628110000005');
        $this->pasang($bendLokal, 'rw_finance', $this->rw());
        $bendAsing = $this->userDenganWa('bendasing', '628110000006');
        $this->pasang($bendAsing, 'rw_finance', $this->rwAsing);

        $this->get('/login')->assertOk();

        NotificationService::notifyByLevel('bendahara', 'uji pesan bendahara');

        $this->assertTrue($this->terkirimKe('628110000005'));
        $this->assertFalse($this->terkirimKe('628110000006'));
    }

    public function test_notifikasi_rt_menyasar_pengurus_rt_organisasi_tenant(): void
    {
        $rtLokal = Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01L', 'slug' => 'rt-01-rw-10-sukakarya',
        ]);
        $rtAsing = Organization::create([
            'parent_id' => $this->rwAsing->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01A', 'slug' => 'rt-01-rw-99-sukakarya',
        ]);
        $petugasLokal = $this->userDenganWa('rtlokal', '628110000007');
        $this->pasang($petugasLokal, 'rt_admin', $rtLokal);
        $petugasAsing = $this->userDenganWa('rtasing', '628110000008');
        $this->pasang($petugasAsing, 'rt_admin', $rtAsing);
        $ketua = $this->userDenganWa('ketualokal', '628110000009');
        $this->pasang($ketua, 'rw_admin', $this->rw());

        $this->get('/login')->assertOk();

        // RT dikirim '1': normalisasi dua digit harus konsisten dengan seed B1.
        NotificationService::notifyRT('1', 'uji pesan rt');

        $this->assertTrue($this->terkirimKe('628110000007'), 'Petugas RT 01 tenant ini menerima.');
        $this->assertFalse($this->terkirimKe('628110000008'), 'Petugas RT 01 tenant lain tidak.');
        $this->assertTrue($this->terkirimKe('628110000009'), 'Ketua RW tetap ditembusi seperti semula.');
    }

    public function test_di_konsol_jatuh_ke_kolom_level(): void
    {
        // Jalur tanpa request (importer/perintah artisan) belum ber-tenant;
        // penyaluran lama berbasis kolom dipertahankan sampai fase queue (G).
        $this->userDenganWa('konsolrw', '628110000010', 'ketua_rw');

        NotificationService::notifyPengurus('uji pesan konsol');

        $this->assertTrue($this->terkirimKe('628110000010'));
    }
}
