# SukaWarga10

Sistem informasi dan billing komunitas **RW 10, Kelurahan Sukakarya, Kecamatan
Tarogong Kidul, Garut**. Produksi: [sukawarga10.jabnet.id](https://sukawarga10.jabnet.id).

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
| `AGENTS.md` | Aturan dan konvensi untuk siapa pun (termasuk AI agent) yang mengubah kode |
| `DEPLOY.md` | Langkah deploy ke produksi |
| `.ai/PROGRESS.md` | Riwayat perubahan dan alasannya |
| `.ai/TODO.md` | Pekerjaan yang belum selesai dan penghambatnya |
| `.ai/DECISIONS.md` | Keputusan arsitektur beserta alasannya |

## Lisensi

Framework Laravel berlisensi MIT. Kode aplikasi ini milik pengurus RW 10
Sukakarya dan Jabnet.
