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
            'operator' => 'op-tetangga',
        ]);
        // Transaksi ber-$fillable eksplisit tanpa organization_id, jadi nilai
        // kiriman create() dibuang mass assignment; isi langsung lewat properti
        // supaya fixture ini sungguh milik RW asing, bukan baris ber-org NULL.
        $this->trxAsing->organization_id = $this->rwAsing->id;
        $this->trxAsing->save();

        $this->admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'u_iso', 'username' => 'isoadmin', 'namaLengkap' => 'Iso Admin',
            'pin' => Hash::make('123456'), 'level' => 'superadmin', 'status' => 'aktif',
        ]));
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

    /** Baris asing untuk satu area, dibuat di jalur konsol (sebelum request). */
    private function barisAsing(string $model, array $atribut): object
    {
        $kelas = "App\\Models\\{$model}";

        return $kelas::create($atribut + ['organization_id' => $this->rwAsing->id]);
    }

    public function test_surat_tenant_lain_tak_terlihat_dan_tak_bisa_disentuh(): void
    {
        $surat = $this->barisAsing('Surat', [
            'surat_id' => 'SRT-asing01', 'kodeSurat' => 'SKTM', 'tahun' => 2026,
            'nomorUrut' => 7, 'nomorSurat' => '007/SKTM/RW99/2026',
            'tanggal' => '2026-08-01', 'pemohon' => 'Pemohon Tetangga',
            'keperluan' => 'keperluan rahasia', 'approval_step' => 'diajukan', 'status' => 'draft',
        ]);

        $this->actingAs($this->admin)->get('/surat')->assertOk()->assertDontSee('Pemohon Tetangga');
        $this->actingAs($this->admin)->get("/surat/{$surat->id}")->assertNotFound();
        $this->actingAs($this->admin)->post("/surat/{$surat->id}/approve")->assertNotFound();
        $this->actingAs($this->admin)->delete("/surat/{$surat->id}")->assertNotFound();
    }

    public function test_penomoran_surat_independen_per_tenant(): void
    {
        // Tenant asing sudah sampai nomor 7 tahun ini; nomor RW 10 tidak
        // boleh ikut melompat (max nomorUrut kini tersaring scope).
        $this->barisAsing('Surat', [
            'surat_id' => 'SRT-asing02', 'kodeSurat' => 'SKU', 'tahun' => date('Y'),
            'nomorUrut' => 7, 'nomorSurat' => '007/SKU/RW99/'.date('Y'),
            'tanggal' => '2026-08-01', 'pemohon' => 'Tetangga',
            'keperluan' => 'x', 'approval_step' => 'selesai', 'status' => 'selesai',
        ]);

        $this->actingAs($this->admin)->post('/surat', [
            'pemohon' => 'Warga Sepuluh', 'kodeSurat' => 'SKTM', 'keperluan' => 'uji nomor',
        ])->assertRedirect();

        $this->assertSame(1, \App\Models\Surat::where('keperluan', 'uji nomor')->value('nomorUrut'));
    }

    public function test_aduan_umkm_kegiatan_tenant_lain_tak_terlihat_dan_tak_bisa_disentuh(): void
    {
        $aduan = $this->barisAsing('Aduan', [
            'aduan_id' => 'ADU-asing01', 'user_id' => 999, 'pelapor' => 'Pelapor Tetangga',
            'rt' => '01', 'kategori' => 'kebersihan', 'isi' => 'aduan rahasia tetangga',
            'tanggal' => '2026-08-01', 'status' => 'baru',
        ]);
        $umkm = $this->barisAsing('Umkm', [
            'umkm_id' => 'UMKM-asing01', 'namaUsaha' => 'Warung Rahasia Tetangga',
            'pemilik' => 'Tetangga', 'rt' => '01',
        ]);
        $kegiatan = $this->barisAsing('Kegiatan', [
            'kegiatan_id' => 'KGT-asing01', 'judul' => 'Kegiatan Rahasia Tetangga',
            'tanggal' => '2026-08-20', 'tempat' => 'Balai RW 99',
        ]);

        $this->actingAs($this->admin)->get('/aduan')->assertOk()->assertDontSee('aduan rahasia tetangga');
        $this->actingAs($this->admin)->put("/aduan/{$aduan->id}/status", ['status' => 'selesai'])->assertNotFound();
        $this->actingAs($this->admin)->get('/umkm')->assertOk()->assertDontSee('Warung Rahasia Tetangga');
        $this->actingAs($this->admin)->delete("/umkm/{$umkm->id}")->assertNotFound();
        $this->actingAs($this->admin)->get('/kegiatan')->assertOk()->assertDontSee('Kegiatan Rahasia Tetangga');
        $this->actingAs($this->admin)->delete("/kegiatan/{$kegiatan->id}")->assertNotFound();
    }

    public function test_pendaftaran_tenant_lain_tak_terlihat_dan_tak_bisa_diproses(): void
    {
        $daftar = $this->barisAsing('Pendaftaran', [
            'nik' => '9999888877776666', 'no_kk' => '1111222233334444',
            'nama_lengkap' => 'Calon Warga Tetangga', 'rt' => '01',
            'no_wa' => '628111111111', 'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get('/pendaftaran')
            ->assertOk()->assertDontSee('Calon Warga Tetangga');
        $this->actingAs($this->admin)
            ->post("/pendaftaran/{$daftar->id}/approve")->assertNotFound();
    }

    public function test_log_sistem_tenant_lain_tak_terlihat(): void
    {
        $this->barisAsing('AuditLog', [
            'log_id' => 'LOG-asing01', 'tanggal' => now(), 'operator' => 'op-tetangga',
            'aksi' => 'create', 'collection' => 'transaksi', 'deskripsi' => 'jejak rahasia tetangga',
        ]);

        $this->actingAs($this->admin)->get('/log')->assertOk()->assertDontSee('jejak rahasia tetangga');
    }

    public function test_anggota_tenant_lain_tersaring_lewat_keluarganya(): void
    {
        \App\Models\Anggota::create([
            'anggota_id' => 'ag_asing01', 'keluarga_id' => $this->kkAsing->keluarga_id,
            'nama' => 'Anggota Tetangga', 'statusKeluarga' => 'Anak', 'jenisKelamin' => 'L',
        ]);

        $this->actingAs($this->admin)->get('/warga')->assertOk();

        // Setelah context hidup: anggota tenant lain tak terlihat model mana pun.
        $this->assertSame(0, \App\Models\Anggota::count());
        $this->assertSame(1, \App\Models\Anggota::withoutGlobalScope('organisasi')->count());
    }

    public function test_reset_data_hanya_mengosongkan_tenant_aktif(): void
    {
        // Baris lokal dibuat lewat jalur request supaya tercap RW 10.
        $this->actingAs($this->admin)->get('/warga')->assertOk();
        Keluarga::create([
            'keluarga_id' => 'kk_lokal01', 'nama' => 'Keluarga Lokal',
            'alamat' => 'Jl. Lokal', 'rt' => '01',
        ]);
        Transaksi::create([
            'transaksi_id' => 'TRX-lokal01', 'tanggal' => '2026-08-10',
            'jenis' => 'masuk', 'kas' => 'umum', 'kategori' => 'Uji',
            'keterangan' => 'Kas lokal', 'jumlah' => 5000, 'operator' => 'tes',
        ]);

        $this->actingAs($this->admin)
            ->post('/pengaturan/reset-data', ['confirm' => 'RESET'])
            ->assertRedirect();

        $semuaKk = Keluarga::withoutGlobalScope('organisasi');
        $this->assertSame(
            0, (clone $semuaKk)->where('organization_id', '!=', $this->rwAsing->id)->count(),
            'Data tenant aktif harus terhapus.'
        );
        $this->assertSame(
            1, (clone $semuaKk)->where('organization_id', $this->rwAsing->id)->count(),
            'Reset tenant ini tidak boleh menghapus keluarga tenant lain.'
        );
        $semuaTrx = Transaksi::withoutGlobalScope('organisasi');
        $this->assertSame(
            0, (clone $semuaTrx)->where('transaksi_id', 'TRX-lokal01')->count(),
            'Transaksi tenant aktif harus ikut terhapus.'
        );
        $this->assertSame(
            1, (clone $semuaTrx)->where('organization_id', $this->rwAsing->id)->count(),
            'Reset tenant ini tidak boleh menghapus transaksi tenant lain.'
        );
    }

    public function test_pembersihan_duplikat_tidak_menyentuh_tenant_lain(): void
    {
        // Nama+RT sama dengan kkAsing di setUp: pasangan duplikat di tenant
        // lain, dibuat konsol sebelum request pertama.
        Keluarga::create([
            'keluarga_id' => 'kk_asing_dup', 'nama' => 'Keluarga Tetangga',
            'alamat' => 'Jl. Tetangga', 'rt' => '01',
            'organization_id' => $this->rwAsing->id,
        ]);

        // Duplikat lokal membuktikan pembersihannya sendiri tetap bekerja.
        $this->actingAs($this->admin)->get('/warga')->assertOk();
        foreach (['kk_dup_a', 'kk_dup_b'] as $id) {
            Keluarga::create([
                'keluarga_id' => $id, 'nama' => 'Keluarga Kembar',
                'alamat' => 'Jl. Lokal', 'rt' => '02',
            ]);
        }

        $this->actingAs($this->admin)->post('/pengaturan/remove-duplicates')->assertRedirect();

        $this->assertSame(1, Keluarga::where('nama', 'Keluarga Kembar')->count());
        $this->assertSame(
            2, Keluarga::withoutGlobalScope('organisasi')
                ->where('organization_id', $this->rwAsing->id)->count(),
            'Duplikat tenant lain bukan urusan tenant ini.'
        );
    }

    public function test_laporan_tidak_menghitung_transaksi_tenant_lain(): void
    {
        // KK lokal dibuat konsol dengan organisasi eksplisit: dua RW aktif
        // membuat fallback berhenti menebak, dan request belum ada.
        Keluarga::create([
            'keluarga_id' => 'kk_lokal_lap', 'nama' => 'Keluarga Lokal',
            'alamat' => 'Jl. Lokal', 'rt' => '05', 'status' => 'aktif',
            'organization_id' => Organization::where('slug', 'rw-10-sukakarya')->value('id'),
        ]);
        // Transaksi tenant lain menunjuk keluarga_id yang kebetulan sama dengan
        // milik tenant ini - jalur bocor lama: pembaca DB::table tanpa scope.
        $trxRef = Transaksi::create([
            'transaksi_id' => 'TRX-asing-ref', 'tanggal' => date('Y').'-08-02',
            'jenis' => 'masuk', 'kas' => 'sampah', 'keterangan' => 'x',
            'jumlah' => 987654, 'operator' => 'op-tetangga',
            'refKeluargaId' => 'kk_lokal_lap',
        ]);
        $trxRef->organization_id = $this->rwAsing->id; // organization_id bukan fillable
        $trxRef->save();

        $respons = $this->actingAs($this->admin)->get('/laporan/ringkasan');
        $respons->assertOk();

        $this->assertNull(
            $respons->viewData('rankingRT')->firstWhere('rt', '05'),
            'Transaksi tenant lain ikut terhitung di ranking RT.'
        );
    }

    public function test_scope_aktif_di_dalam_request_dan_bisa_dibuka_eksplisit(): void
    {
        $this->actingAs($this->admin)->get('/warga')->assertOk();

        // Setelah context hidup: query biasa tersaring, escape hatch eksplisit.
        $this->assertSame(0, Keluarga::count());
        $this->assertSame(1, Keluarga::withoutGlobalScope('organisasi')->count());
    }
}
