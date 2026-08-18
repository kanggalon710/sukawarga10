<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\MatriksKapabilitas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Otorisasi berbasis matriks kapabilitas: `peranEfektif()` mengembalikan
 * SELURUH peran relevan (bukan yang "terkuat") dan `bolehkah()` menggabungkan
 * kapabilitasnya. Ini yang membedakan matriks dari hierarki: pengurus yang
 * merangkap sekretaris + bendahara memegang keduanya, bukan salah satu.
 *
 * `levelEfektif()` tetap satu string dan tetap bersemantik lama - ia dipakai
 * untuk LABEL dan penyaringan DATA (warga hanya melihat miliknya), bukan izin.
 */
class KapabilitasPeranTest extends TestCase
{
    use RefreshDatabase;

    private function buatUser(string $level, string $username): User
    {
        return User::create([
            'user_id' => "u_{$username}", 'username' => $username,
            'namaLengkap' => ucfirst($username), 'pin' => Hash::make('123456'),
            'level' => $level, 'status' => 'aktif',
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

    public function test_tanpa_assignment_peran_efektifnya_warga(): void
    {
        $user = $this->buatUser('ketua_rw', 'tanpaperan');

        $this->actingAs($user)->get('/');

        $this->assertSame(['warga'], $user->peranEfektif());
        $this->assertFalse(bolehkah('pengaturan.ubah'));
        $this->assertTrue(bolehkah('aduan.lapor'));
    }

    public function test_peran_rangkap_menggabungkan_kapabilitas_bukan_memilih_terkuat(): void
    {
        $user = $this->buatUser('warga', 'rangkap');
        $this->pasang($user, 'rw_finance', $this->rw());
        $this->pasang($user, 'rw_secretary', $this->rw());

        $this->actingAs($user)->get('/');

        $peran = $user->peranEfektif();
        sort($peran);
        $this->assertSame(['bendahara', 'sekretaris'], $peran);

        // Gabungan: kapabilitas uang DARI bendahara dan surat DARI sekretaris.
        $this->assertTrue(bolehkah('bukukas.catat'), 'kapabilitas bendahara hilang');
        $this->assertTrue(bolehkah('surat.buat'), 'kapabilitas sekretaris hilang');
        // Tidak ada satu pun peran itu yang boleh void.
        $this->assertFalse(bolehkah('transaksi.void'));

        // Label tetap satu string, dipilih lewat URUTAN_TAMPIL (sekretaris di
        // atas bendahara) - ini penampilan, bukan hierarki hak.
        $this->assertSame('sekretaris', $user->levelEfektif());
    }

    public function test_level_setara_admin_memegang_seluruh_katalog(): void
    {
        // Akun bawaan aplikasi berlevel 'admin', bukan 'superadmin'.
        $user = $this->buatUser('admin', 'adminbawaan');
        $this->pasang($user, 'super_admin', Organization::where('slug', 'platform')->first());

        $this->actingAs($user)->get('/');

        $this->assertTrue(bolehkah('platform.tenant'));
        $this->assertTrue(bolehkah('pengaturan.pemeliharaan'));
        $this->assertTrue(bolehkah('surat.buat'));
    }

    public function test_assignment_tenant_lain_tidak_memberi_kapabilitas(): void
    {
        $rwLain = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);
        $user = $this->buatUser('warga', 'sekretarissana');
        $this->pasang($user, 'rw_secretary', $rwLain);

        $this->actingAs($user)->get('/');

        $this->assertSame(['warga'], $user->peranEfektif());
        $this->assertFalse(bolehkah('surat.buat'));
    }

    public function test_bolehkah_bersifat_or_dan_menolak_tamu(): void
    {
        $this->assertFalse(bolehkah('surat.buat'), 'tamu tidak boleh apa pun');

        $user = $this->buatUser('warga', 'ketuauji');
        $this->pasang($user, 'rw_admin', $this->rw());
        $this->actingAs($user)->get('/');

        // OR: punya salah satu sudah cukup.
        $this->assertTrue(bolehkah('surat.buat', 'surat.ttdRw'));
        $this->assertFalse(bolehkah('surat.buat', 'surat.finalisasi'));
    }

    public function test_override_tenant_menambah_dan_mencabut_kapabilitas(): void
    {
        $user = $this->buatUser('warga', 'ketuaoverride');
        $this->pasang($user, 'rw_admin', $this->rw());

        \App\Models\AppSetting::create([
            'key' => MatriksKapabilitas::KEY_OVERRIDE,
            'organization_id' => $this->rw()->id,
            'value' => json_encode([
                'ketua_rw' => ['surat.ttdRt' => true, 'transaksi.void' => false],
            ]),
        ]);

        $this->actingAs($user)->get('/');

        $this->assertTrue(bolehkah('surat.ttdRt'), 'override gagal menambah');
        $this->assertFalse(bolehkah('transaksi.void'), 'override gagal mencabut');
        // Yang tidak disebut delta tetap mengikuti bawaan kode.
        $this->assertTrue(bolehkah('surat.ttdRw'));
    }

    public function test_override_tidak_bisa_melucuti_superadmin(): void
    {
        $user = $this->buatUser('superadmin', 'supertenant');
        $this->pasang($user, 'super_admin', Organization::where('slug', 'platform')->first());

        \App\Models\AppSetting::create([
            'key' => MatriksKapabilitas::KEY_OVERRIDE,
            'organization_id' => $this->rw()->id,
            'value' => json_encode(['superadmin' => ['pengaturan.ubah' => false]]),
        ]);

        $this->actingAs($user)->get('/');

        $this->assertTrue(bolehkah('pengaturan.ubah'));
    }

    public function test_override_tidak_bisa_mencetak_admin_platform(): void
    {
        $user = $this->buatUser('warga', 'ketuanakal');
        $this->pasang($user, 'rw_admin', $this->rw());

        \App\Models\AppSetting::create([
            'key' => MatriksKapabilitas::KEY_OVERRIDE,
            'organization_id' => $this->rw()->id,
            'value' => json_encode(['ketua_rw' => ['platform.tenant' => true]]),
        ]);

        $this->actingAs($user)->get('/');

        $this->assertFalse(bolehkah('platform.tenant'));
    }

    public function test_override_dengan_key_usang_tidak_mematikan_matriks(): void
    {
        // Baris lama bisa memuat key yang sudah dihapus rilis berikutnya; satu
        // key usang tidak boleh membuat seluruh matriks tenant runtuh.
        $user = $this->buatUser('warga', 'ketualama');
        $this->pasang($user, 'rw_admin', $this->rw());

        \App\Models\AppSetting::create([
            'key' => MatriksKapabilitas::KEY_OVERRIDE,
            'organization_id' => $this->rw()->id,
            'value' => json_encode([
                'ketua_rw' => ['modul.yang.sudah.dihapus' => true],
                'peran_hantu' => ['surat.buat' => true],
            ]),
        ]);

        $this->actingAs($user)->get('/');

        $this->assertTrue(bolehkah('surat.ttdRw'));
        $this->assertFalse(bolehkah('surat.buat'));
    }

    public function test_menu_sidebar_mengikuti_matriks_yang_sama_dengan_rute(): void
    {
        // Dulu menu (role_permissions) dan penjaga rute (CheckRole) adalah dua
        // sistem terpisah, jadi menu bisa menampilkan halaman yang pasti 403.
        $bendahara = $this->buatUser('warga', 'bendaharamenu');
        $this->pasang($bendahara, 'rw_finance', $this->rw());

        $this->actingAs($bendahara)->get('/')
            ->assertSee('Buku Kas')
            ->assertDontSee('Surat Menyurat')
            ->assertDontSee('Manajemen Akun');

        $sekretaris = $this->buatUser('warga', 'sekretarismenu');
        $this->pasang($sekretaris, 'rw_secretary', $this->rw());

        $this->actingAs($sekretaris)->get('/')
            ->assertSee('Surat Menyurat')
            ->assertSee('Data Warga')
            ->assertDontSee('Manajemen Akun');
    }

    public function test_feature_flag_tetap_menang_atas_kapabilitas(): void
    {
        $sekretaris = $this->buatUser('warga', 'sekretarisflag');
        $this->pasang($sekretaris, 'rw_secretary', $this->rw());
        \App\Models\AppSetting::create([
            'key' => 'fitur_surat', 'value' => '0',
            'organization_id' => $this->rw()->id,
        ]);

        $this->actingAs($sekretaris)->get('/')->assertDontSee('Surat Menyurat');
        $this->actingAs($sekretaris)->get('/surat')->assertNotFound();
    }

    public function test_peran_efektif_tidak_menambah_query_per_tingkat(): void
    {
        $user = $this->buatUser('warga', 'hematquery');
        $this->pasang($user, 'rw_admin', $this->rw());
        $this->actingAs($user)->get('/');

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        $user->peranEfektif();
        $user->peranEfektif();
        $jumlah = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Maksimal dua query (assignment + peta organisasi), dan panggilan
        // kedua gratis berkat memo TenantContext.
        $this->assertLessThanOrEqual(2, $jumlah, 'peranEfektif() boros query');
    }
}
