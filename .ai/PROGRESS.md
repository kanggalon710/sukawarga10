# PROGRESS

Catatan pekerjaan, terbaru di atas. Jelaskan KENAPA, bukan APA (git sudah mencatat apa).

## 2026-08-15 - Merge main ke dev, lalu push kedua branch
**Agen:** claude | **Status:** selesai
**Kenapa:** `origin/main` ternyata sudah maju satu commit (`c56c41b`: rombakan
Dashboard jadi BI, perbaikan Manajemen Akun, pembersihan em-dash) yang belum ada
di lokal. Harus digabung sebelum pekerjaan penyelarasan standar bisa didorong.
**Perubahan:**
- Merge `main` ke `dev`, lalu `main` di-fast-forward ke hasilnya. Keduanya kini
  di `f53e138` dan sinkron dengan remote.
- Satu konflik di `resources/views/layanan/aduan.blade.php`, diselesaikan dengan
  mengambil KEDUA sisi: pembacaan `$user->namaLengkap` (kolom `nama` tidak ada
  di tabel `users`, jadi nilainya selalu null) digabung dengan pemisah titik
  tengah dari pembersihan em-dash.
- `userCan()` di `app/helpers.php` diarahkan ke `User::LEVEL_ADMIN`. Commit dari
  main memperkenalkan konstanta itu sebagai sumber kebenaran tunggal untuk level
  setara superadmin, sementara helper masih menulis ulang daftarnya.
- URL remote `origin` diubah dari HTTPS ke SSH, plus `core.sshCommand` untuk
  repo ini. Push HTTPS gagal karena tidak ada kredensial di mesin ini; kunci
  `~/.ssh/yoga-github-ssh` tersedia dan valid.
**File:** app/helpers.php, resources/views/layanan/aduan.blade.php (+ hasil merge)
**Catatan:** Beruntung `c56c41b` hanya menyentuh *view* Dashboard, bukan
`DashboardController`, sehingga refactor query tidak bertabrakan. `composer test`
69 lulus pada pohon hasil merge, termasuk render Dashboard BI baru dengan
controller yang sudah dioptimasi.

## 2026-08-15 - Penyelarasan project dengan standar pengembangan
**Agen:** claude | **Status:** selesai
**Kenapa:** Diminta membawa seluruh project memenuhi standar, bukan hanya menambal
tiga temuan terparah. Lingkungan lokal dipasang lebih dulu (`composer install`,
`.env`, SQLite) supaya setiap perbaikan bisa diverifikasi dengan dijalankan,
bukan diasumsikan.

**Keamanan & otorisasi**
- `userCan()` sekarang fail-closed. Level yang tidak dikenal ditolak, sebelumnya
  justru diberi akses penuh.
- Form Pengaturan memakai whitelist key + validasi tipe. Sebelumnya menulis field
  request apa pun ke `app_settings`, termasuk `role_permissions` yang mengatur
  otorisasi, dan rutenya terbuka untuk ketua_rw.
- Sisa aplikasi PWA lama dihapus dari `public/` (`index.html`, `login.html`,
  `js/*.js`, `ext-test.php`). `js/auth.js` memuat kredensial default dalam teks
  polos dan bisa diunduh siapa saja dari domain produksi.
- `robots.txt` menolak seluruh crawler; tidak ada konten publik yang perlu
  diindeks, sedangkan isinya data pribadi warga.
- Kepemilikan data warga diikat lewat kolom baru `users.keluarga_id`, bukan
  kecocokan nama. Migrasi menautkan yang tidak ambigu saja.

**Kebenaran data**
- `User::$fillable` dibersihkan dari kolom yang tidak ada (`nama`, `noHP`), dan
  pembacaan `$user->nama` yang selalu null diganti `namaLengkap`.
- `Umkm`/`Kegiatan` tidak lagi memakai `$request->all()` pada model `$guarded = []`.
- `resetData()` tidak lagi melapor gagal padahal berhasil, dan transaksi semu di
  sekeliling TRUNCATE dihapus beserta penjelasannya.
- Periode iuran disimpan terstruktur di kolom baru `transaksis.periode`. Rollback
  void tidak lagi menebak lewat teks keterangan; parsing lama tetap ada sebagai
  fallback untuk baris lama, dan untuk padaringan dibatasi ke segmen periode.
- `NotificationService` mendelegasikan pengiriman ke `MpwaService`. Sebelumnya
  keduanya punya endpoint, cara auth, dan normalisasi nomor yang berbeda.
- Zona waktu aplikasi jadi `Asia/Jakarta`. Dengan UTC, pembayaran sebelum pukul
  07.00 WIB tanggal 1 masih terhitung bulan sebelumnya.

**Performa**
- Query di dalam loop pada Dashboard dihapus. Terukur turun dari sekitar 45 ke 16
  query, dan yang lebih penting jumlahnya kini tetap saat jumlah RT bertambah.
- Badge pendaftaran pindah dari Blade ke view composer.
- Index ditambahkan untuk kolom filter/join/sort di 9 tabel.
- `Log Sistem` dan `Data Warga` dipaginasi (sebelumnya `limit(200)` diam-diam dan
  `get()` tanpa batas), memakai partial `partials/pagination`.

**Kebersihan**
- Toolchain Vite/Tailwind dihapus karena tidak dipakai sama sekali (lihat DECISIONS).
- `update_models.php` dihapus. Skrip itu bukan cuma mati: kalau dijalankan lagi ia
  menyuntikkan `$guarded = []` ke `Transaksi.php` dan membatalkan perbaikan
  mass assignment.
- `welcome.blade.php` dan method mati `setorIndex`/`sumbanganIndex` dihapus.
- README dan `.env.example` ditulis ulang untuk project ini.
- 69 tes dibuat dari nol (sebelumnya hanya `ExampleTest` bawaan) mencakup jalur
  uang, void, otorisasi per peran, kepemilikan, helper domain, dan render halaman.

**File:** lihat `git status` - 65 berkas berubah/ditambah/dihapus.
**Catatan:** Verifikasi yang dijalankan: `composer test` (69 lulus, 116 assertion),
Pint bersih untuk seluruh berkas baru, migrasi `up` dan `down` diuji, serta smoke
test HTTP sungguhan termasuk login `jabnet` end-to-end. Yang BELUM: cek UI di
360/768/1280px, dan uji di MySQL (lokal memakai SQLite). Pint masih melaporkan 43
berkas lama menyimpang gaya; sengaja tidak diformat ulang agar diff tetap
terbaca - dicatat sebagai pekerjaan tersendiri di TODO.

## 2026-08-15 - Perbaikan P0: mass assignment transaksi, otorisasi endpoint, akun bawaan
**Agen:** claude | **Status:** selesai (verifikasi runtime belum bisa dijalankan)
**Kenapa:** Tiga temuan paling mendesak dari studi hari ini: pencatatan uang gagal
diam-diam, endpoint pengubah data bisa dipanggil akun warga atas data orang lain,
dan belum ada akun full-access bawaan yang dijamin ada.
**Perubahan:**
- `Transaksi::$fillable` disamakan dengan kolom tabel `transaksis` yang sebenarnya.
  Sebelumnya menyebut 4 kolom yang tidak ada dan melewatkan `transaksi_id`/`kas`
  (NOT NULL) sehingga seluruh insert transaksi gagal.
- Void transaksi memakai `forceFill()->save()`. Kolom void sengaja tidak fillable,
  tapi `update()` juga tunduk pada `$fillable` - jadi void tidak pernah tersimpan.
- Middleware peran ditambahkan untuk surat (ubah/hapus → ketua_rw, TTD/tolak →
  petugas_rt), aduan status, umkm, kegiatan, dan `/search`.
- Cek kepemilikan surat untuk `show` & `cetak`: warga hanya bisa membuka miliknya.
- Kotak pencarian global disembunyikan untuk level warga (data seluruh warga).
- Seeder: akun `admin` dan `jabnet` (superadmin, PIN 463696) dibuat hanya bila
  belum ada. Diubah dari `updateOrCreate` yang mengembalikan PIN ke nilai bawaan
  setiap kali `db:seed` dijalankan di produksi.
**File:** app/Models/Transaksi.php, app/Http/Controllers/TransaksiController.php,
app/Http/Controllers/SuratController.php, routes/web.php,
resources/views/layouts/app.blade.php, database/seeders/DatabaseSeeder.php
**Catatan:** Verifikasi terbatas pada `php -l` (bersih) dan pencocokan manual
seluruh key `Transaksi::create()` terhadap `$fillable`. `composer test` dan
`artisan route:list` belum bisa dijalankan karena `vendor/` dan `.env` tidak ada
di working copy. Wajib dijalankan sebelum deploy. PIN 463696 tersimpan sebagai
teks polos di seeder yang masuk git - lihat catatan risiko di `.ai/TODO.md`.

## 2026-08-15 - Studi awal codebase + pembuatan AGENTS.md & folder .ai
**Agen:** claude | **Status:** selesai
**Kenapa:** Repo belum punya file aturan maupun state, jadi setiap agen baru mulai
dari nol dan berisiko mengulang atau merusak konvensi yang sudah ada.
**Perubahan:** Tambah `AGENTS.md` (stack, kosakata domain, konvensi, ranjau yang
diketahui) dan folder `.ai/` (PROGRESS, TODO, DECISIONS). Tidak ada kode aplikasi
yang diubah.
**File:** AGENTS.md, .ai/PROGRESS.md, .ai/TODO.md, .ai/DECISIONS.md
**Catatan:** Studi menemukan sejumlah temuan serius yang belum diperbaiki (mass
assignment `Transaksi`, endpoint tanpa otorisasi, sisa file PWA lama di `public/`).
Semua dicatat di `.ai/TODO.md` dan menunggu keputusan pemilik project. Verifikasi
runtime belum bisa dilakukan: `vendor/`, `.env`, dan file database tidak ada di
working copy, jadi temuan berbasis pembacaan kode + semantik Laravel, bukan
eksekusi. Satu-satunya verifikasi yang dijalankan: `php -l` untuk seluruh file
di `app/`, `database/`, `routes/`, `config/` - bersih, nol error.
