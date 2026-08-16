<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Demografi Laporan: kepala keluarga adalah jiwa juga. Dulu tile
 * Laki-laki/Perempuan hanya menghitung anggota, sehingga totalnya tidak
 * pernah mencapai Total Jiwa (contoh nyata RW 07: 185 jiwa tapi L+P = 112).
 */
class LaporanDemografiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01'],
        ])->assertSuccessful();

        $this->admin = User::create([
            'user_id' => 'u_lap', 'username' => 'lapadmin', 'namaLengkap' => 'Lap Admin',
            'pin' => Hash::make('123456'), 'level' => 'ketua_rw', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->admin->id,
            'role_id' => Role::where('slug', 'rw_admin')->value('id'),
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);
    }

    public function test_gender_menghitung_kepala_keluarga_bukan_hanya_anggota(): void
    {
        $org = Organization::where('slug', 'rw-01-cibunar')->value('id');

        $kkL = new Keluarga([
            'keluarga_id' => 'kk_lap1', 'nama' => 'Dadan Kepala', 'alamat' => 'Jl. Uji',
            'rt' => '01', 'status' => 'aktif', 'jenisKelaminKK' => 'L',
        ]);
        $kkL->organization_id = $org;
        $kkL->saveQuietly();

        $kkP = new Keluarga([
            'keluarga_id' => 'kk_lap2', 'nama' => 'Euis Kepala', 'alamat' => 'Jl. Uji',
            'rt' => '02', 'status' => 'aktif', 'jenisKelaminKK' => 'P',
        ]);
        $kkP->organization_id = $org;
        $kkP->saveQuietly();

        $anak = new Anggota([
            'anggota_id' => 'a_lap1', 'keluarga_id' => 'kk_lap1',
            'nama' => 'Neng Anak', 'jenisKelamin' => 'Perempuan', 'statusKeluarga' => 'Anak',
        ]);
        $anak->saveQuietly();

        $respons = $this->actingAs($this->admin)
            ->get('https://cibunar-rw01.desa.jabnet.id/laporan/demografi');
        $respons->assertOk();

        $d = $respons->viewData('demografi');
        $this->assertSame(3, $d['totalJiwa']);
        $this->assertSame(1, $d['totalLaki'], 'Kepala laki-laki harus terhitung.');
        $this->assertSame(2, $d['totalPerempuan'], 'Kepala perempuan + anggota perempuan.');

        // Per-RT juga: RT 01 = kepala L + anak P, RT 02 = kepala P saja.
        $perRT = collect($d['populasiRT'])->keyBy('rt');
        $this->assertSame(['laki' => 1, 'perempuan' => 1],
            ['laki' => $perRT['01']['laki'], 'perempuan' => $perRT['01']['perempuan']]);
        $this->assertSame(['laki' => 0, 'perempuan' => 1],
            ['laki' => $perRT['02']['laki'], 'perempuan' => $perRT['02']['perempuan']]);
    }
}
