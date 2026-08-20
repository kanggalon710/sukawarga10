<?php

namespace Tests\Feature;

use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Pendaftaran;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Import/template KK dan persetujuan pendaftaran harus mengikuti WILAYAH
 * TENANT request - dulu default-nya menulis "RW 10 Sukakarya Tarogong Kidul"
 * sebagai data untuk semua tenant.
 */
class ImportTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $adminCibunar;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->artisan('tenant:buat', [
            'nama' => 'Desa Cibunar', 'label' => 'cibunar',
            '--kecamatan' => 'Tarogong Kidul', '--rw' => ['01'],
        ])->assertSuccessful();

        // Sekretaris: impor/ekspor CSV warga memuat PII seluruh tenant, jadi
        // sejak matriks kapabilitas ia bukan lagi wewenang ketua maupun RT.
        $this->adminCibunar = User::create([
            'user_id' => 'u_imp', 'username' => 'impadmin', 'namaLengkap' => 'Imp Admin',
            'pin' => Hash::make('123456'), 'level' => 'sekretaris', 'status' => 'aktif',
        ]);
        UserRoleAssignment::create([
            'user_id' => $this->adminCibunar->id,
            'role_id' => Role::where('slug', 'rw_secretary')->value('id'),
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);
    }

    public function test_import_kk_memakai_wilayah_tenant_untuk_kolom_kosong(): void
    {
        $csv = "Nama KK,RT,RW,Kelurahan/Kecamatan,Alamat\n".
               "Asep Impor,01,,,\n";
        $berkas = UploadedFile::fake()->createWithContent('keluarga.csv', $csv);

        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => $berkas,
            ])->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')->where('nama', 'Asep Impor')->firstOrFail();
        $this->assertSame(
            Organization::where('slug', 'rw-01-cibunar')->value('id'),
            $kk->organization_id, 'Baris impor harus tercap tenant pengimpor.'
        );
        $this->assertSame('01', $kk->rt, "RT kanonik dua digit polos, bukan 'RT 01'.");
        $this->assertSame('01', $kk->rw, 'RW default harus dari tenant, bukan 10.');
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Tarogong Kidul', $kk->kecamatan);
        $this->assertStringNotContainsString('Sukakarya', (string) $kk->alamat);
    }

    public function test_template_impor_memakai_contoh_wilayah_tenant(): void
    {
        $respons = $this->actingAs($this->adminCibunar)
            ->get('https://cibunar-rw01.desa.jabnet.id/warga/template/keluarga');

        $respons->assertOk();
        $isi = $respons->streamedContent();
        $this->assertStringContainsString('Desa Cibunar/Tarogong Kidul', $isi);
        $this->assertStringNotContainsString('Sukakarya', $isi);
    }

    public function test_pendaftaran_disetujui_menulis_wilayah_tenant(): void
    {
        // Pendaftaran masuk lewat form publik tenant Cibunar.
        $this->post('https://cibunar-rw01.desa.jabnet.id/login/register', [
            'nik' => '3205111122223333', 'no_kk' => '3205111122224444',
            'nama_lengkap' => 'Calon Cibunar', 'rt' => '01',
        ])->assertRedirect();
        $daftar = Pendaftaran::withoutGlobalScope('organisasi')
            ->where('nik', '3205111122223333')->firstOrFail();

        // Menyetujui pendaftaran adalah `pendaftaran.putuskan` milik ketua RW,
        // bukan sekretaris; pakai akun operator bawaan RW hasil `tenant:buat`.
        $ketua = User::where('username', 'cibunar-rw01')->firstOrFail();

        $this->actingAs($ketua)
            ->post("https://cibunar-rw01.desa.jabnet.id/pendaftaran/{$daftar->id}/approve")
            ->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')
            ->where('nik', '3205111122223333')->firstOrFail();
        $this->assertSame('01', $kk->rw);
        $this->assertSame('Desa Cibunar', $kk->kelurahan);
        $this->assertSame('Tarogong Kidul', $kk->kecamatan);
        $this->assertStringNotContainsString('Sukakarya', (string) $kk->alamat);
        $this->assertStringContainsString('RT 01', (string) $kk->alamat);
    }

    public function test_baris_kepala_keluarga_melengkapi_kk_bukan_jadi_anggota(): void
    {
        // Nama, No. KK, dan tanggal lahir di bawah adalah SINTETIS (kode wilayah
        // 999999 tidak ada di Permendagri). Sebelum 2026-08-20 tes ini memakai
        // data warga RW 07 yang sungguhan - repositori ini publik, jadi contoh
        // di tes tidak boleh diambil dari orang yang benar-benar ada.
        // KK dulu.
        $csvKk = "Nama KK,No. KK,RT,Alamat\nUjang Sutisna,9999992031200031,01,Kp. Parung\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => UploadedFile::fake()->createWithContent('kk.csv', $csvKk),
            ])->assertRedirect();

        // Anggota: baris kepala + satu istri; referensi via No. KK; tanggal
        // gaya spreadsheet dd-mm-yyyy.
        $csvAg = "No,Nama KK (Referensi),RT,Nama Anggota,L/P,Tgl Lahir,Status Keluarga,Pekerjaan,BPJS,Keterangan\n".
                 ",9999992031200031,RT 01,UJANG SUTISNA,L,07-03-1981,Kepala Keluarga,Wiraswasta,,\n".
                 ",9999992031200031,RT 01,SITI RAHAYU,P,19-06-1984,Istri,Mengurus Rumah Tangga,,\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/anggota', [
                'file_anggota' => UploadedFile::fake()->createWithContent('ag.csv', $csvAg),
            ])->assertRedirect();

        $kk = Keluarga::withoutGlobalScope('organisasi')->where('noKK', '9999992031200031')->firstOrFail();
        // Kepala TIDAK digandakan jadi anggota (hindari jiwa dobel)...
        $this->assertSame(
            1, \App\Models\Anggota::withoutGlobalScope('organisasi')
                ->where('keluarga_id', $kk->keluarga_id)->count()
        );
        // ...tapi melengkapi data KK-nya, seperti importer CLI.
        $this->assertSame('L', $kk->jenisKelaminKK);
        $this->assertSame('1981-03-07', $kk->tanggalLahirKK?->format('Y-m-d'));
        // Pekerjaan kepala ikut tersimpan di KK - dulu dibuang sehingga
        // mayoritas KK hasil impor tampak tanpa pekerjaan di Laporan.
        $this->assertSame('Wiraswasta', $kk->pekerjaan);

        $istri = \App\Models\Anggota::withoutGlobalScope('organisasi')
            ->where('keluarga_id', $kk->keluarga_id)->first();
        $this->assertSame('SITI RAHAYU', $istri->nama);
        $this->assertSame('1984-06-19', $istri->tanggalLahir?->format('Y-m-d'));
        $this->assertNotNull($istri->statusPekerjaan);
    }

    public function test_tanggal_tidak_terurai_dilewati_bukan_menggagalkan_impor(): void
    {
        $csvKk = "Nama KK,No. KK,RT,Alamat\nUji Tanggal,3205060000000001,01,Kp. Parung\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => UploadedFile::fake()->createWithContent('kk.csv', $csvKk),
            ])->assertRedirect();

        $csvAg = "Nama KK (Referensi),Nama Anggota,L/P,Tgl Lahir,Status Keluarga\n".
                 "3205060000000001,ANAK SATU,L,bukan-tanggal,Anak\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/anggota', [
                'file_anggota' => UploadedFile::fake()->createWithContent('ag.csv', $csvAg),
            ])->assertRedirect()->assertSessionMissing('error');

        $anak = \App\Models\Anggota::withoutGlobalScope('organisasi')->where('nama', 'ANAK SATU')->first();
        $this->assertNotNull($anak, 'Baris tetap terimpor meski tanggalnya rusak.');
        $this->assertNull($anak->tanggalLahir);
    }

    public function test_referensi_no_kk_tidak_nyangkut_ke_keluarga_tenant_lain(): void
    {
        // KK ber-noKK sama milik RW 10 (tenant lain), dibuat konsol dengan
        // organisasi eksplisit sebelum request.
        $kkAsing = new Keluarga([
            'keluarga_id' => 'kk_asing_ref', 'nama' => 'KK Asing',
            'alamat' => 'Jl. Asing', 'rt' => '01', 'noKK' => '3205069999999999',
        ]);
        $kkAsing->organization_id = Organization::where('slug', 'rw-10-sukakarya')->value('id');
        $kkAsing->saveQuietly();

        // KK cibunar ber-noKK SAMA. Dibuat langsung, bukan lewat impor: sejak
        // pemeriksa identitas hidup (2026-08-20), impor menolak No.KK yang sudah
        // terdaftar di portal lain. Keadaan yang diuji di sini tetap nyata, karena
        // data warisan tiga desa sudah memuat duplikat semacam ini sejak sebelum
        // penjagaan itu ada - dan justru baris warisan itulah yang dulu membuat
        // OR tanpa kurung menembus scope tenant.
        $kkCibunar = new Keluarga([
            'keluarga_id' => 'kk_cibunar_ref', 'nama' => 'KK Cibunar',
            'alamat' => 'Kp. Parung', 'rt' => '01', 'noKK' => '3205069999999999',
        ]);
        $kkCibunar->organization_id = Organization::where('slug', 'rw-01-cibunar')->value('id');
        $kkCibunar->saveQuietly();

        $csvAg = "Nama KK (Referensi),Nama Anggota,L/P,Status Keluarga\n".
                 "3205069999999999,ANAK CIBUNAR,L,Anak\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/anggota', [
                'file_anggota' => UploadedFile::fake()->createWithContent('ag.csv', $csvAg),
            ])->assertRedirect();

        // Anggota menempel ke KK CIBUNAR, bukan KK tenant lain - dulu
        // orWhere tanpa kurung membuat OR menembus scope tenant.
        $anak = \App\Models\Anggota::withoutGlobalScope('organisasi')->where('nama', 'ANAK CIBUNAR')->firstOrFail();
        $this->assertNotSame('kk_asing_ref', $anak->keluarga_id);
    }

    public function test_impor_menolak_no_kk_yang_sudah_terdaftar_di_portal_lain(): void
    {
        $kkAsing = new Keluarga([
            'keluarga_id' => 'kk_asing_tolak', 'nama' => 'KK Asing',
            'alamat' => 'Jl. Asing', 'rt' => '01', 'noKK' => '3205068888888888',
        ]);
        $kkAsing->organization_id = Organization::where('slug', 'rw-10-sukakarya')->value('id');
        $kkAsing->saveQuietly();

        $csv = "Nama KK,No. KK,RT,Alamat\nKK Baru Cibunar,3205068888888888,01,Kp. Parung\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => UploadedFile::fake()->createWithContent('kk.csv', $csv),
            ])->assertRedirect();

        $this->assertDatabaseMissing('keluargas', ['nama' => 'KK Baru Cibunar']);

        // Jalur massal tidak pernah menyebut LOKASI: satu unggahan bisa memuat
        // ribuan baris, jadi menyebut desa per baris berarti menyerahkan peta
        // tempat tinggal ribuan orang dalam satu klik.
        $pesan = session('success');
        $this->assertStringContainsString('DILEWATI', $pesan);
        $this->assertStringNotContainsString('RW 10', $pesan);
        $this->assertStringNotContainsString('Sukakarya', $pesan);
    }

    public function test_setting_kelurahan_tenant_menimpa_turunan_nama_desa(): void
    {
        \App\Models\AppSetting::create([
            'key' => 'kelurahan', 'value' => 'Kelurahan Cibunar Asli',
            'organization_id' => Organization::where('slug', 'rw-01-cibunar')->value('id'),
        ]);

        $csv = "Nama KK,RT,Kelurahan/Kecamatan\nUjang Setting,02,\n";
        $this->actingAs($this->adminCibunar)
            ->post('https://cibunar-rw01.desa.jabnet.id/warga/import/keluarga', [
                'file_keluarga' => UploadedFile::fake()->createWithContent('k.csv', $csv),
            ])->assertRedirect();

        $this->assertSame(
            'Kelurahan Cibunar Asli',
            Keluarga::withoutGlobalScope('organisasi')->where('nama', 'Ujang Setting')->value('kelurahan')
        );
    }
}
