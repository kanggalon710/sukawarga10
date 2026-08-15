# HANDOFF - Orientasi untuk agent berikutnya

Halaman ini untuk dibaca **pertama**, sebelum menyentuh kode. Isinya keadaan
project per **2026-08-15**, bukan riwayat lengkap.

Urutan baca: file ini → `AGENTS.md` (aturan) → `.ai/TODO.md` (pekerjaan) →
`.ai/DECISIONS.md` (kenapa sesuatu dibentuk begitu). `.ai/PROGRESS.md` adalah
riwayat kronologis; baca kalau butuh konteks sejarah, bukan untuk orientasi.

---

## 1. Keadaan sekarang

| | |
|---|---|
| Commit | terakhir dicatat `36c0e57`; kalau `git log -1` sudah lain, halaman ini tertinggal |
| Branch | `main` dan `dev` sama-sama di commit itu, sinkron dengan `origin` |
| Remote | `git@github.com:kanggalon710/sukawarga10.git` (SSH, bukan HTTPS) |
| Tes | 73 lulus, 137 assertion (`composer test`) |
| Working tree | bersih |

Produksi berjalan di `https://paru.jabnet.id` dengan MySQL (rencana pindah ke
`desa.jabnet.id`, langkahnya di `DEPLOY.md` bagian "Pindah domain"). Ini sistem
yang dipakai sungguhan: ada uang iuran warga dan data pribadi (NIK, No. KK,
alamat, nomor HP) di dalamnya. Perlakukan setiap perubahan sesuai bobot itu.

## 2. Yang WAJIB kamu tahu sebelum apa pun

**Repositori ini PUBLIK.** Jangan pernah menaruh kredensial, kunci API, atau
data warga di dalam kode atau commit. Yang sudah terlanjur: PIN superadmin
`463696` ada di `database/seeders/DatabaseSeeder.php` dan sudah masuk riwayat
git publik. Itu keputusan sadar pemilik project (lihat `.ai/TODO.md`), tapi
perlakukan PIN itu sebagai **sudah bocor**. Jangan menambah yang serupa.

**`$fillable` harus cocok dengan kolom tabel.** Laravel membuang atribut
non-fillable tanpa bersuara. `Transaksi` dan `User` pernah menyebut kolom yang
tidak ada sekaligus melewatkan kolom yang benar-benar ditulis controller -
akibatnya **seluruh pencatatan uang gagal**, dan tidak ada yang tahu karena
errornya muncul sebagai kegagalan constraint, bukan sebagai bug logika.
`tests/Feature/PencatatanIuranTest.php` mengunci ini. Jangan pernah men-skip
tes itu untuk "sementara".

**`update()` juga tunduk pada `$fillable`.** Kolom void pada `Transaksi` sengaja
tidak fillable, jadi aksi void memakai `forceFill()->save()`. Kalau kamu
menggantinya jadi `update()`, void akan diam-diam tidak tersimpan lagi.

**Tidak ada build front-end.** Tidak ada Node, npm, Vite, atau Tailwind.
Styling dari `public/css/styles.css` dan `public/css/bi-report.css`. Class
Tailwind yang kamu tulis di Blade tidak akan ter-compile oleh apa pun.

**Otorisasi ditegakkan di server.** `userCan()` hanya untuk tampil/sembunyi
menu - itu UI, bukan pengamanan. Setiap rute yang mengubah data wajib punya
middleware `role:` atau cek kepemilikan eksplisit. Ada tes yang menjaga ini
(`tests/Feature/OtorisasiTest.php`).

**Kepemilikan data warga lewat `users.keluarga_id`,** bukan kecocokan nama.
Jangan pernah mencari KK milik seseorang lewat kolom `nama`.

## 3. Cara mulai bekerja

```bash
composer install
composer setup        # .env + sqlite + migrate + seed (aman diulang)
composer test         # harus 73 lulus sebelum kamu mengubah apa pun
php artisan serve
```

`.env`, `database/database.sqlite`, dan `vendor/` sudah ada di mesin pemilik
project dan semuanya gitignored. Kalau `composer test` tidak hijau sejak awal,
**selesaikan itu dulu** sebelum menambah pekerjaan baru.

## 4. Apa yang sudah diverifikasi, dan apa yang belum

Jangan mengulang verifikasi yang sudah ada; jangan pula menganggap yang belum
sebagai sudah.

**Sudah:** 73 tes (jalur uang, void, otorisasi tiap peran, kepemilikan, helper
domain, identitas aplikasi, render 20 halaman); Pint bersih untuk seluruh berkas yang dibuat pada
2026-08-15; migrasi `up` dan `down` diuji; migrasi bersih dari database kosong;
smoke test HTTP sungguhan termasuk login end-to-end.

**Belum:**
- **MySQL.** Semua verifikasi memakai SQLite. Titik rawan: kolom
  `transaksis.periode` bertipe `json` dan `whereJsonContains` dipakai di tes.
- **Tampilan di 360/768/1280px** untuk sebagian besar halaman. Yang sudah:
  halaman login (dipotret lewat headless Chrome, bersih di ketiga lebar).
- **Uji peran dengan akun `warga` sungguhan di produksi.**

## 5. Pekerjaan berikutnya

**Arah besar:** project ini direncanakan berkembang jadi platform multi-desa.
Visinya di `AI_AGENT_MULTI_TENANT_ARCHITECTURE.md` (planning only, dokumen itu
sendiri melarang refactor sebelum audit), dan auditnya SUDAH selesai:
`.ai/AUDIT-MULTITENANT.md`. Kalau kamu diminta mengerjakan multi-tenant, mulai
dari audit itu, jangan dari nol; implementasi belum dimulai dan menunggu
keputusan pemilik project.

Daftar lengkap dan alasannya ada di `.ai/TODO.md`. Ringkasnya, yang paling
berdampak lebih dulu:

1. **Checklist deploy** di bagian atas `.ai/TODO.md` - ada tiga migrasi baru
   (`users.keluarga_id` + backfill, `transaksis.periode`, index 9 tabel).
   Backup database produksi dulu.
2. **Notifikasi WA masih menahan request.** Perlu keputusan soal queue worker
   dulu, bukan langsung dipindah ke job - alasannya di `.ai/DECISIONS.md`.
3. **Pagination** baru terpasang di Log Sistem dan Data Warga. Partial-nya sudah
   ada di `resources/views/partials/pagination.blade.php`, tinggal dipasang di
   Surat, Aduan, UMKM, Kegiatan, Buku Kas, dan riwayat billing.
4. **`ExportImportController`** (488 baris, terbesar di repo) belum ditinjau.
   Menyentuh berkas unggahan dan data pribadi.
5. **Level `sekretaris` adalah hantu** - dirujuk di `SuratController::index`
   tapi tidak ada di hirarki peran mana pun. Perlu diputuskan.

## 6. Hal yang gampang salah paham

- **Kolom domain memakai camelCase** (`namaLengkap`, `noHP`, `ikutSampah`,
  `tanggalLahirKK`), beda dari konvensi snake_case Laravel. Ikuti yang ada,
  jangan "dirapikan".
- **Nama "Kampung Paru" bukan literal di kode.** Nama, tagline, lokasi, dan
  alamat portal datang dari `app_settings` lewat `namaAplikasi()`,
  `taglineAplikasi()`, `lokasiSingkat()`, dan `alamatPortal()` di
  `app/helpers.php`, dengan nilai bawaan di helper itu sendiri. Jangan menulis
  ulang namanya di Blade atau di service; ada tes yang menggagalkannya. Ini yang
  membuat project ini bisa dipakai desa lain tanpa mengedit kode.
- **`app_settings` bukan sekadar preferensi.** Tabel itu ikut menentukan
  otorisasi (`role_permissions`) dan ambang kemiskinan. Form Pengaturan memakai
  whitelist key; jangan diubah jadi menerima `$request->all()`.
- **Zona waktu aplikasi `Asia/Jakarta`,** bukan UTC. Ini disengaja: dengan UTC,
  pembayaran sebelum pukul 07.00 WIB tanggal 1 terhitung bulan sebelumnya.
- **Pint melaporkan 43 berkas lama menyimpang gaya.** Itu diketahui dan sengaja
  dibiarkan supaya diff perbaikan tetap terbaca. Kalau mau dibereskan, jadikan
  commit tersendiri yang isinya **hanya** format, jangan dicampur perubahan
  logika.
- **Teks UI dan komentar memakai bahasa Indonesia,** dan tanpa em dash atau en
  dash (ada commit khusus yang membersihkannya - jangan dimasukkan lagi).

## 7. Protokol sebelum kamu melapor selesai

Perbarui `.ai/PROGRESS.md` (entri baru di atas), centang/tambah di
`.ai/TODO.md`, dan catat di `.ai/DECISIONS.md` kalau kamu mengambil keputusan
arsitektur atau sengaja melanggar aturan. Perbarui juga halaman ini kalau
keadaan yang dijelaskan di atas sudah berubah - kalau tidak, ia berubah jadi
menyesatkan, dan itu lebih buruk daripada tidak ada.
