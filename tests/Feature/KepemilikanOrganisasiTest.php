<?php

namespace Tests\Feature;

use App\Models\Concerns\MilikOrganisasi;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Transaksi;
use App\Models\Umkm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C multi-tenant: setiap baris tenant baru tercap organization_id,
 * dan nilainya TIDAK PERNAH ditentukan client. Aturan pengisiannya ada di
 * trait MilikOrganisasi.
 */
class KepemilikanOrganisasiTest extends TestCase
{
    use RefreshDatabase;

    /** Model tenant yang wajib memakai trait; tabel turunannya (anggota, iuran) sengaja tidak. */
    private const MODEL_TENANT = [
        \App\Models\Keluarga::class, \App\Models\User::class,
        \App\Models\Transaksi::class, \App\Models\Surat::class,
        \App\Models\Aduan::class, \App\Models\Umkm::class,
        \App\Models\Kegiatan::class, \App\Models\Pengeluaran::class,
        \App\Models\Sumbangan::class, \App\Models\SetorSampah::class,
        \App\Models\Pendaftaran::class, \App\Models\AuditLog::class,
    ];

    private function idRw(): int
    {
        return Organization::where('slug', 'rw-10-sukakarya')->value('id');
    }

    public function test_seluruh_model_tenant_memakai_trait(): void
    {
        foreach (self::MODEL_TENANT as $kelas) {
            $this->assertContains(
                MilikOrganisasi::class,
                class_uses_recursive($kelas),
                "{$kelas} kehilangan trait MilikOrganisasi - baris barunya tidak akan tercap organisasi."
            );
        }
    }

    public function test_baris_baru_dalam_request_tercap_dari_context(): void
    {
        // Request lewat resolver mengisi TenantContext (host default: localhost).
        $this->get('/login')->assertOk();

        $kk = Keluarga::create([
            'keluarga_id' => 'kk_cap01', 'nama' => 'Uji Cap', 'alamat' => 'Jl. Uji', 'rt' => '01',
        ]);

        $this->assertSame($this->idRw(), $kk->organization_id);
    }

    public function test_nilai_kiriman_client_ditimpa_context(): void
    {
        $this->get('/login')->assertOk();

        // Umkm ber-$guarded=[] - persis jalur suntikan mass assignment yang
        // harus mati: client tidak pernah menentukan tenant.
        $umkm = Umkm::create([
            'umkm_id' => 'UMKM-cap01', 'namaUsaha' => 'Warung Uji', 'pemilik' => 'Uji',
            'rt' => '01', 'organization_id' => 999999,
        ]);

        $this->assertSame($this->idRw(), $umkm->organization_id);
        $this->assertNotSame(999999, $umkm->organization_id);
    }

    public function test_di_konsol_default_ke_satu_satunya_rw_aktif(): void
    {
        // Tanpa request: TenantContext kosong (jalur importer/seeder/tinker).
        $kk = Keluarga::create([
            'keluarga_id' => 'kk_cli01', 'nama' => 'Uji Konsol', 'alamat' => 'Jl. Uji', 'rt' => '02',
        ]);

        $this->assertSame($this->idRw(), $kk->organization_id);
    }

    public function test_di_konsol_nilai_eksplisit_dipertahankan(): void
    {
        $lain = Organization::create([
            'parent_id' => null, 'type' => Organization::TYPE_RW,
            'name' => 'RW Lain', 'code' => 'RWLAIN', 'slug' => 'rw-lain', 'status' => 'nonaktif',
        ]);

        $kk = Keluarga::create([
            'keluarga_id' => 'kk_cli02', 'nama' => 'Uji Eksplisit', 'alamat' => 'Jl. Uji',
            'rt' => '02', 'organization_id' => $lain->id,
        ]);

        $this->assertSame($lain->id, $kk->organization_id);
    }

    public function test_fallback_berhenti_menebak_saat_rw_lebih_dari_satu(): void
    {
        Organization::create([
            'parent_id' => null, 'type' => Organization::TYPE_RW,
            'name' => 'RW Kedua', 'code' => 'RW02X', 'slug' => 'rw-kedua', 'status' => 'aktif',
        ]);

        $kk = Keluarga::create([
            'keluarga_id' => 'kk_cli03', 'nama' => 'Uji Ambigu', 'alamat' => 'Jl. Uji', 'rt' => '03',
        ]);

        $this->assertNull(
            $kk->organization_id,
            'Dengan dua RW aktif, menebak tenant lebih berbahaya daripada null.'
        );
    }

    public function test_transaksi_tetap_tercap_meski_fillable_membuang_kiriman(): void
    {
        $this->get('/login')->assertOk();

        // Transaksi ber-$fillable eksplisit tanpa organization_id: kiriman
        // dibuang mass assignment, lalu trait mengecap nilai yang benar.
        $trx = Transaksi::create([
            'transaksi_id' => 'TRX-cap01', 'tanggal' => '2026-08-15', 'jenis' => 'masuk',
            'kas' => 'umum', 'kategori' => 'Uji', 'keterangan' => 'Uji cap',
            'jumlah' => 1000, 'operator' => 'tes', 'organization_id' => 999999,
        ]);

        $this->assertSame($this->idRw(), $trx->fresh()->organization_id);
    }

    public function test_relasi_organization_terbaca(): void
    {
        $this->get('/login')->assertOk();
        $kk = Keluarga::create([
            'keluarga_id' => 'kk_rel01', 'nama' => 'Uji Relasi', 'alamat' => 'Jl. Uji', 'rt' => '01',
        ]);

        $this->assertSame('RW10', $kk->organization->code);
    }
}
