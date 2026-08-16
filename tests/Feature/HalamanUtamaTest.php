<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\AppSetting;
use App\Models\Keluarga;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test: halaman utama benar-benar ter-render dengan data.
 *
 * Selain memastikan tidak ada error, tes ini menjaga agar Dashboard tetap hemat
 * query — perhitungannya pernah menjalankan query di dalam loop (per jenis kas,
 * per bulan, per RT).
 */
class HalamanUtamaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();

        $this->admin = $this->pasangPeranSetaraLevel(User::create([
            'user_id' => 'usr_admin', 'username' => 'admin',
            'namaLengkap' => 'Administrator', 'pin' => Hash::make('123456'),
            'level' => 'superadmin', 'status' => 'aktif', 'isDefault' => true,
        ]));

        AppSetting::create(['key' => 'tarif_sampah', 'value' => '5000']);

        foreach (['01', '02', '03'] as $i => $rt) {
            for ($n = 0; $n < 4; $n++) {
                $kk = Keluarga::create([
                    'keluarga_id' => "kk_{$rt}_{$n}",
                    'nama' => "Warga {$rt}-{$n}",
                    'rt' => $rt,
                    'alamat' => "Jl. Uji {$rt} No. {$n}",
                    'jumlahAnggota' => 3,
                    'status' => 'aktif',
                    'ikutSampah' => true,
                    'ikutPadaringan' => $n % 2 === 0,
                    'penghasilan' => '1 - 2.5 Juta',
                ]);

                for ($a = 0; $a < 2; $a++) {
                    Anggota::create([
                        'anggota_id' => "ag_{$rt}_{$n}_{$a}",
                        'keluarga_id' => $kk->keluarga_id,
                        'nama' => "Anggota {$rt}-{$n}-{$a}",
                        'jenisKelamin' => $a === 0 ? 'L' : 'P',
                        'statusKeluarga' => 'Anak',
                        'pekerjaan' => 'Pelajar',
                    ]);
                }
            }
        }

        foreach (['umum', 'sampah', 'padaringan'] as $kas) {
            foreach ([1, 2, 3] as $bulan) {
                Transaksi::create([
                    'transaksi_id' => "TRX-{$kas}-{$bulan}",
                    'tanggal' => date('Y').'-'.str_pad($bulan, 2, '0', STR_PAD_LEFT).'-10',
                    'jenis' => $bulan % 2 === 0 ? 'keluar' : 'masuk',
                    'kas' => $kas,
                    'keterangan' => 'Uji',
                    'jumlah' => 50000,
                    'operator' => 'admin',
                ]);
            }
        }
    }

    public static function halaman(): array
    {
        return [
            'dashboard' => ['/'],
            'data warga' => ['/warga'],
            'tambah kk' => ['/warga/create'],
            'iuran sampah' => ['/sampah'],
            'padaringan' => ['/padaringan'],
            'buku kas' => ['/bukukas'],
            'pengeluaran' => ['/kas/pengeluaran'],
            'sumbangan' => ['/kas/sumbangan'],
            'setor sampah' => ['/kas/setor'],
            'surat' => ['/surat'],
            'aduan' => ['/aduan'],
            'umkm' => ['/umkm'],
            'kegiatan' => ['/kegiatan'],
            'pendaftaran' => ['/pendaftaran'],
            'log sistem' => ['/log'],
            'pengaturan' => ['/pengaturan'],
            'laporan ringkasan' => ['/laporan/ringkasan'],
            'laporan demografi' => ['/laporan/demografi'],
            'laporan ekonomi' => ['/laporan/ekonomi'],
            'laporan permukiman' => ['/laporan/permukiman'],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_ter_render_untuk_superadmin(string $uri): void
    {
        $this->actingAs($this->admin)->get($uri)->assertOk();
    }

    public function test_halaman_login_bisa_dibuka_tanpa_akun(): void
    {
        $this->get('/login')->assertOk()->assertSee('Kampung Paru', false);
    }

    /**
     * Nama & tagline datang dari app_settings, bukan ditulis tetap di Blade.
     * Ini yang membuat project turunan untuk kampung lain cukup mengganti lewat
     * menu Pengaturan; kalau ada yang menulis ulang namanya di view, tes ini gagal.
     */
    public function test_identitas_aplikasi_diambil_dari_pengaturan(): void
    {
        AppSetting::updateOrCreate(['key' => 'nama_aplikasi'], ['value' => 'Kampung Uji']);
        AppSetting::updateOrCreate(['key' => 'tagline_aplikasi'], ['value' => "Baris satu.\nBaris dua."]);
        AppSetting::updateOrCreate(['key' => 'lokasi_singkat'], ['value' => 'Tasikmalaya']);

        $this->get('/login')->assertOk()
            ->assertSee('Kampung Uji', false)
            ->assertSee('Baris satu.<br />', false)
            ->assertSee('Tasikmalaya', false)
            ->assertDontSee('Kampung Paru', false);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('Kampung Uji', false);
    }

    public function test_tagline_dari_pengaturan_tidak_dirender_sebagai_html(): void
    {
        AppSetting::updateOrCreate(['key' => 'tagline_aplikasi'], ['value' => '<script>alert(1)</script>']);

        $this->get('/login')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * Dashboard dulunya menjalankan puluhan query karena ada query di dalam loop
     * (6 untuk saldo, 6 untuk saldo kumulatif, 12 untuk tren bulanan, plus 3 query
     * per RT). Sekarang jumlahnya tetap dan tidak ikut tumbuh saat RT bertambah.
     *
     * Rincian plafon: 16 query dashboard + 2 resolver tenant (Phase B2:
     * domains + organizations) + 2 level efektif (Phase E1: assignment; peta
     * induk hanya bila user punya assignment). Kalau menaikkan plafon ini,
     * tulis komponen barunya - dan ingat penjaga strukturalnya tes di bawah.
     */
    public function test_dashboard_tidak_menjalankan_query_di_dalam_loop(): void
    {
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/')->assertOk();
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            21,
            $jumlahQuery,
            "Dashboard menjalankan {$jumlahQuery} query — indikasi query di dalam loop muncul lagi."
        );
    }

    /**
     * Penjaga sesungguhnya: menambah RT tidak boleh menambah jumlah query.
     */
    public function test_jumlah_query_dashboard_tidak_tumbuh_bersama_jumlah_rt(): void
    {
        $hitung = function (): int {
            // Identitas aplikasi diingat per container, dan di dalam satu tes container
            // dipakai ulang antar request (di produksi tiap request container baru).
            // Tanpa dilupakan, pengukuran kedua kehilangan satu query dan perbandingan
            // jadi membandingkan dua kondisi berbeda.
            app()->forgetInstance('identitas.aplikasi');
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->admin)->get('/')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $sebelum = $hitung();

        foreach (['04', '05', '06', '07'] as $rt) {
            Keluarga::create([
                'keluarga_id' => "kk_tambahan_{$rt}", 'nama' => "Warga {$rt}", 'rt' => $rt,
                'alamat' => 'Jl. Tambahan', 'jumlahAnggota' => 1, 'status' => 'aktif',
                'ikutSampah' => true, 'ikutPadaringan' => true,
            ]);
        }

        $this->assertSame($sebelum, $hitung(), 'Jumlah query ikut bertambah saat RT bertambah.');
    }
}
