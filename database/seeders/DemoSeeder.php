<?php

namespace Database\Seeders;

use App\Models\Aduan;
use App\Models\Anggota;
use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\IuranPadaringan;
use App\Models\IuranSampah;
use App\Models\Kegiatan;
use App\Models\Keluarga;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Surat;
use App\Models\Transaksi;
use App\Models\Umkm;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Isi instalasi DEMO (mis. demo-sukawarga.jabnet.id) dengan data fiktif.
 *
 * Dijalankan manual: `php artisan db:seed --class=DemoSeeder --force`, pada
 * database demo yang baru dimigrasi. Menolak berjalan bila DEMO_MODE mati
 * atau bila sudah ada data warga, jadi tidak mungkin menimpa produksi.
 *
 * Semua data jelas fiktif: nama berawalan "[Demo]", NIK/No.KK berawalan
 * 9999 (tidak ada kode wilayah 99), nomor HP 62800000xxxx. Tidak ada satu pun
 * data warga sungguhan yang disalin.
 */
class DemoSeeder extends Seeder
{
    private const NAMA_DEPAN = [
        'Budi', 'Siti', 'Agus', 'Dewi', 'Asep', 'Rina', 'Dedi', 'Nining', 'Ujang', 'Euis',
        'Hendra', 'Lilis', 'Yayan', 'Tuti', 'Cecep', 'Ani', 'Dadang', 'Neng', 'Iwan', 'Popon',
        'Jajang', 'Enok', 'Wawan', 'Imas', 'Ade',
    ];

    private const BULAN = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];

    private int $rwId;

    public function run(): void
    {
        if (! config('app.demo')) {
            throw new \RuntimeException('DemoSeeder hanya untuk instalasi demo: setel DEMO_MODE=true di .env.');
        }
        $host = strtolower(trim((string) config('app.demo_host')));
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            throw new \RuntimeException('Isi DEMO_HOST dengan nama host demo, mis. demo-sukawarga.jabnet.id.');
        }
        $this->pastikanDatabaseDemoKosong($host);

        $pin = trim((string) config('app.demo_pin'));
        $pinDicetak = $pin === '';
        if ($pinDicetak) {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } elseif (! preg_match('/^\d{6}$/', $pin)) {
            throw new \RuntimeException('DEMO_PIN harus 6 angka.');
        }

        DB::transaction(function () use ($host, $pin) {
            $this->siapkanOrganisasi($host);
            $keluarga = $this->buatKeluarga();
            $this->buatIuranDanKas($keluarga);
            $this->buatLayanan($keluarga);
            $this->buatAkun($keluarga, $pin);
        });

        $this->command?->info("Data demo dibuat untuk https://{$host} (akun: demo.ketua, demo.sekretaris, demo.bendahara, demo.rt, demo.warga).");
        if ($pinDicetak) {
            $this->command?->warn("PIN akun demo: {$pin}");
            $this->command?->warn('Catat sekarang: PIN ini tidak disimpan di mana pun dan tidak akan ditampilkan lagi.');
        }
    }

    /**
     * Pengaman berlapis: seeder ini MENGHAPUS seluruh pemetaan domain dan
     * mengganti nama organisasi, jadi harus mustahil berjalan di database
     * sungguhan walau DEMO_MODE tertinggal menyala di .env yang disalin.
     */
    private function pastikanDatabaseDemoKosong(string $host): void
    {
        $hostAplikasi = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($hostAplikasi !== $host) {
            throw new \RuntimeException("APP_URL ({$hostAplikasi}) tidak sama dengan DEMO_HOST ({$host}). DemoSeeder dibatalkan.");
        }

        // Host bawaan migrasi di instalasi baru; host lain berarti database ini
        // pernah dipakai membuka tenant sungguhan.
        $hostBawaan = ['paru.jabnet.id', 'sukawarga10.jabnet.id', 'localhost', '127.0.0.1', 'desa.jabnet.id', 'sukakarya.desa.jabnet.id'];
        $hostLain = Domain::whereNotIn('hostname', $hostBawaan)->pluck('hostname');
        if ($hostLain->isNotEmpty()) {
            throw new \RuntimeException('Database ini sudah memiliki domain tenant ('.$hostLain->implode(', ').'). DemoSeeder hanya untuk database baru.');
        }

        // Lintas tenant dengan sadar: yang diperiksa seluruh isi database.
        $terisi = Keluarga::withoutGlobalScope('organisasi')->exists()
            || Transaksi::withoutGlobalScope('organisasi')->exists()
            || Surat::withoutGlobalScope('organisasi')->exists()
            || User::count() > 1;
        if ($terisi) {
            throw new \RuntimeException('Database sudah berisi data (warga, transaksi, surat, atau lebih dari satu akun). DemoSeeder hanya untuk database demo yang kosong.');
        }
    }

    /** Ganti nama organisasi bawaan, buang host produksi, daftarkan host demo. */
    private function siapkanOrganisasi(string $host): void
    {
        // Slug sengaja TIDAK diubah (beku, dirujuk migrasi); hanya nama tampilannya.
        $desa = Organization::where('slug', 'sukakarya')->firstOrFail();
        $rw = Organization::where('slug', 'rw-10-sukakarya')->firstOrFail();
        $desa->update(['name' => '[Demo] Desa Contoh', 'code' => 'DEMO']);
        $rw->update(['name' => 'RW 01', 'code' => 'RW01']);
        $this->rwId = $rw->id;

        // Host bawaan migrasi (desa.jabnet.id, paru.jabnet.id, localhost, ...)
        // milik produksi; database demo hanya boleh mengenal host demo.
        Domain::query()->delete();
        Domain::create(['organization_id' => $rw->id, 'hostname' => $host, 'is_primary' => true]);

        foreach (['01', '02', '03', '04'] as $rt) {
            Organization::firstOrCreate(
                ['slug' => "rt-{$rt}-demo"],
                ['parent_id' => $rw->id, 'type' => Organization::TYPE_RT, 'name' => "RT {$rt}", 'code' => "RT{$rt}", 'status' => 'aktif']
            );
        }

        $identitas = [
            'nama_aplikasi' => 'Portal Warga Demo',
            'tagline_aplikasi' => "Contoh portal warga digital.\nSemua data di situs ini fiktif.",
            'lokasi_singkat' => 'Situs demo',
            'alamat_portal' => $host,
            'nama_rw' => 'RW 01',
            'ketua_rw' => '[Demo] Ketua RW',
            'kelurahan' => '[Demo] Desa Contoh',
            'kecamatan' => '[Demo] Kecamatan Contoh',
            'kabupaten' => '[Demo] Kabupaten Contoh',
            'alamat_rw' => '[Demo] Jl. Contoh No. 1',
            'tarif_sampah' => '5000',
            'tarif_padaringan' => '15000',
        ];
        foreach ($identitas as $key => $nilai) {
            AppSetting::simpanUntuk($rw->id, $key, $nilai);
        }
    }

    /** @return list<Keluarga> */
    private function buatKeluarga(): array
    {
        $hasil = [];
        foreach (self::NAMA_DEPAN as $i => $nama) {
            $n = $i + 1;
            $kk = Keluarga::create([
                'keluarga_id' => 'kk_demo_'.Str::lower(Str::random(10)),
                'organization_id' => $this->rwId,
                'nama' => "[Demo] {$nama} Contoh",
                'noKK' => sprintf('9999%012d', 100000 + $n),
                'nik' => sprintf('9999%012d', 500000 + $n),
                'noHP' => sprintf('62800000%04d', $n),
                'alamat' => "[Demo] Gang Contoh No. {$n}",
                'rt' => sprintf('%02d', ($i % 4) + 1),
                'rw' => '01',
                'kelurahan' => '[Demo] Desa Contoh',
                'kecamatan' => '[Demo] Kecamatan Contoh',
                'status' => 'aktif',
                'jumlahAnggota' => 1,
                'ikutSampah' => $i % 5 !== 0,
                'ikutPadaringan' => $i % 3 === 0,
                'pekerjaan' => ['Petani', 'Pedagang', 'Buruh', 'Wiraswasta', 'Guru'][$i % 5],
                'jenisKelaminKK' => $i % 2 === 0 ? 'L' : 'P',
                'statusRumah' => ['Milik Sendiri', 'Sewa', 'Menumpang'][$i % 3],
            ]);

            $jumlahAnggota = $i % 4;
            for ($a = 1; $a <= $jumlahAnggota; $a++) {
                Anggota::create([
                    'anggota_id' => 'ag_'.Str::uuid()->toString(),
                    'keluarga_id' => $kk->keluarga_id,
                    'nama' => "[Demo] Anggota {$a} Keluarga {$nama}",
                    'jenisKelamin' => $a % 2 === 0 ? 'P' : 'L',
                    'statusKeluarga' => $a === 1 ? 'Istri/Suami' : 'Anak',
                    'nik' => sprintf('9999%012d', 800000 + $n * 10 + $a),
                ]);
            }
            $kk->update(['jumlahAnggota' => 1 + $jumlahAnggota]);
            $hasil[] = $kk;
        }

        return $hasil;
    }

    /** @param list<Keluarga> $daftar */
    private function buatIuranDanKas(array $daftar): void
    {
        $tahun = (int) now()->format('Y');
        $bulanBerjalan = (int) now()->format('n');
        $tarifSampah = 5000;
        $tarifPadaringan = 15000;

        foreach ($daftar as $i => $kk) {
            // Sebagian besar lunas sampai bulan berjalan; sekitar seperempat
            // menunggak 1-2 bulan supaya laporan tunggakan tetap berisi.
            $lunasSampai = max(0, $bulanBerjalan - ($i % 4 === 3 ? 1 + ($i % 2) : 0));

            if ($kk->ikutSampah && $lunasSampai > 0) {
                $weeks = [];
                $tanggal = [];
                for ($b = 0; $b < $lunasSampai; $b++) {
                    $bulan = self::BULAN[$b];
                    $tgl = $this->tanggalLampau($tahun, $b + 1, 10);
                    $kunci = [];
                    for ($m = 1; $m <= 4; $m++) {
                        $weeks["{$bulan}-M{$m}"] = 'lunas';
                        $tanggal["{$bulan}-M{$m}"] = $tgl;
                        $kunci[] = "{$bulan}-M{$m}";
                    }
                    $this->transaksi($tgl, 'masuk', 'sampah', "Iuran Sampah {$bulan} - {$kk->nama} (RT {$kk->rt})", 4 * $tarifSampah, $kk->id, $kunci);
                }
                IuranSampah::create(['keluarga_id' => $kk->id, 'tahun' => $tahun, 'weeks' => $weeks, 'weekDates' => $tanggal]);
            }

            if ($kk->ikutPadaringan && $lunasSampai > 0) {
                $months = [];
                $tanggal = [];
                for ($b = 0; $b < $lunasSampai; $b++) {
                    $bulan = self::BULAN[$b];
                    $tgl = $this->tanggalLampau($tahun, $b + 1, 12);
                    $months[$bulan] = true;
                    $tanggal[$bulan] = $tgl;
                    $this->transaksi($tgl, 'masuk', 'padaringan', "Iuran Padaringan {$bulan} - {$kk->nama} (RT {$kk->rt})", $tarifPadaringan, $kk->id, [$bulan]);
                }
                IuranPadaringan::create(['keluarga_id' => $kk->id, 'tahun' => $tahun, 'months' => $months, 'monthDates' => $tanggal]);
            }
        }

        // Beberapa pengeluaran kas umum supaya buku kas punya dua sisi.
        foreach (['[Demo] Beli alat kebersihan' => 250000, '[Demo] Konsumsi rapat RW' => 180000, '[Demo] Perbaikan lampu jalan' => 320000] as $ket => $jumlah) {
            $this->transaksi(sprintf('%d-%02d-15', $tahun, max(1, $bulanBerjalan - 1)), 'keluar', 'umum', $ket, $jumlah, null, null);
        }
    }

    /** Tanggal di bulan itu, tidak pernah melewati hari ini (awal bulan berjalan). */
    private function tanggalLampau(int $tahun, int $bulan, int $hari): string
    {
        return now()->setDate($tahun, $bulan, $hari)->min(now())->toDateString();
    }

    private function transaksi(string $tanggal, string $jenis, string $kas, string $keterangan, int $jumlah, ?int $refKeluarga, ?array $periode): void
    {
        $prefix = ['sampah' => 'KS-', 'padaringan' => 'KP-', 'umum' => 'TRX-'][$kas];
        // organization_id bukan fillable Transaksi: diisi eksplisit lewat properti.
        $trx = new Transaksi([
            'transaksi_id' => $prefix.strtoupper(Str::random(12)),
            'tanggal' => $tanggal,
            'jenis' => $jenis,
            'kas' => $kas,
            'kategori' => $jenis === 'masuk' ? 'Iuran' : 'Operasional',
            'keterangan' => $keterangan,
            'jumlah' => $jumlah,
            'refKeluargaId' => $refKeluarga,
            'periode' => $periode,
            'operator' => 'demo.bendahara',
        ]);
        $trx->organization_id = $this->rwId;
        $trx->save();
    }

    /** @param list<Keluarga> $daftar */
    private function buatLayanan(array $daftar): void
    {
        $tahun = (int) now()->format('Y');
        $surat = [['SKD', 'Keperluan administrasi bank'], ['SKTM', 'Pengajuan beasiswa'], ['SKU', 'Izin usaha warung'],
            ['SKCK', 'Melamar pekerjaan'], ['SKD', 'Pembuatan KTP'], ['SKP', 'Pindah domisili']];
        foreach ($surat as $i => [$kode, $keperluan]) {
            $urut = $i + 1;
            Surat::create([
                'surat_id' => 'SRT-'.strtoupper(Str::random(12)),
                'organization_id' => $this->rwId,
                'kodeSurat' => $kode, 'tahun' => $tahun, 'nomorUrut' => $urut,
                'nomorSurat' => sprintf('%03d/%s/RW01/%d', $urut, $kode, $tahun),
                'tanggal' => now()->subDays(30 - $i * 4)->toDateString(),
                'pemohon' => $daftar[$i]->nama, 'keperluan' => "[Demo] {$keperluan}",
                'status' => $i < 4 ? 'selesai' : 'draft',
                'approval_step' => $i < 4 ? 'selesai' : 'diajukan',
                'rt_target' => $daftar[$i]->rt,
            ]);
        }

        $aduan = [['Kebersihan', 'Sampah menumpuk di ujung gang'], ['Keamanan', 'Lampu jalan RT 02 mati'],
            ['Infrastruktur', 'Saluran air tersumbat'], ['Sosial', 'Usulan kerja bakti bulanan']];
        foreach ($aduan as $i => [$kategori, $isi]) {
            Aduan::create([
                'aduan_id' => 'ADU-'.strtoupper(Str::random(12)),
                'organization_id' => $this->rwId,
                'tanggal' => now()->subDays(20 - $i * 5)->toDateString(),
                'pelapor' => $daftar[$i + 5]->nama, 'rt' => $daftar[$i + 5]->rt,
                'kategori' => $kategori, 'prioritas' => ['tinggi', 'sedang', 'rendah', 'sedang'][$i],
                'isi' => "[Demo] {$isi}", 'status' => ['baru', 'proses', 'selesai', 'baru'][$i],
            ]);
        }

        $kegiatan = [['Kerja bakti lingkungan', 5, 'selesai'], ['Posyandu balita', 12, 'direncanakan'],
            ['Rapat pengurus RW', 20, 'direncanakan'], ['Pengajian bulanan', -10, 'selesai']];
        foreach ($kegiatan as [$judul, $hari, $status]) {
            Kegiatan::create([
                'kegiatan_id' => 'KGT-'.strtoupper(Str::random(12)),
                'organization_id' => $this->rwId,
                'judul' => "[Demo] {$judul}", 'tanggal' => now()->addDays($hari)->toDateString(),
                'waktu' => '08:00', 'tempat' => '[Demo] Balai Warga', 'status' => $status,
            ]);
        }

        $usaha = [['Warung Sembako', 'Kuliner & Sembako'], ['Jahit Pakaian', 'Jasa'], ['Keripik Singkong', 'Makanan Olahan'],
            ['Bengkel Motor', 'Jasa'], ['Kerajinan Bambu', 'Kerajinan']];
        foreach ($usaha as $i => [$nama, $jenis]) {
            Umkm::create([
                'umkm_id' => 'UMKM-'.strtoupper(Str::random(12)),
                'organization_id' => $this->rwId,
                'pemilik' => $daftar[$i + 10]->nama, 'rt' => $daftar[$i + 10]->rt,
                'namaUsaha' => "[Demo] {$nama}", 'jenis' => $jenis,
                'noHP' => $daftar[$i + 10]->noHP, 'status' => 'aktif',
            ]);
        }
    }

    /** @param list<Keluarga> $daftar */
    private function buatAkun(array $daftar, string $pin): void
    {
        $akun = [
            // username => [nama, level, slug peran, organisasi assignment]
            // Nama akun berawalan "Demo" (bukan "[Demo]") supaya inisial avatar terbaca.
            'demo.ketua' => ['Demo Ketua RW', 'ketua_rw', 'rw_admin', $this->rwId],
            'demo.sekretaris' => ['Demo Sekretaris RW', 'sekretaris', 'rw_secretary', $this->rwId],
            'demo.bendahara' => ['Demo Bendahara RW', 'bendahara', 'rw_finance', $this->rwId],
            'demo.rt' => ['Demo Petugas RT 01', 'petugas_rt', 'rt_admin', Organization::where('slug', 'rt-01-demo')->value('id')],
            'demo.warga' => ['Demo Warga', 'warga', null, null],
        ];

        foreach ($akun as $username => [$nama, $level, $slugPeran, $orgId]) {
            $user = User::create([
                'user_id' => 'USR-'.strtoupper(Str::random(12)),
                'username' => $username, 'namaLengkap' => $nama,
                'pin' => Hash::make($pin), 'level' => $level, 'status' => 'aktif',
                'rt' => $level === 'petugas_rt' ? '01' : ($level === 'warga' ? $daftar[0]->rt : null),
                'keluarga_id' => $level === 'warga' ? $daftar[0]->keluarga_id : null,
                'isDefault' => false,
            ]);
            if ($slugPeran !== null) {
                UserRoleAssignment::create([
                    'user_id' => $user->id,
                    'role_id' => Role::where('slug', $slugPeran)->value('id'),
                    'organization_id' => $orgId,
                ]);
            }
        }
    }
}
