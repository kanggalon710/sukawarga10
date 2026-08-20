<?php

if (!function_exists('formatRupiah')) {
    function formatRupiah($value, $withPrefix = true): string
    {
        $formatted = number_format((float) $value, 0, ',', '.');
        return $withPrefix ? 'Rp ' . $formatted : $formatted;
    }
}

if (!function_exists('identitasAplikasi')) {
    /**
     * Identitas merek aplikasi (nama, tagline, lokasi) dari `app_settings`.
     *
     * Satu-satunya sumber kebenaran untuk nama yang tampil ke warga: judul
     * halaman, sidebar, halaman login, meta OG, dan seluruh pesan WhatsApp.
     * Sebelumnya nama ditulis ulang di 8 berkas, sehingga project turunan untuk
     * kampung lain harus mengedit kode, bukan cukup mengganti lewat Pengaturan.
     *
     * Per-tenant sejak Phase F: nilainya dari AppSetting::semuaEfektif()
     * (inheritance platform → desa → RW, memo per request), jadi dua tenant
     * di satu instalasi punya identitas masing-masing.
     */
    function identitasAplikasi(): array
    {
        // Bawaan NETRAL sejak multi-desa: tiap tenant menetapkan identitasnya
        // sendiri lewat Pengaturan ("Kampung Paru" milik RW 10 kini baris
        // app_settings, bukan bawaan kode).
        $bawaan = [
            'nama_aplikasi' => 'Portal Desa',
            'tagline_aplikasi' => "Portal warga digital.\nData keluarga, iuran, dan surat dalam satu tempat.",
            'lokasi_singkat' => 'Garut, Jawa Barat',
            'alamat_portal' => 'desa.jabnet.id',
        ];

        try {
            $tersimpan = \App\Models\AppSetting::semuaEfektif();
        } catch (\Exception $e) {
            $tersimpan = [];
        }

        $hasil = [];
        foreach ($bawaan as $key => $default) {
            $nilai = trim((string) ($tersimpan[$key] ?? ''));
            $hasil[$key] = $nilai !== '' ? $nilai : $default;
        }

        return $hasil;
    }
}

if (!function_exists('namaAplikasi')) {
    /** Nama aplikasi yang tampil ke warga. Override via AppSetting `nama_aplikasi`. */
    function namaAplikasi(): string
    {
        return identitasAplikasi()['nama_aplikasi'];
    }
}

if (!function_exists('taglineAplikasi')) {
    /** Tagline halaman login. Baris baru dipertahankan. Override via AppSetting `tagline_aplikasi`. */
    function taglineAplikasi(): string
    {
        return identitasAplikasi()['tagline_aplikasi'];
    }
}

if (!function_exists('lokasiSingkat')) {
    /** Lokasi ringkas untuk badge & tanda tangan pesan. Override via AppSetting `lokasi_singkat`. */
    function lokasiSingkat(): string
    {
        return identitasAplikasi()['lokasi_singkat'];
    }
}

if (!function_exists('alamatPortal')) {
    /**
     * Alamat portal tanpa skema (mis. `paru.jabnet.id`), untuk ditulis di pesan
     * WhatsApp. Override via AppSetting `alamat_portal`.
     *
     * Sengaja TIDAK diambil dari `APP_URL`: di mesin lokal nilainya
     * `http://localhost:8000`, dan salah setel di produksi berarti warga
     * menerima link yang tidak bisa dibuka.
     */
    function alamatPortal(): string
    {
        return identitasAplikasi()['alamat_portal'];
    }
}

if (!function_exists('tenantSaatIni')) {
    /**
     * Nama organisasi tenant request untuk label UI:
     * ['rw' => 'RW 01', 'desa' => 'Desa Cibunar (Tarogong Kidul)'].
     * Null bila context belum ada (konsol) - pemanggil pakai fallback netral.
     * Rantai leluhur di-cache pada instance organisasi, jadi pemanggilan
     * berulang dalam satu request tidak menambah query.
     */
    function tenantSaatIni(): array
    {
        try {
            $context = app(\App\Services\TenantContext::class);
            if (!$context->sudahDitetapkan()) return ['rw' => null, 'desa' => null];

            return $context->ingat('label.tenant', fn () => [
                'rw' => $context->rw()?->name,
                'desa' => $context->desa()?->name,
            ]);
        } catch (\Exception $e) {
            return ['rw' => null, 'desa' => null];
        }
    }
}

if (!function_exists('namaRw')) {
    /** Nama RW tenant ('RW 01') atau string kosong di luar request tenant. */
    function namaRw(): string
    {
        return tenantSaatIni()['rw'] ?? '';
    }
}

if (!function_exists('namaDesa')) {
    /** Nama desa tenant ('Desa Cibunar (Tarogong Kidul)') atau string kosong. */
    function namaDesa(): string
    {
        return tenantSaatIni()['desa'] ?? '';
    }
}

if (!function_exists('wilayahTenant')) {
    /**
     * Wilayah administratif tenant untuk DATA (bukan merek): nomor RW dua
     * digit, kelurahan/desa, dan kecamatan. Dipakai default impor KK dan
     * persetujuan pendaftaran - dulu ditulis tetap "RW 10 Sukakarya".
     *
     * Sumber: setting `kelurahan`/`kecamatan` (form Pengaturan, per tenant)
     * menang; bila kosong diturunkan dari nama organisasi
     * ("Desa Cibunar (Tarogong Kidul)" → kelurahan "Desa Cibunar",
     * kecamatan "Tarogong Kidul").
     */
    function wilayahTenant(): array
    {
        $kelurahan = '';
        $kecamatan = '';
        try {
            $kelurahan = trim((string) \App\Models\AppSetting::nilai('kelurahan'));
            $kecamatan = trim((string) \App\Models\AppSetting::nilai('kecamatan'));
        } catch (\Exception $e) {}

        if ($kelurahan === '' || $kecamatan === '') {
            preg_match('/^(.*?)(?:\s*\((.*)\))?$/', namaDesa(), $bagian);
            $kelurahan = $kelurahan !== '' ? $kelurahan : trim($bagian[1] ?? '');
            $kecamatan = $kecamatan !== '' ? $kecamatan : trim($bagian[2] ?? '');
        }

        return [
            'rw' => trim(preg_replace('/\D+/', '', namaRw())),
            'kelurahan' => $kelurahan,
            'kecamatan' => $kecamatan,
        ];
    }
}

if (!function_exists('getMenuPermissions')) {
    /**
     * Matriks menu per peran, DITURUNKAN dari matriks kapabilitas: sebuah
     * peran "punya" menu bila memegang minimal satu kapabilitas di modul itu.
     *
     * Dipakai tampilan read-only tab Hak Akses. Setting lama `role_permissions`
     * (snapshot penuh yang bisa diedit admin tenant) TIDAK dibaca lagi -
     * pengaturan hak akses kini hanya lewat admin platform.
     */
    function getMenuPermissions(): array
    {
        $matriks = \App\Services\MatriksKapabilitas::class;
        $peran = array_merge(['superadmin'], array_keys($matriks::BAWAAN));
        $menu = array_map(fn ($m) => $m['key'], getAllMenuItems());

        $hasil = [];
        foreach ($peran as $satu) {
            $dimiliki = $matriks::untukPeran($satu);
            $hasil[$satu] = array_values(array_filter(
                $menu,
                fn ($key) => (bool) array_filter(
                    $dimiliki,
                    fn ($kapabilitas) => str_starts_with($kapabilitas, $key.'.')
                )
            ));
        }

        return $hasil;
    }
}

if (!function_exists('fiturAktif')) {
    /**
     * Feature flag per organisasi (Phase F, §18): setting `fitur_<modul>`
     * bernilai '0' mematikan modul untuk tenant itu; tanpa baris = aktif,
     * jadi nol baris berarti perilaku lama utuh. Ikut inheritance: platform
     * bisa mematikan modul untuk semua tenant sekaligus.
     */
    function fiturAktif(string $modul): bool
    {
        try {
            return \App\Models\AppSetting::nilai("fitur_{$modul}") !== '0';
        } catch (\Exception $e) {
            return true;
        }
    }
}

if (!function_exists('bolehkah')) {
    /**
     * Apakah user yang login memegang SALAH SATU kapabilitas ini (OR)?
     *
     * Ini penjaga izin yang sesungguhnya, dipakai middleware `izin:`,
     * controller, dan blade. Berbeda dengan userCan() yang hanya soal
     * tampil/sembunyi menu, dan berbeda dengan hierarki linier lama: peran
     * rangkap MENGGABUNGKAN kapabilitas, bukan memilih yang tertinggi.
     */
    function bolehkah(string ...$kapabilitas): bool
    {
        return \App\Services\MatriksKapabilitas::userPunya(auth()->user(), ...$kapabilitas);
    }
}

if (!function_exists('userCan')) {
    /**
     * Apakah menu modul ini ditampilkan untuk user yang login?
     *
     * Dibangun DI ATAS matriks kapabilitas yang sama dengan penjaga rute:
     * menu tampil bila user memegang minimal satu kapabilitas di modul itu.
     * Sebelumnya ini sistem terpisah (`role_permissions`) yang bisa berbeda
     * dari penjaga rute, sehingga menu bisa tampil untuk halaman yang pasti
     * 403 - atau sebaliknya menyembunyikan halaman yang sebenarnya boleh.
     */
    function userCan(string $menuKey): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        // Modul yang dimatikan untuk tenant ini hilang untuk SEMUA peran,
        // termasuk superadmin: ini ketersediaan modul, bukan izin. Rutenya
        // ikut tertutup oleh middleware `fitur:` (PastikanFiturAktif).
        if (!fiturAktif($menuKey)) return false;

        return \App\Services\MatriksKapabilitas::userPunyaModul($user, $menuKey);
    }
}

if (!function_exists('getAllMenuItems')) {
    function getAllMenuItems(): array
    {
        return [
            ['key' => 'dashboard',   'label' => 'Dashboard',       'icon' => 'fa-tachometer-alt', 'section' => 'UTAMA'],
            ['key' => 'warga',       'label' => 'Data Warga',      'icon' => 'fa-users',          'section' => 'UTAMA'],
            ['key' => 'sampah',      'label' => 'Iuran Sampah',    'icon' => 'fa-trash',          'section' => 'BILLING'],
            ['key' => 'padaringan',  'label' => 'Padaringan',      'icon' => 'fa-utensils',       'section' => 'BILLING'],
            ['key' => 'surat',       'label' => 'Surat Menyurat',  'icon' => 'fa-envelope-open-text', 'section' => 'LAYANAN'],
            ['key' => 'pendaftaran', 'label' => 'Pendaftaran',     'icon' => 'fa-user-clock',     'section' => 'LAYANAN'],
            ['key' => 'umkm',        'label' => 'UMKM Warga',      'icon' => 'fa-store',          'section' => 'LAYANAN'],
            ['key' => 'bukukas',     'label' => 'Buku Kas',        'icon' => 'fa-book',           'section' => 'KEUANGAN'],
            ['key' => 'pengeluaran', 'label' => 'Pengeluaran',     'icon' => 'fa-file-invoice-dollar', 'section' => 'KEUANGAN'],
            ['key' => 'sumbangan',   'label' => 'Sumbangan',       'icon' => 'fa-gift',           'section' => 'KEUANGAN'],
            ['key' => 'setor',       'label' => 'Setor Sampah RT', 'icon' => 'fa-recycle',        'section' => 'ADMINISTRASI'],
            ['key' => 'aduan',       'label' => 'Aduan Warga',     'icon' => 'fa-headset',        'section' => 'ADMINISTRASI'],
            ['key' => 'mpwa',        'label' => 'Broadcast WA',    'icon' => 'fab fa-whatsapp',   'section' => 'ADMINISTRASI'],
            ['key' => 'kegiatan',    'label' => 'Kegiatan RW',     'icon' => 'fa-calendar-alt',   'section' => 'ADMINISTRASI'],
            ['key' => 'laporan',     'label' => 'Laporan',         'icon' => 'fa-chart-bar',      'section' => 'ADMIN'],
            ['key' => 'akun',        'label' => 'Manajemen Akun',  'icon' => 'fa-user-shield',    'section' => 'ADMIN'],
            ['key' => 'log',         'label' => 'Log Sistem',      'icon' => 'fa-history',        'section' => 'ADMIN'],
            ['key' => 'pengaturan',  'label' => 'Pengaturan',      'icon' => 'fa-cog',            'section' => 'ADMIN'],
        ];
    }
}

if (!function_exists('statusKerjaDariPekerjaan')) {
    /**
     * Klasifikasi teks bebas `pekerjaan` → status ketenagakerjaan terstruktur.
     * Dipakai backfill data lama & import CSV. Urutan cek penting (negasi dulu).
     */
    function statusKerjaDariPekerjaan($teks): string
    {
        $t = strtolower(trim((string) $teks));
        if ($t === '' || $t === '-') return 'Tidak Bekerja';
        foreach (['belum', 'tidak bekerja', 'tdk bekerja', 'menganggur', 'nganggur', 'tidak kerja'] as $k) {
            if (str_contains($t, $k)) return 'Tidak Bekerja';
        }
        foreach (['mencari kerja', 'cari kerja'] as $k) {
            if (str_contains($t, $k)) return 'Mencari Kerja';
        }
        foreach (['pelajar', 'siswa', 'sekolah', 'mahasiswa', 'kuliah', 'paud', 'tk'] as $k) {
            if (str_contains($t, $k)) return 'Sekolah';
        }
        foreach (['ibu rumah tangga', 'irt', 'mengurus rumah'] as $k) {
            if (str_contains($t, $k)) return 'Mengurus Rumah Tangga';
        }
        if (str_contains($t, 'pensiun')) return 'Pensiunan';
        foreach (['balita', 'bayi'] as $k) {
            if (str_contains($t, $k)) return 'Tidak Bekerja';
        }
        return 'Bekerja';
    }
}

if (!function_exists('incomeMidpoint')) {
    /**
     * Titik tengah (Rp) rentang penghasilan — untuk ESTIMASI agregat pendapatan
     * rumah tangga & per kapita. Mendukung bucket kanonik + bucket form lama.
     */
    function incomeMidpoint($range): int
    {
        $r = strtolower(str_replace([' ', ','], ['', '.'], (string) $range));
        if ($r === '' || $r === '-') return 0;
        if (str_contains($r, '<500'))                            return 250000;
        if (str_contains($r, '500rb-1jt'))                       return 750000;
        if (str_contains($r, '<1'))                              return 500000;
        if (str_contains($r, '1-2.5') || str_contains($r, '1jt-2.5jt'))   return 1750000;
        if (str_contains($r, '2.5-5') || str_contains($r, '2.5jt-5jt'))   return 3750000;
        if (str_contains($r, '>5'))                              return 6000000;
        return 0;
    }
}

if (!function_exists('garisKemiskinan')) {
    /** Garis kemiskinan per kapita/bulan (Rp). Override via AppSetting key `garis_kemiskinan`. */
    function garisKemiskinan(): int
    {
        try {
            $v = \App\Models\AppSetting::nilai('garis_kemiskinan');
            if ($v !== null && is_numeric($v) && (int) $v > 0) return (int) $v;
        } catch (\Exception $e) {}
        return 500000; // default ≈ garis kemiskinan kab. Garut (dibulatkan)
    }
}

if (!function_exists('kkKepalaBekerja')) {
    /** Apakah kepala keluarga terhitung bekerja (punya sumber pendapatan aktif). */
    function kkKepalaBekerja($kk): bool
    {
        $sp = strtolower(trim((string) ($kk->sumberPendapatan ?? '')));
        if (str_contains($sp, 'tidak bekerja')) return false;
        if ($sp !== '') return true;
        $pk = strtolower(trim((string) ($kk->pekerjaan ?? '')));
        return $pk !== '' && $pk !== '-' && !str_contains($pk, 'tidak bekerja') && !str_contains($pk, 'belum');
    }
}

if (!function_exists('normalisasiIdentitas')) {
    /**
     * Bersihkan No. KK / NIK jadi 16 digit, atau null bila tidak bisa dipercaya.
     *
     * Nomor 16 digit yang pernah singgah di Google Sheets sebagai ANGKA rusak
     * permanen: ia kembali sebagai notasi ilmiah ("3.205063190736E+016") atau
     * terpotong sepanjang lebar kolom. Digit yang hilang tidak bisa dipulihkan
     * dengan menebak, jadi nilai seperti itu WAJIB ditolak di batas masuk, bukan
     * disimpan apa adanya seperti selama ini.
     *
     * Mengembalikan null untuk: kosong, memuat huruf/notasi ilmiah, atau
     * panjangnya bukan 16. Pemanggil yang membedakan "belum diisi" dari "diisi
     * tapi rusak" memakai identitasRusak() di bawah.
     */
    function normalisasiIdentitas($raw): ?string
    {
        $t = trim((string) $raw);
        if ($t === '' || $t === '-') return null;

        // Notasi ilmiah & elipsis: digitnya sudah hilang, jangan dipungut sisanya.
        if (preg_match('/[eE]\s*\+?\s*\d/', $t) || str_contains($t, '.')) return null;

        // Huruf atau simbol lain = salah ketik, bukan pemisah. Spasi & tanda
        // hubung memang lazim ditulis manusia, jadi itu saja yang dibuang.
        if (preg_match('/[^0-9\s-]/', $t)) return null;

        $d = preg_replace('/[\s-]/', '', $t);

        return strlen($d) === 16 ? $d : null;
    }
}

if (!function_exists('identitasRusak')) {
    /**
     * true bila kolom identitas diisi TAPI tidak bisa dipakai.
     * Kosong bukan rusak: pendataan memang sering belum lengkap.
     */
    function identitasRusak($raw): bool
    {
        $t = trim((string) $raw);

        return $t !== '' && $t !== '-' && normalisasiIdentitas($t) === null;
    }
}

if (!function_exists('normalizeWa')) {
    /**
     * Normalisasi nomor WhatsApp Indonesia ke format internasional tanpa "+": 62xxxxxxxxxx.
     * Menerima: 08xx, 8xx, 62xx, +62xx, 0062xx, dengan spasi/strip/titik.
     * Return null bila kosong. Pengecekan panjang dilakukan saat kirim/validasi.
     */
    function normalizeWa($raw): ?string
    {
        $d = preg_replace('/\D/', '', (string) $raw);   // ambil digit saja
        if ($d === '') return null;
        if (str_starts_with($d, '00')) $d = substr($d, 2);          // 0062.. → 62..
        if (str_starts_with($d, '0')) $d = '62' . substr($d, 1);    // 08xx  → 628xx
        elseif (str_starts_with($d, '8')) $d = '62' . $d;           // 8xx   → 628xx
        elseif (!str_starts_with($d, '62')) $d = '62' . $d;         // fallback: anggap lokal
        if (str_starts_with($d, '620')) $d = '62' . ltrim(substr($d, 2), '0'); // 62+0xx → 62xx
        return $d ?: null;
    }
}
