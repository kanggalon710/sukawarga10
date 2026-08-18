<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Surat;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Alur surat setelah pembagian tugas pengurus ditegakkan matriks kapabilitas.
 *
 * Warga mengajukan -> Petugas RT tanda tangan -> Ketua RW tanda tangan ->
 * Sekretaris membubuhkan cap dan menyelesaikan. Ketua TIDAK bisa membuat atau
 * menyunting surat; bendahara tidak ikut sama sekali.
 */
class SuratAlurTest extends TestCase
{
    use RefreshDatabase;

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

    private function petugasRt(): User
    {
        $rt = Organization::firstOrCreate(
            ['slug' => 'rt-01-rw-10-sukakarya'],
            ['parent_id' => $this->rw()->id, 'type' => Organization::TYPE_RT,
                'name' => 'RT 01', 'code' => 'RT01'],
        );

        return $this->user('petugasalur', 'rt_admin', $rt);
    }

    public function test_alur_lengkap_warga_rt_ketua_sekretaris(): void
    {
        $warga = $this->user('wargaalur');
        $rt = $this->petugasRt();
        $ketua = $this->user('ketuaalur', 'rw_admin');
        $sekretaris = $this->user('sekretarisalur', 'rw_secretary');

        $this->actingAs($warga)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji alur',
        ])->assertRedirect();

        $surat = Surat::orderByDesc('id')->first();
        $this->assertSame('diajukan', $surat->approval_step);

        $this->actingAs($rt)->post("/surat/{$surat->id}/approve")->assertRedirect();
        $this->assertSame('ttd_rt', $surat->fresh()->approval_step);

        $this->actingAs($ketua)->post("/surat/{$surat->id}/approve")->assertRedirect();
        $this->assertSame('ttd_rw', $surat->fresh()->approval_step);

        $this->actingAs($sekretaris)->post("/surat/{$surat->id}/approve")->assertRedirect();
        $segar = $surat->fresh();
        $this->assertSame('selesai', $segar->approval_step);
        $this->assertSame('selesai', $segar->status);
        $this->assertNotNull($segar->sek_signed_at, 'Cap sekretaris harus tercatat');
    }

    public function test_setiap_tahap_hanya_pemegangnya(): void
    {
        $warga = $this->user('wargatahap');
        $rt = $this->petugasRt();
        $ketua = $this->user('ketuatahap', 'rw_admin');
        $sekretaris = $this->user('sekretaristahap', 'rw_secretary');

        $this->actingAs($warga)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Dede Kurnia', 'keperluan' => 'Uji tahap',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();

        // Tahap `diajukan` milik RT: ketua dan sekretaris belum boleh maju.
        foreach ([$ketua, $sekretaris] as $bukanGiliran) {
            $this->actingAs($bukanGiliran)->post("/surat/{$surat->id}/approve")
                ->assertSessionHas('error');
        }
        $this->assertSame('diajukan', $surat->fresh()->approval_step);

        // Tahap `ttd_rt` milik ketua: RT tidak bisa memajukan dua kali.
        $this->actingAs($rt)->post("/surat/{$surat->id}/approve")->assertRedirect();
        $this->actingAs($rt)->post("/surat/{$surat->id}/approve")->assertSessionHas('error');
        $this->assertSame('ttd_rt', $surat->fresh()->approval_step);
    }

    public function test_ketua_tidak_bisa_membuat_atau_menyunting_surat(): void
    {
        $ketua = $this->user('ketuabuat', 'rw_admin');
        $sekretaris = $this->user('sekretarisbuat', 'rw_secretary');

        $this->actingAs($ketua)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji',
        ])->assertForbidden();

        // Sekretaris membuatnya, lalu ketua tetap tidak bisa menyunting.
        $this->actingAs($sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();

        $this->actingAs($ketua)->put("/surat/{$surat->id}", [
            'pemohon' => 'Ganti', 'kodeSurat' => 'SKD', 'nomorSurat' => $surat->nomorSurat,
        ])->assertForbidden();
        $this->actingAs($ketua)->put("/surat/{$surat->id}/isi", ['isi' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($ketua)->delete("/surat/{$surat->id}")->assertForbidden();
    }

    public function test_sekretaris_menerbitkan_surat_langsung_selesai(): void
    {
        $sekretaris = $this->user('sekretarisbikin', 'rw_secretary');

        $this->actingAs($sekretaris)->post('/surat', [
            'kodeSurat' => 'SKU', 'pemohon' => 'Dede Kurnia', 'keperluan' => 'Usaha',
        ])->assertRedirect();

        $surat = Surat::orderByDesc('id')->first();
        $this->assertSame('selesai', $surat->approval_step);
        $this->assertSame('selesai', $surat->status);
    }

    public function test_bendahara_tertutup_dari_seluruh_modul_surat(): void
    {
        $bendahara = $this->user('bendaharasurat', 'rw_finance');
        $sekretaris = $this->user('sekretarispagar', 'rw_secretary');

        $this->actingAs($sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();

        $this->actingAs($bendahara)->get('/surat')->assertForbidden();
        $this->actingAs($bendahara)->get("/surat/{$surat->id}/cetak")->assertForbidden();
        $this->actingAs($bendahara)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'X', 'keperluan' => 'Y',
        ])->assertForbidden();
        $this->actingAs($bendahara)->post("/surat/{$surat->id}/approve")->assertForbidden();
        $this->actingAs($bendahara)->delete("/surat/{$surat->id}")->assertForbidden();
    }

    public function test_surat_yang_sudah_selesai_tidak_bisa_ditolak(): void
    {
        // Bug lama: reject() tidak memeriksa tahap sama sekali, sehingga
        // petugas RT bisa menolak surat yang sudah dicap sekretaris.
        $rt = $this->petugasRt();
        $sekretaris = $this->user('sekretarisfinal', 'rw_secretary');

        $this->actingAs($sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();
        $this->assertSame('selesai', $surat->approval_step);

        $this->actingAs($rt)->post("/surat/{$surat->id}/reject", ['alasan' => 'iseng'])
            ->assertSessionHas('error');

        $this->assertSame('selesai', $surat->fresh()->approval_step);
    }

    public function test_penolakan_hanya_oleh_pemegang_tahap_berjalan(): void
    {
        $warga = $this->user('wargatolak');
        $rt = $this->petugasRt();
        $sekretaris = $this->user('sekretaristolak', 'rw_secretary');

        $this->actingAs($warga)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Dede Kurnia', 'keperluan' => 'Uji tolak',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();

        // Sekretaris tidak memegang surat.tolak maupun tahap `diajukan`.
        $this->actingAs($sekretaris)->post("/surat/{$surat->id}/reject", ['alasan' => 'x'])
            ->assertSessionHas('error');
        $this->assertSame('diajukan', $surat->fresh()->approval_step);

        // RT memegang tahap berjalan, jadi boleh menolak.
        $this->actingAs($rt)->post("/surat/{$surat->id}/reject", ['alasan' => 'Berkas kurang'])
            ->assertSessionHas('success');
        $this->assertSame('ditolak', $surat->fresh()->approval_step);
    }

    public function test_akun_setara_admin_bisa_menyetujui_di_semua_tahap(): void
    {
        // Regresi: approve() dulu mencocokkan NAMA level ('superadmin'), jadi
        // akun bawaan aplikasi yang berlevel 'admin' lolos middleware tapi
        // ditolak di dalam controller.
        $warga = $this->user('wargaadmin');
        $admin = $this->user('operatorportal', 'super_admin');

        $this->actingAs($warga)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Asep Suhendar', 'keperluan' => 'Uji admin',
        ])->assertRedirect();
        $surat = Surat::orderByDesc('id')->first();

        foreach (['ttd_rt', 'ttd_rw', 'selesai'] as $tahapBerikutnya) {
            $this->actingAs($admin)->post("/surat/{$surat->id}/approve")->assertRedirect();
            $this->assertSame($tahapBerikutnya, $surat->fresh()->approval_step);
        }
    }

    public function test_warga_hanya_melihat_dan_mencetak_suratnya_sendiri(): void
    {
        $warga = $this->user('wargamilik');
        $wargaLain = $this->user('wargalain');
        $sekretaris = $this->user('sekretarismilik', 'rw_secretary');

        $this->actingAs($sekretaris)->post('/surat', [
            'kodeSurat' => 'SKD', 'pemohon' => 'Orang Lain', 'keperluan' => 'Uji',
        ])->assertRedirect();
        $suratOrangLain = Surat::orderByDesc('id')->first();

        $this->actingAs($warga)->get('/surat')->assertOk();
        $this->actingAs($warga)->get("/surat/{$suratOrangLain->id}/cetak")->assertForbidden();
        $this->actingAs($wargaLain)->get("/surat/{$suratOrangLain->id}")->assertForbidden();
    }
}
