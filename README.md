# Kampung Paru

Sistem informasi dan billing komunitas warga. Produksi berjalan di
[paru.jabnet.id](https://paru.jabnet.id) untuk RW 10, Kelurahan Sukakarya,
Kecamatan Tarogong Kidul, Garut.

Menangani data warga (KK dan anggota keluarga), iuran sampah mingguan, iuran
padaringan bulanan, buku kas RW, surat menyurat berjenjang, aduan warga, UMKM,
kegiatan, serta laporan demografi dan kesejahteraan. Notifikasi ke warga dikirim
lewat WhatsApp.

> Ini sistem yang dipakai sungguhan: berisi uang iuran warga dan data pribadi
> (NIK, No. KK, alamat, nomor HP). Baca `AGENTS.md` sebelum mengubah kode.

## Stack

Laravel 12 (PHP 8.2+) dengan Blade server-rendered. Tanpa SPA, tanpa build
front-end: CSS dimuat langsung dari `public/css/`. Database produksi MySQL,
lokal SQLite. Autentikasi memakai **username + PIN**.

## Menjalankan secara lokal

```bash
composer setup        # install, buat .env, generate key, migrate, seed
php artisan serve     # http://localhost:8000
```

`composer setup` membuat dua akun bawaan: `admin` dan `jabnet`. PIN keduanya ada
di `database/seeders/DatabaseSeeder.php`. **Ganti PIN setelah login pertama.**
Akun hanya dibuat bila belum ada, jadi menjalankan ulang seeder tidak akan
menimpa PIN yang sudah diganti.

## Perintah

| Perintah | Kegunaan |
|---|---|
| `composer dev` | Jalankan server pengembangan |
| `composer test` | Jalankan seluruh tes |
| `php artisan migrate` | Terapkan migrasi (produksi: `--force`) |
| `php artisan import:keluarga <csv>` | Impor data KK |
| `php artisan import:anggota <csv>` | Impor data anggota keluarga |

## Identitas aplikasi bisa diganti tanpa menyentuh kode

Nama, tagline, lokasi, dan alamat portal disimpan di tabel `app_settings` dan
diubah lewat **Pengaturan > Info RW > Identitas Aplikasi**:

| Key | Bawaan | Tampil di |
|---|---|---|
| `nama_aplikasi` | Kampung Paru | judul halaman, sidebar, login, meta OG, pesan WhatsApp |
| `tagline_aplikasi` | Portal warga Kampung Paru. (2 baris) | hero halaman login |
| `lokasi_singkat` | Garut, Jawa Barat | badge login, footer dan kop pesan WhatsApp |
| `alamat_portal` | paru.jabnet.id | link yang dikirim ke warga lewat WhatsApp |

Nilai bawaannya ada di `app/helpers.php`, jadi instalasi baru langsung jalan
tanpa satu baris pun di `app_settings`. Untuk memakai project ini bagi kampung
lain, tidak perlu mengedit kode: jalankan `composer setup`, lalu ganti keempat
nilai di atas dan data RW di menu Pengaturan.

Identitas RW untuk kop surat (`nama_rw`, `kelurahan`, `kecamatan`, `kabupaten`,
`ketua_rw`) juga sudah berupa pengaturan, bukan literal di kode.

Satu-satunya identitas yang berbentuk gambar adalah `public/logo-sukawarga.svg`.
Teks di dalamnya sengaja generik ("Portal Desa") supaya tetap benar untuk desa
mana pun; nama instansinya dibawa oleh `nama_aplikasi` di halaman login.

## Domain

Produksi saat ini di `paru.jabnet.id`. Rencananya pindah ke `desa.jabnet.id`;
per 2026-08-15 domain itu belum resolve. Langkah pindahnya ada di `DEPLOY.md`
bagian "Pindah domain", dan tidak butuh perubahan kode.

## Peran pengguna

`superadmin` > `ketua_rw` > `bendahara` > `petugas_rt` > `warga`

Warga hanya dapat melihat dan melengkapi data keluarganya sendiri, mengajukan
surat, dan melaporkan aduan. Seluruh aksi yang mengubah data milik orang lain
dijaga middleware `role:` di sisi server, bukan sekadar disembunyikan dari menu.

## Data pribadi

File CSV data warga (`database/seed-data/`) **tidak pernah di-commit** dan sudah
masuk `.gitignore`. Kirim ke server lewat jalur terpisah. Lihat `DEPLOY.md`.

## Dokumentasi lain

| File | Isi |
|---|---|
| `.ai/HANDOFF.md` | **Mulai dari sini.** Keadaan terkini project, apa yang sudah/belum diverifikasi, dan jebakan yang perlu diketahui |
| `AGENTS.md` | Aturan dan konvensi untuk siapa pun (termasuk AI agent) yang mengubah kode |
| `DEPLOY.md` | Langkah deploy ke produksi |
| `AI_AGENT_MULTI_TENANT_ARCHITECTURE.md` | Visi arsitektur multi-desa (status: planning) |
| `.ai/AUDIT-MULTITENANT.md` | Gap analysis single-RW menuju multi-tenant, hasil audit Phase A |
| `.ai/PROGRESS.md` | Riwayat perubahan dan alasannya |
| `.ai/TODO.md` | Pekerjaan yang belum selesai dan penghambatnya |
| `.ai/DECISIONS.md` | Keputusan arsitektur beserta alasannya |

## Lisensi

Framework Laravel berlisensi MIT. Kode aplikasi ini milik pengurus RW 10
Sukakarya dan Jabnet.
