<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Penjaga peran Broadcast WA.
 *
 * Sampai matriks kapabilitas dipasang, seluruh /mpwa/* hanya dijaga `auth` +
 * feature flag: akun warga bisa mem-broadcast WhatsApp ke seluruh KK tenant
 * dan mengubah aturan notifikasi (`saveAturan` menulis `notif_*` ke
 * app_settings). Biaya gateway dan reputasi nomor RW ada di ujungnya.
 */
class MpwaOtorisasiTest extends TestCase
{
    use RefreshDatabase;

    private const MENULIS = [
        '/mpwa/broadcast',
        '/mpwa/test',
        '/mpwa/template/save',
        '/mpwa/template/delete',
        '/mpwa/aturan/save',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function user(string $username, ?string $slug = null, ?Organization $org = null): User
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
                'organization_id' => ($org ?? $this->rw())->id,
            ]);
        }

        return $user;
    }

    private function rw(): Organization
    {
        return Organization::where('slug', 'rw-10-sukakarya')->first();
    }

    public function test_warga_ditolak_di_seluruh_jalur_mpwa(): void
    {
        $warga = $this->user('wargawa');

        $this->actingAs($warga)->get('/mpwa')->assertForbidden();
        foreach (self::MENULIS as $uri) {
            $this->actingAs($warga)->post($uri)->assertForbidden("Warga masih bisa POST {$uri}");
        }
    }

    public function test_petugas_rt_dan_bendahara_tidak_boleh_broadcast(): void
    {
        $rtOrg = Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 03', 'code' => 'RT03', 'slug' => 'rt-03-rw-10-sukakarya',
        ]);

        foreach ([$this->user('rtwa', 'rt_admin', $rtOrg), $this->user('bendaharawa', 'rw_finance')] as $user) {
            $this->actingAs($user)->post('/mpwa/broadcast')->assertForbidden();
            $this->actingAs($user)->post('/mpwa/aturan/save')->assertForbidden();
        }
    }

    public function test_sekretaris_memegang_broadcast(): void
    {
        $sekretaris = $this->user('sekretariswa', 'rw_secretary');

        $this->actingAs($sekretaris)->get('/mpwa')->assertOk();
        foreach (self::MENULIS as $uri) {
            $status = $this->actingAs($sekretaris)->post($uri)->getStatusCode();
            $this->assertNotSame(403, $status, "Sekretaris ditolak di {$uri}");
        }
    }

    public function test_ketua_melihat_halaman_tapi_tidak_mengirim(): void
    {
        $ketua = $this->user('ketuawa', 'rw_admin');

        $this->actingAs($ketua)->get('/mpwa')->assertOk();
        $this->actingAs($ketua)->post('/mpwa/broadcast')->assertForbidden();
        $this->actingAs($ketua)->post('/mpwa/aturan/save')->assertForbidden();
    }
}
