<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;

/**
 * Matriks kapabilitas per peran: satu-satunya sumber kebenaran "peran mana
 * boleh apa", untuk penjaga rute (middleware `izin:`) MAUPUN visibilitas menu
 * (`userCan()`). Menggantikan dua sistem lama yang tidak saling bicara:
 * hierarki linier `User::LEVEL_POWER` (mengikat) dan matriks menu
 * `role_permissions` (kosmetik belaka).
 *
 * Kelas konstanta, bukan file `config/`: repo ini belum punya satu pun config
 * kustom, dan matriks tidak pernah berbeda per environment - menambah config
 * berarti menambah kewajiban `config:cache` di DEPLOY.md tanpa manfaat. Pola
 * yang diikuti persis `User::LEVEL_POWER` dan `Surat::KODE_VALID`.
 *
 * Kunci bergaya `modul.aksi`, dan prefiks modulnya SENGAJA sama dengan menu key
 * `getAllMenuItems()`. Itu yang membuat menu dan rute membaca peta yang sama,
 * dan membuat aturan AGENTS.md #11 (`fitur:<menu key>`) bisa diuji mesin.
 */
class MatriksKapabilitas
{
    /**
     * Katalog seluruh kapabilitas yang dikenal sistem.
     * Label dipakai halaman matriks admin platform.
     */
    public const KATALOG = [
        'dashboard.lihat' => ['modul' => 'dashboard', 'label' => 'Membuka dashboard'],

        'warga.lihat' => ['modul' => 'warga', 'label' => 'Melihat data warga'],
        'warga.kelola' => ['modul' => 'warga', 'label' => 'Menambah, mengubah, menghapus data warga'],
        'warga.impor' => ['modul' => 'warga', 'label' => 'Mengimpor data warga dari CSV'],
        'warga.ekspor' => ['modul' => 'warga', 'label' => 'Mengekspor data warga ke CSV'],
        'warga.cari' => ['modul' => 'warga', 'label' => 'Memakai pencarian global warga'],

        'sampah.lihat' => ['modul' => 'sampah', 'label' => 'Melihat tagihan iuran sampah'],
        'sampah.tagih' => ['modul' => 'sampah', 'label' => 'Menerima pembayaran iuran sampah'],

        'padaringan.lihat' => ['modul' => 'padaringan', 'label' => 'Melihat tagihan padaringan'],
        'padaringan.tagih' => ['modul' => 'padaringan', 'label' => 'Menerima pembayaran padaringan'],

        'surat.lihat' => ['modul' => 'surat', 'label' => 'Melihat daftar surat'],
        'surat.ajukan' => ['modul' => 'surat', 'label' => 'Mengajukan permohonan surat'],
        'surat.buat' => ['modul' => 'surat', 'label' => 'Menerbitkan surat langsung'],
        'surat.ubah' => ['modul' => 'surat', 'label' => 'Mengubah data surat (pemohon, nomor, jenis)'],
        'surat.ubahIsi' => ['modul' => 'surat', 'label' => 'Mengubah isi naskah surat'],
        'surat.hapus' => ['modul' => 'surat', 'label' => 'Menghapus surat'],
        'surat.cetak' => ['modul' => 'surat', 'label' => 'Mencetak surat'],
        'surat.ttdRt' => ['modul' => 'surat', 'label' => 'Menandatangani tahap RT'],
        'surat.ttdRw' => ['modul' => 'surat', 'label' => 'Menandatangani tahap RW'],
        'surat.finalisasi' => ['modul' => 'surat', 'label' => 'Membubuhkan cap dan menyelesaikan surat'],
        'surat.tolak' => ['modul' => 'surat', 'label' => 'Menolak permohonan surat'],

        'pendaftaran.lihat' => ['modul' => 'pendaftaran', 'label' => 'Melihat pendaftaran warga baru'],
        'pendaftaran.putuskan' => ['modul' => 'pendaftaran', 'label' => 'Menyetujui atau menolak pendaftaran'],

        'umkm.lihat' => ['modul' => 'umkm', 'label' => 'Melihat daftar UMKM'],
        'umkm.kelola' => ['modul' => 'umkm', 'label' => 'Mendata dan menghapus UMKM'],

        'bukukas.lihat' => ['modul' => 'bukukas', 'label' => 'Melihat buku kas'],
        'bukukas.catat' => ['modul' => 'bukukas', 'label' => 'Mencatat transaksi buku kas'],

        'pengeluaran.lihat' => ['modul' => 'pengeluaran', 'label' => 'Melihat pengeluaran'],
        'pengeluaran.catat' => ['modul' => 'pengeluaran', 'label' => 'Mencatat pengeluaran'],

        'sumbangan.lihat' => ['modul' => 'sumbangan', 'label' => 'Melihat sumbangan'],
        'sumbangan.catat' => ['modul' => 'sumbangan', 'label' => 'Mencatat sumbangan'],

        'setor.lihat' => ['modul' => 'setor', 'label' => 'Melihat setoran sampah RT'],
        'setor.catat' => ['modul' => 'setor', 'label' => 'Mencatat setoran sampah RT'],

        'aduan.lihat' => ['modul' => 'aduan', 'label' => 'Melihat aduan warga'],
        'aduan.lapor' => ['modul' => 'aduan', 'label' => 'Mengirim aduan'],
        'aduan.tindak' => ['modul' => 'aduan', 'label' => 'Mengubah status aduan'],

        'mpwa.lihat' => ['modul' => 'mpwa', 'label' => 'Membuka halaman Broadcast WA'],
        'mpwa.broadcast' => ['modul' => 'mpwa', 'label' => 'Mengirim broadcast WhatsApp'],
        'mpwa.kelolaTemplate' => ['modul' => 'mpwa', 'label' => 'Mengubah template dan aturan notifikasi'],
        'mpwa.uji' => ['modul' => 'mpwa', 'label' => 'Menguji koneksi gateway WhatsApp'],

        'kegiatan.lihat' => ['modul' => 'kegiatan', 'label' => 'Melihat kegiatan RW'],
        'kegiatan.kelola' => ['modul' => 'kegiatan', 'label' => 'Menambah dan menghapus kegiatan'],

        'laporan.lihat' => ['modul' => 'laporan', 'label' => 'Membuka laporan'],

        'akun.lihat' => ['modul' => 'akun', 'label' => 'Melihat daftar akun'],
        'akun.kelola' => ['modul' => 'akun', 'label' => 'Membuat, mengubah, menghapus akun'],

        'log.lihat' => ['modul' => 'log', 'label' => 'Membuka log sistem'],

        'pengaturan.lihat' => ['modul' => 'pengaturan', 'label' => 'Membuka pengaturan'],
        'pengaturan.ubah' => ['modul' => 'pengaturan', 'label' => 'Mengubah pengaturan RW'],
        'pengaturan.pemeliharaan' => ['modul' => 'pengaturan', 'label' => 'Reset data dan hapus duplikat'],

        // Bukan menu key: aksinya hidup di halaman Buku Kas, tapi ia bukan
        // "modul" yang bisa dimatikan feature flag sendirian.
        'transaksi.void' => ['modul' => 'transaksi', 'label' => 'Membatalkan (void) transaksi'],

        // Fitur platform lintas tenant, sengaja di luar feature flag - lihat
        // komentar di routes/web.php pada grup Manajemen Desa.
        'platform.tenant' => ['modul' => 'platform', 'label' => 'Manajemen Desa dan RW'],
        'platform.pembaruan' => ['modul' => 'platform', 'label' => 'Pembaruan Sistem'],
        'platform.matriks' => ['modul' => 'platform', 'label' => 'Mengubah matriks kapabilitas tenant'],
    ];

    /**
     * Kapabilitas yang sengaja TIDAK dipegang peran mana pun secara bawaan.
     * Daftar ini yang membuat tes "key yatim" tetap tajam: key baru yang lupa
     * diberikan ke peran akan ketahuan, kecuali sengaja didaftarkan di sini.
     */
    public const KHUSUS_SUPERADMIN = [
        'pengaturan.pemeliharaan',
        'platform.tenant',
        'platform.pembaruan',
        'platform.matriks',
    ];

    /**
     * Kapabilitas yang TIDAK PERNAH boleh diberikan lewat override tenant.
     * Tanpa pagar ini, satu baris `app_settings` bisa mencetak admin platform.
     */
    public const TERLARANG_OVERRIDE = ['platform.tenant', 'platform.pembaruan', 'platform.matriks'];

    /**
     * Pembagian tugas pengurus RW. `superadmin` sengaja TIDAK dienumerasi -
     * lihat untukPeran(): ia selalu memegang seluruh katalog, sehingga
     * keputusan "operator portal tidak dibatasi" tidak lapuk saat key baru
     * ditambahkan, dan tidak bisa dicabut lewat data.
     */
    public const BAWAAN = [
        // Pengawas dan penanda tangan: melihat semua, mengubah di enam titik.
        'ketua_rw' => [
            'dashboard.lihat',
            'warga.lihat', 'warga.cari',
            'sampah.lihat', 'padaringan.lihat',
            'surat.lihat', 'surat.cetak', 'surat.ttdRw', 'surat.tolak',
            'pendaftaran.lihat', 'pendaftaran.putuskan',
            'umkm.lihat', 'kegiatan.lihat',
            'bukukas.lihat', 'pengeluaran.lihat', 'sumbangan.lihat', 'setor.lihat',
            'aduan.lihat', 'aduan.lapor',
            'mpwa.lihat',
            'laporan.lihat',
            'akun.lihat', 'akun.kelola',
            'log.lihat', 'pengaturan.lihat', 'pengaturan.ubah',
            'transaksi.void',
        ],

        // Juru tulis: seluruh siklus surat dan arsip warga, nol akses uang.
        'sekretaris' => [
            'dashboard.lihat',
            'warga.lihat', 'warga.kelola', 'warga.impor', 'warga.ekspor', 'warga.cari',
            'sampah.lihat', 'padaringan.lihat',
            'surat.lihat', 'surat.buat', 'surat.ubah', 'surat.ubahIsi',
            'surat.hapus', 'surat.cetak', 'surat.finalisasi',
            'pendaftaran.lihat',
            'umkm.lihat', 'umkm.kelola', 'kegiatan.lihat', 'kegiatan.kelola',
            'bukukas.lihat', 'pengeluaran.lihat', 'sumbangan.lihat', 'setor.lihat',
            'aduan.lihat', 'aduan.lapor', 'aduan.tindak',
            'mpwa.lihat', 'mpwa.broadcast', 'mpwa.kelolaTemplate', 'mpwa.uji',
            'laporan.lihat',
            'log.lihat', 'pengaturan.lihat',
        ],

        // Pemegang kas: seluruh pencatatan uang, nol akses surat. Void sengaja
        // tidak diberikan - pencatat tidak membatalkan catatannya sendiri.
        'bendahara' => [
            'dashboard.lihat',
            'warga.lihat', 'warga.cari',
            'sampah.lihat', 'sampah.tagih',
            'padaringan.lihat', 'padaringan.tagih',
            'umkm.lihat', 'kegiatan.lihat',
            'bukukas.lihat', 'bukukas.catat',
            'pengeluaran.lihat', 'pengeluaran.catat',
            'sumbangan.lihat', 'sumbangan.catat',
            'setor.lihat', 'setor.catat',
            'aduan.lihat', 'aduan.lapor',
            'laporan.lihat',
        ],

        // Petugas lapangan: mendata warganya, menagih, menyetor ke RW.
        // Impor/ekspor CSV sengaja TIDAK diberikan: berkasnya memuat PII
        // seluruh tenant, bukan hanya RT yang bersangkutan.
        'petugas_rt' => [
            'dashboard.lihat',
            'warga.lihat', 'warga.kelola', 'warga.cari',
            'sampah.lihat', 'sampah.tagih',
            'padaringan.lihat', 'padaringan.tagih',
            'surat.lihat', 'surat.cetak', 'surat.ttdRt', 'surat.tolak',
            'umkm.lihat', 'umkm.kelola', 'kegiatan.lihat', 'kegiatan.kelola',
            'setor.lihat', 'setor.catat',
            'aduan.lihat', 'aduan.lapor', 'aduan.tindak',
        ],

        // Warga: hanya urusannya sendiri. Penyaringan "miliknya" tetap di
        // controller (kepemilikan), kapabilitas hanya membuka pintunya.
        'warga' => [
            'dashboard.lihat',
            'surat.lihat', 'surat.ajukan', 'surat.cetak',
            'aduan.lihat', 'aduan.lapor',
        ],
    ];

    /**
     * Urutan penampilan label peran saat seorang user merangkap beberapa
     * peran. INI BUKAN HIERARKI HAK: kapabilitas selalu digabung (union),
     * tidak pernah "yang tertinggi menang". Angka hanya menentukan label mana
     * yang ditampilkan di sidebar dan daftar akun.
     */
    public const URUTAN_TAMPIL = [
        'superadmin' => 60,
        'admin' => 60,
        'super_admin' => 60,
        'ketua_rw' => 50,
        'sekretaris' => 40,
        'bendahara' => 30,
        'petugas_rt' => 20,
        'warga' => 10,
    ];

    /** Key AppSetting berisi delta override per tenant. */
    public const KEY_OVERRIDE = 'kapabilitas_peran';

    /** Seluruh key katalog, urut sesuai deklarasi. */
    public static function semua(): array
    {
        return array_keys(self::KATALOG);
    }

    /**
     * Kapabilitas efektif sebuah peran di tenant request ini.
     *
     * Peran setara superadmin di-short-circuit SEBELUM overlay override:
     * operator portal tidak bisa dilucuti lewat baris app_settings. Peran yang
     * tidak dikenal mendapat array kosong (fail-closed, meniru userCan()).
     */
    public static function untukPeran(string $peran): array
    {
        if (in_array($peran, User::LEVEL_ADMIN, true)) {
            return self::semua();
        }

        $bawaan = self::BAWAAN[$peran] ?? null;
        if ($bawaan === null) {
            return [];
        }

        $delta = self::overrideTenant()[$peran] ?? [];
        if ($delta === []) {
            return $bawaan;
        }

        $efektif = array_flip($bawaan);
        foreach ($delta as $kapabilitas => $aktif) {
            if ($aktif === true) {
                $efektif[$kapabilitas] = true;
            } else {
                unset($efektif[$kapabilitas]);
            }
        }

        // Urutkan mengikuti katalog supaya hasilnya stabil dan bisa dibandingkan.
        return array_values(array_intersect(self::semua(), array_keys($efektif)));
    }

    /**
     * Delta override milik tenant request, sudah dibersihkan.
     *
     * Key/peran tak dikenal DIBUANG saat baca (bukan ditolak): baris lama bisa
     * memuat key yang dihapus rilis berikutnya, dan satu key usang tidak boleh
     * mematikan seluruh matriks tenant. Penolakan bersuara dilakukan saat
     * MENULIS, di controller. Nilai non-bool dianggap false (fail-closed).
     */
    public static function overrideTenant(): array
    {
        return app(TenantContext::class)->ingat('kapabilitas.override', function () {
            try {
                $mentah = json_decode((string) AppSetting::nilai(self::KEY_OVERRIDE), true);
            } catch (\Throwable $e) {
                return [];
            }
            if (! is_array($mentah)) {
                return [];
            }

            return self::bersihkanDelta($mentah);
        });
    }

    /**
     * Saring delta ke bentuk aman: hanya peran bawaan yang dikenal, hanya key
     * katalog, nilai bool. `platform.*` tidak pernah bisa DIBERIKAN lewat
     * override - tanpa pagar ini satu baris setting bisa mencetak admin
     * platform.
     */
    public static function bersihkanDelta(array $mentah): array
    {
        $bersih = [];
        foreach ($mentah as $peran => $daftar) {
            if (! isset(self::BAWAAN[$peran]) || ! is_array($daftar)) {
                continue;
            }
            foreach ($daftar as $kapabilitas => $aktif) {
                if (! isset(self::KATALOG[$kapabilitas])) {
                    continue;
                }
                $aktif = $aktif === true;
                if ($aktif && in_array($kapabilitas, self::TERLARANG_OVERRIDE, true)) {
                    continue;
                }
                $bersih[$peran][$kapabilitas] = $aktif;
            }
        }

        return $bersih;
    }

    /**
     * Apakah user memegang SALAH SATU kapabilitas yang diminta (OR).
     *
     * Kapabilitas seluruh peran efektif DIGABUNG, bukan diambil yang
     * "tertinggi": pengurus yang merangkap sekretaris dan bendahara memperoleh
     * keduanya. Itulah bedanya matriks dengan hierarki.
     */
    public static function userPunya(?User $user, string ...$kapabilitas): bool
    {
        if ($user === null || $kapabilitas === []) {
            return false;
        }

        $dimiliki = self::untukUser($user);

        foreach ($kapabilitas as $satu) {
            if (in_array($satu, $dimiliki, true)) {
                return true;
            }
        }

        return false;
    }

    /** Gabungan kapabilitas seluruh peran efektif user di tenant request ini. */
    public static function untukUser(User $user): array
    {
        $peran = $user->peranEfektif();

        foreach ($peran as $satu) {
            if (in_array($satu, User::LEVEL_ADMIN, true)) {
                return self::semua();
            }
        }

        $gabungan = [];
        foreach ($peran as $satu) {
            foreach (self::untukPeran($satu) as $kapabilitas) {
                $gabungan[$kapabilitas] = true;
            }
        }

        return array_keys($gabungan);
    }

    /** Apakah user memegang minimal satu kapabilitas di modul ini (untuk menu). */
    public static function userPunyaModul(?User $user, string $modul): bool
    {
        if ($user === null) {
            return false;
        }

        $prefiks = $modul.'.';
        foreach (self::untukUser($user) as $kapabilitas) {
            if (str_starts_with($kapabilitas, $prefiks)) {
                return true;
            }
        }

        return false;
    }
}
