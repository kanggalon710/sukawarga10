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
     * Diingat per request lewat container (bukan static biasa) karena layout
     * memanggilnya beberapa kali per halaman; static akan bocor antar request
     * di dalam satu proses tes, sedangkan container dibuat ulang tiap request.
     */
    function identitasAplikasi(): array
    {
        if (app()->bound('identitas.aplikasi')) {
            return app('identitas.aplikasi');
        }

        $bawaan = [
            'nama_aplikasi' => 'Kampung Paru',
            'tagline_aplikasi' => "Portal warga Kampung Paru.\nData keluarga, iuran, dan surat dalam satu tempat.",
            'lokasi_singkat' => 'Garut, Jawa Barat',
            // Rencana pindah ke desa.jabnet.id. Bawaannya tetap paru.jabnet.id
            // karena itu yang sudah hidup; desa.jabnet.id belum resolve, dan
            // alamat mati di pesan WhatsApp lebih buruk daripada alamat lama.
            // Saat DNS siap, ganti lewat Pengaturan tanpa menyentuh kode.
            'alamat_portal' => 'paru.jabnet.id',
        ];

        try {
            $tersimpan = \App\Models\AppSetting::whereIn('key', array_keys($bawaan))
                ->pluck('value', 'key')->all();
        } catch (\Exception $e) {
            $tersimpan = [];
        }

        $hasil = [];
        foreach ($bawaan as $key => $default) {
            $nilai = trim((string) ($tersimpan[$key] ?? ''));
            $hasil[$key] = $nilai !== '' ? $nilai : $default;
        }

        app()->instance('identitas.aplikasi', $hasil);
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

if (!function_exists('getDefaultPermissions')) {
    function getDefaultPermissions(): array
    {
        return [
            'superadmin' => ['dashboard','warga','sampah','padaringan','surat','pendaftaran','umkm','bukukas','pengeluaran','sumbangan','setor','aduan','mpwa','kegiatan','laporan','akun','log','pengaturan'],
            'ketua_rw'   => ['dashboard','warga','sampah','padaringan','surat','pendaftaran','umkm','bukukas','pengeluaran','sumbangan','setor','aduan','mpwa','kegiatan','laporan','log','pengaturan'],
            'bendahara'  => ['dashboard','warga','sampah','padaringan','bukukas','pengeluaran','sumbangan','setor','aduan','laporan'],
            'petugas_rt' => ['dashboard','warga','sampah','padaringan','setor','aduan','kegiatan','umkm'],
            'warga'      => ['dashboard','aduan'],
        ];
    }
}

if (!function_exists('getMenuPermissions')) {
    function getMenuPermissions(): array
    {
        try {
            $stored = \App\Models\AppSetting::where('key', 'role_permissions')->value('value');
            if ($stored) {
                $decoded = json_decode($stored, true);
                if (is_array($decoded)) return $decoded;
            }
        } catch (\Exception $e) {}
        return getDefaultPermissions();
    }
}

if (!function_exists('userCan')) {
    function userCan(string $menuKey): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $level = $user->level ?? 'warga';

        // Admin-like levels always get full access.
        // Daftarnya diambil dari User::LEVEL_ADMIN, bukan ditulis ulang di sini,
        // supaya hanya ada satu sumber kebenaran untuk "setara superadmin".
        if (in_array(strtolower($level), \App\Models\User::LEVEL_ADMIN, true)) return true;

        $perms = getMenuPermissions();

        // Level yang tidak dikenal DITOLAK. Sebelumnya di sini `return true`
        // (fail-open), sehingga level salah ketik atau level baru yang belum
        // didaftarkan otomatis mendapat akses penuh ke seluruh menu.
        if (!isset($perms[$level])) return false;

        return in_array($menuKey, $perms[$level], true);
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
            ['key' => 'mpwa',        'label' => 'MPWA Broadcast',  'icon' => 'fab fa-whatsapp',   'section' => 'ADMINISTRASI'],
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
            $v = \App\Models\AppSetting::where('key', 'garis_kemiskinan')->value('value');
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
