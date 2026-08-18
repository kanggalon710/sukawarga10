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
 * Penjaga peran modul keuangan.
 *
 * Sampai matriks kapabilitas dipasang, SELURUH rute uang (/bukukas,
 * /kas/pengeluaran, /kas/setor, /kas/sumbangan, /sampah/bayar, /padaringan/bayar)
 * hanya dijaga `auth` + feature flag: akun warga yang login bisa mencatat
 * transaksi kas lewat URL langsung, dan tak satu pun controllernya mengecek
 * level. Tes ini adalah bukti celah itu, sekaligus penguncinya.
 *
 * Pembagian yang dikunci: bendahara mencatat semua; petugas RT hanya menagih
 * warganya dan menyetor ke RW; ketua dan sekretaris hanya melihat.
 */
class KeuanganOtorisasiTest extends TestCase
{
    use RefreshDatabase;

    /** Seluruh endpoint yang MENGUBAH data uang. */
    private const MENULIS = [
        ['post', '/bukukas'],
        ['post', '/kas/pengeluaran'],
        ['post', '/kas/setor'],
        ['post', '/kas/sumbangan'],
        ['post', '/sampah/bayar/1'],
        ['post', '/padaringan/bayar/1'],
    ];

    /** Endpoint yang hanya MENAMPILKAN angka kas seluruh tenant. */
    private const MEMBACA = [
        '/bukukas',
        '/kas/pengeluaran',
        '/kas/setor',
        '/kas/sumbangan',
        '/sampah',
        '/padaringan',
        '/laporan/ringkasan',
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

    private function orgRt(): Organization
    {
        return Organization::create([
            'parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
            'name' => 'RT 01', 'code' => 'RT01', 'slug' => 'rt-01-rw-10-sukakarya',
        ]);
    }

    public function test_warga_ditolak_menulis_di_seluruh_jalur_uang(): void
    {
        $warga = $this->user('wargakas');

        foreach (self::MENULIS as [$method, $uri]) {
            $this->actingAs($warga)->{$method}($uri)
                ->assertForbidden("Warga masih bisa {$method} {$uri}");
        }
    }

    public function test_warga_ditolak_membaca_angka_kas_tenant(): void
    {
        $warga = $this->user('wargabaca');

        foreach (self::MEMBACA as $uri) {
            $this->actingAs($warga)->get($uri)
                ->assertForbidden("Warga masih bisa membuka {$uri}");
        }
    }

    public function test_bendahara_boleh_mencatat_seluruh_jalur_uang(): void
    {
        $bendahara = $this->user('bendaharakas', 'rw_finance');

        foreach (self::MENULIS as [$method, $uri]) {
            $status = $this->actingAs($bendahara)->{$method}($uri)->getStatusCode();
            $this->assertNotSame(403, $status, "Bendahara ditolak di {$method} {$uri}");
        }
    }

    public function test_ketua_melihat_kas_tapi_tidak_mencatat(): void
    {
        $ketua = $this->user('ketuakas', 'rw_admin');

        $this->actingAs($ketua)->get('/bukukas')->assertOk();
        $this->actingAs($ketua)->get('/laporan/ringkasan')->assertOk();

        foreach (self::MENULIS as [$method, $uri]) {
            $this->actingAs($ketua)->{$method}($uri)
                ->assertForbidden("Ketua masih bisa {$method} {$uri}");
        }
    }

    public function test_sekretaris_melihat_kas_tapi_tidak_mencatat(): void
    {
        $sekretaris = $this->user('sekretariskas', 'rw_secretary');

        $this->actingAs($sekretaris)->get('/bukukas')->assertOk();

        foreach (self::MENULIS as [$method, $uri]) {
            $this->actingAs($sekretaris)->{$method}($uri)
                ->assertForbidden("Sekretaris masih bisa {$method} {$uri}");
        }
    }

    public function test_petugas_rt_hanya_menagih_dan_menyetor(): void
    {
        $rt = $this->user('petugaskas', 'rt_admin', $this->orgRt());

        // Boleh: menerima iuran warganya dan menyetorkannya ke RW.
        foreach ([['post', '/sampah/bayar/1'], ['post', '/padaringan/bayar/1'], ['post', '/kas/setor']] as [$method, $uri]) {
            $status = $this->actingAs($rt)->{$method}($uri)->getStatusCode();
            $this->assertNotSame(403, $status, "Petugas RT ditolak di {$method} {$uri}");
        }

        // Tidak boleh: kas RW dan pengeluaran adalah wilayah bendahara.
        foreach ([['post', '/bukukas'], ['post', '/kas/pengeluaran'], ['post', '/kas/sumbangan']] as [$method, $uri]) {
            $this->actingAs($rt)->{$method}($uri)
                ->assertForbidden("Petugas RT masih bisa {$method} {$uri}");
        }
    }

    public function test_void_transaksi_hanya_pengawas_bukan_pencatat(): void
    {
        $ketua = $this->user('ketuavoid', 'rw_admin');
        $bendahara = $this->user('bendaharavoid', 'rw_finance');

        // Pencatat tidak membatalkan catatannya sendiri.
        $this->actingAs($bendahara)->post('/transaksi/1/void')->assertForbidden();

        $status = $this->actingAs($ketua)->post('/transaksi/1/void')->getStatusCode();
        $this->assertNotSame(403, $status, 'Ketua seharusnya boleh void');
    }

    public function test_modul_yang_dimatikan_menjawab_404_bukan_403(): void
    {
        // Feature flag menang atas izin: bagi tenant yang modulnya dimatikan,
        // modul itu memang TIDAK ADA - 403 justru membocorkan keberadaannya.
        \App\Models\AppSetting::create([
            'key' => 'fitur_bukukas', 'value' => '0',
            'organization_id' => $this->rw()->id,
        ]);
        $warga = $this->user('wargaflag');

        $this->actingAs($warga)->get('/bukukas')->assertNotFound();
        $this->actingAs($warga)->post('/bukukas')->assertNotFound();
    }
}
