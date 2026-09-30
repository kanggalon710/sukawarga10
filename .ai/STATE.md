# STATE - Kampung Paru / Sukawarga
Updated: 2026-09-30 by codex-gpt-5 (Codex)

## What this is
Portal informasi, administrasi, dan penagihan iuran warga berbasis Laravel 12,
Blade, dan CSS biasa. Produksi aktif terverifikasi di `https://desa.jabnet.id`;
data produksi memuat uang iuran dan data pribadi warga. Repositori GitHub
`kanggalon710/sukawarga10` bersifat publik.

## Run and verify
```bash
composer install
composer setup
composer test
php artisan serve
```
Tanpa `.env`, `composer test` saat ini gagal karena `APP_KEY` tidak ada. Dengan
APP_KEY sementara, 406 tes/1585 assertion tidak gagal, tetapi menghasilkan 348
peringatan karena `.env` tidak ada. `vendor/bin/pint --test` menemukan 34 berkas
yang belum sesuai format.

## Works
- Remote `origin` terhubung ke GitHub; `main` sinkron dengan `origin/main` pada
  `f1f3084`. `dev` sama dengan `main`; `production` tertinggal satu commit.
- Produksi `desa.jabnet.id` menjawab HTTPS, login tampil, file sensitif umum 404,
  cookie aman, HSTS/X-Frame-Options/nosniff aktif.
- Migrasi dan seeder berhasil pada SQLite kosong.
- 174 berkas PHP lolos pemeriksaan sintaks.
- Cache konfigurasi, rute, dan Blade dapat dibangun.
- Sampel UI login, dashboard, warga, MPWA, laporan, pengaturan, dan surat tidak
  overflow dan konsol bersih pada lebar 360/768/1280 px.
- Matriks kapabilitas, feature flag, global tenant scope, AppSetting bertingkat,
  dan audit log memiliki cakupan tes yang kuat.

## In progress
Audit 2026-09-30 selesai tanpa mengubah kode aplikasi. Rencana eksekusi dan prompt
agen ada di `.ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md`. Prioritas berikutnya:
putuskan tenant vs aplikasi terpisah, lalu kerjakan fase keamanan P0 sebelum
membuat aplikasi bermerek baru dari snapshot rilis yang sudah terverifikasi.

## Blocked, needs a human
- Putuskan dan lakukan pembersihan riwayat git yang pernah memuat data warga;
  force-push berdampak pada semua clone.
- Rotasi PIN bawaan/produksi dan kunci MPWA karena repo publik serta paparan
  pengaturan lintas tenant.
- Tentukan apakah halaman publik desa harus dapat diindeks. Saat ini robots
  memblokir seluruh situs dan tidak ada sitemap.

## Traps
- Upload KK/profil tidak memvalidasi MIME/ukuran sebelum masuk disk publik:
  `KeluargaController.php:37-41,118-122` dan `ProfilWargaController.php:51-94`.
- Ketua RW dapat menyimpan URL MPWA generik sementara kunci efektif diwariskan
  dan ditampilkan polos: `PengaturanController.php:26-32,56-59` dan
  `admin/pengaturan.blade.php:96-99`.
- Pemulihan PIN mengubah PIN sebelum pengiriman WA dan tidak dibatasi lajunya:
  `WebAuthController.php:224-274` dan `routes/web.php:23-26`.
- Query bulanan laporan berada dalam loop: `LaporanController.php:47-49`.
- Notifikasi/broadcast WA berjalan sinkron dan dapat menahan request lama:
  `MpwaController.php:187-227`.

## Recently touched
- `.ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md`, `.ai/STATE.md`,
  `.ai/HANDOFF.md`, `.ai/PROGRESS.md`, dan `.ai/TODO.md` oleh codex-gpt-5
  (dokumentasi audit/rencana saja, tidak ada perubahan kode aplikasi).
- Berkas tak terlacak `stai-garut-r-7095b88a.webp` adalah milik pengguna dan
  sengaja tidak disentuh.
