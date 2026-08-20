<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Rilis 2: NIK yang sudah terdaftar di portal desa LAIN ditolak.
 *
 * Fitur ini menciptakan risiko keamanan yang sebelumnya tidak ada: kalau setiap
 * NIK dijawab dengan lokasinya, portal berubah jadi mesin pencari alamat. Karena
 * itu yang boleh keluar hanya nama desa dan RW, jumlahnya dibatasi per hari, dan
 * tiap pengungkapan dicatat dengan NIK ter-sidik.
 *
 * NIK contoh memakai kode wilayah 999999 yang tidak ada di Permendagri, supaya
 * tidak ada identitas warga sungguhan masuk repositori publik ini.
 */
class NikLintasTenantTest extends TestCase
{
    use RefreshDatabase;

    private const NIK_TETANGGA = '9999990303700005';

    private User $pengurus;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        RateLimiter::clear('nik-lokasi:1');

        $this->pengurus = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_sekre', 'username' => 'sekre',
            'namaLengkap' => 'Sekretaris Uji', 'pin' => Hash::make('123456'),
            'level' => 'sekretaris', 'status' => 'aktif', 'isDefault' => false,
        ]));
    }

    /** KK milik RW lain, dibuat sebelum request pertama supaya org-nya dihormati. */
    private function kkTenantLain(array $ubah = []): Keluarga
    {
        $rwLain = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 07',
            'code' => 'RW07', 'slug' => 'rw-07-tetangga-'.uniqid(),
        ]);

        $kk = new Keluarga(array_merge([
            'keluarga_id' => 'kk_'.uniqid(),
            'nama' => 'Bapak Tetangga',
            'alamat' => 'Kp. Sebelah No. 9',
            'rt' => '03',
            'noHP' => '628123456789',
            'nik' => self::NIK_TETANGGA,
            'noKK' => '9999990112200015',
        ], $ubah));
        $kk->organization_id = $rwLain->id;
        $kk->saveQuietly();

        return $kk;
    }

    private function tambahKk(array $isi): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->pengurus)->post('/warga', array_merge([
            'nama' => 'Bapak Baru', 'rt' => '01',
            'alamat' => 'Kp. Uji', 'jumlahAnggota' => 1,
        ], $isi));
    }

    public function test_nik_milik_portal_lain_ditolak(): void
    {
        $this->kkTenantLain();

        $this->tambahKk(['nik' => self::NIK_TETANGGA])->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('keluargas', ['nama' => 'Bapak Baru']);
    }

    public function test_pesan_menyebut_desa_dan_rw_tapi_tidak_pernah_identitas_orangnya(): void
    {
        // Inilah asersi terpenting di berkas ini. Menyebut lokasi masih bisa
        // dibela sebagai kebutuhan kerja; menyebut nama, alamat, atau nomor HP
        // warga desa lain tidak pernah bisa.
        $this->kkTenantLain();

        $this->tambahKk(['nik' => self::NIK_TETANGGA]);
        $pesan = session('errors')->first('nik');

        $this->assertStringContainsString('RW 07', $pesan);
        $this->assertStringContainsString('Sukakarya', $pesan);

        $this->assertStringNotContainsString('Bapak Tetangga', $pesan);
        $this->assertStringNotContainsString('Kp. Sebelah', $pesan);
        $this->assertStringNotContainsString('628123456789', $pesan);
        $this->assertStringNotContainsString('RT 03', $pesan);
    }

    public function test_no_kk_milik_portal_lain_juga_ditolak(): void
    {
        $this->kkTenantLain();

        $this->tambahKk(['noKK' => '9999990112200015'])->assertSessionHasErrors('noKK');
    }

    public function test_arsip_warga_pindah_tidak_dianggap_masih_tinggal_di_sana(): void
    {
        // Tanpa pengecualian ini, warga yang sudah pindah ke sini tidak akan
        // pernah bisa didaftarkan maupun disunting: arsipnya sendiri di RW asal
        // selamanya dilaporkan sebagai pemakai NIK itu.
        $kk = $this->kkTenantLain();
        $kk->forceFill(['status' => 'pindah'])->save();

        $this->tambahKk(['nik' => self::NIK_TETANGGA])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('keluargas', ['nama' => 'Bapak Baru']);
    }

    public function test_kuota_habis_membuat_pesan_berhenti_menyebut_lokasi(): void
    {
        $this->kkTenantLain();

        // Kuota 5 per hari. Percobaan keenam masih ditolak, tapi tanpa lokasi.
        for ($i = 0; $i < 5; $i++) {
            $this->tambahKk(['nik' => self::NIK_TETANGGA]);
        }

        $this->tambahKk(['nik' => self::NIK_TETANGGA])->assertSessionHasErrors('nik');
        $pesan = session('errors')->first('nik');

        $this->assertStringNotContainsString('RW 07', $pesan);
        $this->assertStringNotContainsString('Sukakarya', $pesan);
        $this->assertStringContainsString('portal desa lain', $pesan);
    }

    public function test_tiap_pengungkapan_dicatat_dengan_nik_tersidik(): void
    {
        $this->kkTenantLain();

        $this->tambahKk(['nik' => self::NIK_TETANGGA]);

        $log = AuditLog::withoutGlobalScope('organisasi')
            ->where('aksi', 'nik_lintas_tenant')->latest('id')->first();

        $this->assertNotNull($log, 'Pengungkapan lokasi wajib meninggalkan jejak.');
        $this->assertStringNotContainsString(self::NIK_TETANGGA, $log->deskripsi,
            'Log Sistem tidak boleh berubah jadi kumpulan NIK mentah.');
    }

    public function test_pendaftaran_publik_tidak_membocorkan_keberadaan_nik_desa_lain(): void
    {
        // Halaman ini terbuka tanpa login. Kalau cek lintas tenant dipasang di
        // sini, siapa pun dari internet bisa menembak NIK satu per satu untuk
        // menebak di desa mana seseorang terdaftar. Pemeriksaannya sengaja
        // ditunda sampai pengurus menyetujui pendaftarannya.
        $this->kkTenantLain();

        $this->post('/login/register', [
            'nik' => self::NIK_TETANGGA,
            'no_kk' => '9999990112200015',
            'nama_lengkap' => 'Pendatang Baru',
            'rt' => '01',
        ]);
        $this->assertDatabaseHas('pendaftarans', ['nama_lengkap' => 'Pendatang Baru']);
    }

    public function test_mendaftar_tanpa_no_kk_tidak_menjatuhkan_halaman(): void
    {
        // Regresi: kolom `pendaftarans.no_kk` dibuat NOT NULL padahal formulirnya
        // memang tidak mewajibkan No. KK, sehingga warga yang belum memegang
        // kartunya mendapat halaman error 500, bukan pesan yang bisa dimengerti.
        $this->post('/login/register', [
            'nik' => '9999991505680002',
            'nama_lengkap' => 'Tanpa Kartu',
            'rt' => '02',
        ])->assertRedirect();

        $this->assertDatabaseHas('pendaftarans', ['nama_lengkap' => 'Tanpa Kartu']);
    }
}
