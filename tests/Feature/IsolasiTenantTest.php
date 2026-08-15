<?php

namespace Tests\Feature;

use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase E2 (jalur uang): isolasi tenant sesuai §37 dokumen arsitektur.
 * RW A tidak boleh membaca, membayar, atau membatalkan milik RW B - juga
 * lewat tebakan ID langsung (IDOR). Ditegakkan global scope ScopedKeOrganisasi
 * pada Transaksi & Keluarga.
 *
 * Data tenant asing dibuat SEBELUM request pertama tes: begitu ada request,
 * TenantContext hidup dan trait MilikOrganisasi menimpa organization_id
 * kiriman dengan milik tenant request (by design).
 */
class IsolasiTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $rwAsing;

    private Keluarga $kkAsing;

    private Transaksi $trxAsing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rwAsing = Organization::create([
            'parent_id' => Organization::where('slug', 'sukakarya')->value('id'),
            'type' => Organization::TYPE_RW, 'name' => 'RW 99',
            'code' => 'RW99', 'slug' => 'rw-99-sukakarya',
        ]);

        // Jalur konsol (context belum ada): organization_id eksplisit dihormati.
        $this->kkAsing = Keluarga::create([
            'keluarga_id' => 'kk_asing01', 'nama' => 'Keluarga Tetangga',
            'alamat' => 'Jl. Tetangga', 'rt' => '01',
            'organization_id' => $this->rwAsing->id,
        ]);
        $this->trxAsing = Transaksi::create([
            'transaksi_id' => 'TRX-asing01', 'tanggal' => '2026-08-01',
            'jenis' => 'masuk', 'kas' => 'sampah', 'kategori' => 'Iuran Sampah',
            'keterangan' => 'Iuran rahasia tetangga', 'jumlah' => 987654,
            'operator' => 'op-tetangga', 'organization_id' => $this->rwAsing->id,
        ]);

        $this->admin = User::create([
            'user_id' => 'u_iso', 'username' => 'isoadmin', 'namaLengkap' => 'Iso Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]);
    }

    public function test_daftar_warga_tidak_memuat_kk_tenant_lain(): void
    {
        $this->actingAs($this->admin)->get('/warga')
            ->assertOk()->assertDontSee('Keluarga Tetangga');
    }

    public function test_kk_tenant_lain_tidak_bisa_dibuka_lewat_id_langsung(): void
    {
        // IDOR: menebak ID numerik milik tenant lain harus 404, bukan 200.
        $this->actingAs($this->admin)
            ->get("/warga/{$this->kkAsing->id}/edit")->assertNotFound();
        $this->actingAs($this->admin)
            ->delete("/warga/{$this->kkAsing->id}")->assertNotFound();
    }

    public function test_halaman_billing_tidak_memuat_kk_tenant_lain(): void
    {
        $this->actingAs($this->admin)->get('/sampah')
            ->assertOk()->assertDontSee('Keluarga Tetangga');
    }

    public function test_tidak_bisa_mencatat_pembayaran_untuk_kk_tenant_lain(): void
    {
        $this->actingAs($this->admin)
            ->post("/sampah/bayar/{$this->kkAsing->id}", [
                'tahun' => 2026, 'bulan_key' => 'JAN',
                'minggu' => ['M1'], 'tanggal_bayar' => '2026-08-15',
            ])
            ->assertNotFound();

        $this->assertSame(
            1, Transaksi::withoutGlobalScope('organisasi')->count(),
            'Tidak boleh ada transaksi baru tercatat untuk KK tenant lain.'
        );
    }

    public function test_buku_kas_tidak_memuat_transaksi_tenant_lain(): void
    {
        $this->actingAs($this->admin)->get('/bukukas?tahun=2026')
            ->assertOk()->assertDontSee('Iuran rahasia tetangga');
    }

    public function test_transaksi_tenant_lain_tidak_bisa_divoid(): void
    {
        $this->actingAs($this->admin)
            ->post("/transaksi/{$this->trxAsing->id}/void", ['void_reason' => 'coba-coba'])
            ->assertNotFound();

        $this->assertFalse(
            (bool) Transaksi::withoutGlobalScope('organisasi')->find($this->trxAsing->id)->voided,
            'Transaksi tenant lain tidak boleh berubah.'
        );
    }

    public function test_konsol_tetap_melihat_semua_karena_context_tidak_ada(): void
    {
        // Jalur importer/tinker bekerja lintas isi tabel; scope hanya hidup
        // di dalam request. Tes ini TANPA request HTTP sama sekali.
        $this->assertSame(1, Keluarga::count());
        $this->assertSame(1, Transaksi::count());
    }

    public function test_scope_aktif_di_dalam_request_dan_bisa_dibuka_eksplisit(): void
    {
        $this->actingAs($this->admin)->get('/warga')->assertOk();

        // Setelah context hidup: query biasa tersaring, escape hatch eksplisit.
        $this->assertSame(0, Keluarga::count());
        $this->assertSame(1, Keluarga::withoutGlobalScope('organisasi')->count());
    }
}
