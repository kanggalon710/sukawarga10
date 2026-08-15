<?php

namespace Tests\Feature;

use App\Models\Aduan;
use App\Models\AppSetting;
use App\Models\Kegiatan;
use App\Models\Keluarga;
use App\Models\Surat;
use App\Models\Umkm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Otorisasi di sisi server.
 *
 * Endpoint-endpoint di bawah sempat hanya dijaga middleware `auth`, sehingga
 * akun warga bisa mengubah atau menghapus data milik orang lain hanya dengan
 * menebak id di URL. Menu memang disembunyikan userCan(), tapi itu UI.
 */
class OtorisasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    private function user(string $level, ?string $username = null): User
    {
        $username = $username ?: $level;

        return User::create([
            'user_id' => 'usr_'.$username,
            'username' => $username,
            'namaLengkap' => 'Akun '.$username,
            'pin' => Hash::make('123456'),
            'level' => $level,
            'status' => 'aktif',
            'isDefault' => false,
        ]);
    }

    private function surat(?int $userId = null): Surat
    {
        return Surat::create([
            'surat_id' => 'SRT-'.uniqid(),
            'kodeSurat' => 'SKD',
            'tahun' => date('Y'),
            'nomorUrut' => 1,
            'nomorSurat' => '001/SKD/RW10/'.date('Y'),
            'tanggal' => now()->toDateString(),
            'pemohon' => 'Asep Suhendar',
            'keperluan' => 'Keperluan uji',
            'user_id' => $userId,
            'approval_step' => 'diajukan',
            'status' => 'draft',
        ]);
    }

    public static function endpointTerlarangUntukWarga(): array
    {
        return [
            'ubah surat' => ['put', '/surat/{surat}'],
            'ubah status surat' => ['patch', '/surat/{surat}/status'],
            'hapus surat' => ['delete', '/surat/{surat}'],
            'setujui surat' => ['post', '/surat/{surat}/approve'],
            'tolak surat' => ['post', '/surat/{surat}/reject'],
            'ubah status aduan' => ['put', '/aduan/{aduan}/status'],
            'tambah umkm' => ['post', '/umkm'],
            'hapus umkm' => ['delete', '/umkm/{umkm}'],
            'tambah kegiatan' => ['post', '/kegiatan'],
            'hapus kegiatan' => ['delete', '/kegiatan/{kegiatan}'],
            'pencarian global' => ['get', '/search?q=asep'],
        ];
    }

    #[DataProvider('endpointTerlarangUntukWarga')]
    public function test_warga_ditolak_pada_endpoint_pengurus(string $method, string $uri): void
    {
        $warga = $this->user('warga');

        $surat = $this->surat();
        $aduan = Aduan::create([
            'aduan_id' => 'ADU-1', 'tanggal' => now()->toDateString(),
            'pelapor' => 'X', 'rt' => '01', 'isi' => 'Uji', 'status' => 'masuk',
        ]);
        $umkm = Umkm::create(['umkm_id' => 'UMKM-1', 'pemilik' => 'X', 'namaUsaha' => 'Warung']);
        $kegiatan = Kegiatan::create([
            'kegiatan_id' => 'KGT-1', 'judul' => 'Kerja Bakti', 'tanggal' => now()->toDateString(),
        ]);

        $uri = strtr($uri, [
            '{surat}' => $surat->id,
            '{aduan}' => $aduan->id,
            '{umkm}' => $umkm->id,
            '{kegiatan}' => $kegiatan->id,
        ]);

        $this->actingAs($warga)->{$method}($uri, ['pemohon' => 'X', 'status' => 'selesai'])
            ->assertForbidden();
    }

    public function test_warga_tidak_bisa_membuka_surat_milik_orang_lain(): void
    {
        $warga = $this->user('warga', 'warga1');
        $wargaLain = $this->user('warga', 'warga2');
        $surat = $this->surat($wargaLain->id);

        $this->actingAs($warga)->get('/surat/'.$surat->id)->assertForbidden();
        $this->actingAs($warga)->get('/surat/'.$surat->id.'/cetak')->assertForbidden();
    }

    public function test_warga_bisa_membuka_suratnya_sendiri(): void
    {
        $warga = $this->user('warga');
        $surat = $this->surat($warga->id);

        $this->actingAs($warga)->get('/surat/'.$surat->id)->assertOk();
    }

    public function test_pengurus_boleh_mengubah_surat(): void
    {
        $surat = $this->surat();

        $this->actingAs($this->user('ketua_rw'))
            ->put('/surat/'.$surat->id, ['pemohon' => 'Nama Baru'])
            ->assertRedirect();

        $this->assertSame('Nama Baru', $surat->fresh()->pemohon);
    }

    /**
     * app_settings ikut menentukan otorisasi, jadi form Pengaturan tidak boleh
     * menjadi jalan pintas untuk menaikkan hak akses sendiri.
     */
    public function test_pengaturan_menolak_key_di_luar_whitelist(): void
    {
        $this->actingAs($this->user('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'tarif',
            'tarif_sampah' => '7000',
            'role_permissions' => json_encode(['ketua_rw' => ['akun']]),
            'kolom_ngawur' => 'nilai',
        ])->assertRedirect();

        $this->assertSame('7000', AppSetting::where('key', 'tarif_sampah')->value('value'));
        $this->assertNull(AppSetting::where('key', 'role_permissions')->value('value'));
        $this->assertNull(AppSetting::where('key', 'kolom_ngawur')->value('value'));
    }

    /**
     * Identitas aplikasi harus benar-benar bisa diganti lewat form, bukan cuma
     * lewat penulisan langsung ke app_settings. Menutup kemungkinan key baru
     * lupa didaftarkan di whitelist sehingga tersimpan diam-diam gagal.
     */
    public function test_identitas_aplikasi_bisa_disimpan_lewat_form_pengaturan(): void
    {
        $this->actingAs($this->user('ketua_rw'))->post('/pengaturan', [
            '_active_tab' => 'info',
            'nama_aplikasi' => 'Kampung Uji',
            'tagline_aplikasi' => "Baris satu.\nBaris dua.",
            'lokasi_singkat' => 'Tasikmalaya',
        ])->assertRedirect();

        $this->assertSame('Kampung Uji', AppSetting::where('key', 'nama_aplikasi')->value('value'));
        $this->assertSame("Baris satu.\nBaris dua.", AppSetting::where('key', 'tagline_aplikasi')->value('value'));
        $this->assertSame('Tasikmalaya', AppSetting::where('key', 'lokasi_singkat')->value('value'));
    }

    public function test_warga_tidak_bisa_membuka_halaman_pengaturan(): void
    {
        $this->actingAs($this->user('warga'))->get('/pengaturan')->assertForbidden();
        $this->actingAs($this->user('bendahara', 'bend'))->get('/akun')->assertForbidden();
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/warga')->assertRedirect('/login');
    }

    /**
     * Profil Saya harus terikat ke KK lewat users.keluarga_id, bukan kecocokan nama.
     */
    public function test_profil_warga_terikat_lewat_keluarga_id_bukan_nama(): void
    {
        $kk = Keluarga::create([
            'keluarga_id' => 'kk_a', 'nama' => 'Budi Santoso', 'rt' => '01',
            'alamat' => 'Jl. A', 'jumlahAnggota' => 2, 'status' => 'aktif',
        ]);

        // Nama akun sama persis dengan nama KK, tapi tidak tertaut.
        $penyamar = $this->user('warga', 'penyamar');
        $penyamar->update(['namaLengkap' => 'Budi Santoso']);

        $this->actingAs($penyamar)->get('/profil')
            ->assertRedirect(route('dashboard'));

        // Akun yang benar-benar tertaut boleh masuk.
        $pemilik = $this->user('warga', 'pemilik');
        $pemilik->update(['keluarga_id' => $kk->keluarga_id]);

        $this->actingAs($pemilik)->get('/profil')->assertOk();
    }
}
