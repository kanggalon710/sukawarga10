# AGENTS.md - Kampung Paru

Aturan khusus project ini. Aturan universal ada di standar global agent.
Kalau bertabrakan, file ini yang menang.

> **Baru di project ini? Baca `.ai/HANDOFF.md` dulu.** Isinya keadaan terkini:
> apa yang sudah diverifikasi, apa yang belum, dan jebakan yang paling sering
> memakan korban. File ini menjelaskan *aturannya*, HANDOFF menjelaskan
> *keadaannya*.

## Apa ini

Sistem informasi & billing komunitas warga. Produksi: `https://paru.jabnet.id`
(rencana pindah ke `desa.jabnet.id`, lihat `DEPLOY.md`). Instansi yang dilayani
sekarang: **RW 10 Kel. Sukakarya, Kec. Tarogong Kidul, Garut**, data nyata ~101 KK
/ ~280 jiwa. Bukan demo, bukan proyek latihan: uang iuran warga dan data pribadi
ada di dalamnya.

## Stack

| Bagian | Pilihan |
|---|---|
| Framework | Laravel 12, PHP ^8.2 (mesin dev: PHP 8.4) |
| View | Blade server-rendered, **tanpa** SPA/Livewire/Inertia |
| CSS | `public/css/styles.css` + `public/css/bi-report.css` + `public/css/mobile-fixes.css` (plain CSS, di-link langsung) |
| Ikon | Font Awesome 6 via CDN |
| DB produksi | MySQL (`jabnet_rw10`) |
| DB lokal | SQLite (`.env.example` default) |
| Auth | Web guard bawaan, login **username + PIN** (kolom `pin`, bukan `password`) |
| Notifikasi | WhatsApp lewat API MPWA (`mpwa.jabnet.id`) |
| Zona waktu | `Asia/Jakarta` (batas bulan tagihan harus WIB, bukan UTC) |

### Tidak ada build front-end

Tidak ada Node, npm, Vite, atau Tailwind di project ini - toolchain-nya dihapus
2026-08-15 karena tidak satu pun Blade memanggil `@vite` (lihat
`.ai/DECISIONS.md`). Jangan menambahkan class Tailwind ke Blade, tidak ada yang
meng-compile-nya. Kalau butuh gaya baru, edit `public/css/styles.css` dan pakai
CSS variable yang sudah ada (`--merah`, `--abu`, `--abu2`, `--text2`, `--text3`,
`--radius`, `--shadow-lg`, dll).

## Perintah

```bash
composer setup          # install + key + sqlite + migrate + seed
composer dev            # php artisan serve
composer test           # config:clear lalu artisan test
php artisan migrate     # produksi: tambahkan --force
php -l <file>           # cek sintaks cepat, jalan tanpa vendor/
```

Sebelum mengklaim sesuatu "jalan", jalankan `composer test` dan baca hasilnya.
Jangan mengasumsikan.

Deploy: ikuti `DEPLOY.md` apa adanya, terutama peringatan `--fresh` pada
`import:keluarga` (menghapus seluruh transaksi & iuran).

## Bahasa

Kode, komentar, pesan commit, dan seluruh teks UI memakai **bahasa Indonesia**.
Nama kolom & variabel domain juga Indonesia dan **camelCase** (`namaLengkap`,
`noHP`, `ikutSampah`, `tanggalLahirKK`) - beda dari konvensi snake_case Laravel.
Ikuti yang sudah ada, jangan "dirapikan" jadi snake_case.

## Kosakata domain

| Istilah | Arti |
|---|---|
| KK / Keluarga | Satu kartu keluarga = satu baris `keluargas` |
| Anggota | Anggota keluarga selain kepala KK (`anggotas`) |
| Iuran Sampah | Ditagih **per minggu**, kunci `BLN-Mn` (mis. `JAN-M1`) di `iuran_sampahs.weeks` |
| Padaringan | Iuran **per bulan**, kunci `JAN`..`DES` di `iuran_padaringans.months` |
| Kas | `umum` / `sampah` / `padaringan` - kolom `transaksis.kas` |
| Void | Pembatalan transaksi (tidak dihapus), hanya superadmin & ketua_rw |
| Tunggakan | KK peserta yang belum lunas periode berjalan |
| MPWA | Gateway WhatsApp pihak ketiga untuk notifikasi |

**Total jiwa** = jumlah KK aktif + jumlah anggota. Definisi ini dipakai di
Dashboard, Laporan, dan accessor `Keluarga::totalJiwa` - jaga tetap satu definisi.

## Peran & akses

Peran: `superadmin` (operator portal, tak terbatas), `ketua_rw`, `sekretaris`,
`bendahara`, `petugas_rt`, `warga`. **BUKAN hierarki** - sejak 2026-08-18 ini
matriks kapabilitas: ketua tidak bisa membuat surat, bendahara tidak menyentuh
surat, sekretaris tidak menyentuh kas. Peran rangkap MENGGABUNGKAN kapabilitas,
bukan mengambil yang tertinggi.

Satu sumber kebenaran: `App\Services\MatriksKapabilitas` (`KATALOG` + `BAWAAN`).
Dari situ mengalir keduanya:

- Middleware `izin:<kapabilitas>` (`PastikanBerizin`) → penjaga rute sungguhan.
  Beberapa argumen berarti OR murni.
- `bolehkah('modul.aksi')` di `app/helpers.php` → cek yang sama untuk controller
  dan blade (tombol aksi).
- `userCan($menuKey)` → tampil/sembunyi menu, DITURUNKAN dari matriks yang sama
  ("punya minimal satu kapabilitas di modul itu"). Tetap bukan pengamanan.

**Aturan wajib:** setiap rute yang mengubah data harus punya middleware `izin:`,
atau terdaftar beserta alasannya di `KapabilitasRuteTest::TANPA_IZIN` (untuk yang
gerbangnya kepemilikan, mis. `/profil`). Dijaga mesin oleh
`tests/Feature/KapabilitasRuteTest.php` - rute baru yang lupa dijaga langsung
menjatuhkan tes. Urutan wajib `fitur:` DULU, baru `izin:` (modul mati = 404,
bukan 403 yang membocorkan keberadaannya).

Matriks bisa disesuaikan per tenant lewat setting `kapabilitas_peran` (delta
bool), dan **hanya admin platform** yang punya formnya (`/tenant/rw/{id}/matriks`).
Pengurus RW melihatnya read-only di Manajemen Akun. Menambah kapabilitas baru =
tambah entri `KATALOG` + berikan ke peran di `BAWAAN` (atau daftarkan di
`KHUSUS_SUPERADMIN`) + pasang `izin:` di rutenya.

Hierarki lama sudah DIHAPUS (2026-08-18): tidak ada lagi `CheckRole`, alias
`role:`, `User::LEVEL_POWER`, maupun helper izin berbasis nama level
(`isSuperAdmin`, `canVoid`, dst). Kalau Anda tergoda menambahkannya kembali,
jangan - dikunci `tests/Feature/PensiunHierarkiLamaTest.php`.

## Aturan yang mudah dilanggar

Baca `.ai/TODO.md` sebelum menyentuh area ini.

1. **`$fillable` harus cocok dengan kolom tabel.** Laravel membuang atribut
   non-fillable tanpa bersuara. `Transaksi` dan `User` pernah menyebut kolom yang
   tidak ada sekaligus melewatkan kolom yang benar-benar ditulis, dan akibatnya
   seluruh pencatatan uang gagal. Ada tes yang mengunci ini
   (`tests/Feature/PencatatanIuranTest.php`) - jangan dilewati.
2. **`update()` juga tunduk pada `$fillable`.** Kolom void pada `Transaksi`
   sengaja tidak fillable, jadi aksi void memakai `forceFill()->save()`.
3. **Pengiriman WhatsApp hanya lewat `MpwaService`.** `NotificationService`
   mengurus penyaluran (siapa yang dikirimi) dan mendelegasikan pengirimannya.
   Jangan menambah pemanggil `Http::post` ke gateway di tempat lain.
4. **Nomor WA** selalu lewat `normalizeWa()` di `app/helpers.php` → format `62xxxx`.
   Jangan menulis normalisasi sendiri.
5. **Uang** selalu lewat `formatRupiah()`. Jangan `number_format` manual di Blade.
6. **`AppSetting`** adalah key-value ber-scope organisasi yang ikut mengatur
   otorisasi (`role_permissions`) dan ambang kemiskinan (`garis_kemiskinan`).
   Baca HANYA lewat `AppSetting::nilai()`/`semuaEfektif()`, tulis lewat
   `simpan()` - query `where('key')` polos tidak tahu inheritance
   platform→desa→RW dan bisa mengambil baris tenant lain. Form Pengaturan
   memakai whitelist key; jangan diganti jadi menerima `$request->all()`.
7. **Kepemilikan data warga** diikat lewat `users.keluarga_id`, bukan kecocokan
   nama. Jangan pernah mencari KK milik seseorang lewat `nama`.
8. **Identitas aplikasi jangan ditulis tetap.** Pakai `namaAplikasi()`,
   `taglineAplikasi()`, `lokasiSingkat()`, dan `alamatPortal()` di
   `app/helpers.php`; nilainya dari `app_settings` dengan bawaan di helper. Ini
   yang membuat project turunan untuk kampung lain cukup ganti lewat menu
   Pengaturan. Dikunci oleh `tests/Feature/HalamanUtamaTest.php` dan
   `tests/Feature/OtorisasiTest.php`. Konstanta tidak bisa memanggil fungsi, jadi
   teks yang menyebut nama komunitas ditaruh di method, bukan `const`.
9. **Isolasi tenant lewat global scope, dan HANYA hidup di Eloquent.**
   Model ber-trait `ScopedKeOrganisasi` (mulai dari `Transaksi`, `Keluarga`)
   otomatis tersaring ke tenant request; `DB::table()` TIDAK ikut tersaring,
   jadi query resource tenant wajib lewat model. Butuh lintas tenant secara
   sadar? `Model::withoutGlobalScope('organisasi')` dengan komentar alasan.
   Dijaga `tests/Feature/IsolasiTenantTest.php`; model baru yang diberi scope
   wajib ditambahkan ke tes itu.
10. **Alamat portal bukan `APP_URL`.** `alamatPortal()` mengembalikan nama host
   saja (mis. `paru.jabnet.id`) dan ditempel apa adanya ke pesan WhatsApp.
   Sengaja tidak dari `APP_URL` karena di lokal nilainya `http://localhost:8000`,
   dan salah setel berarti warga menerima link yang tidak bisa dibuka. Nilainya
   divalidasi sebagai nama host (tanpa skema, tanpa path) di `PengaturanController`.
11. **Rute modul wajib dibungkus `fitur:<menu key>`.** Feature flag per tenant
   (`fitur_<modul> = 0`) menyembunyikan menu lewat `userCan()` DAN menutup
   rutenya (404) lewat middleware `PastikanFiturAktif` - dua-duanya memakai
   menu key yang sama dari `getAllMenuItems()`. Menambah modul baru tanpa
   membungkus rutenya berarti modul itu tidak bisa dimatikan per tenant.
   Dijaga `tests/Feature/PengaturanTenantTest.php` dan `KapabilitasRuteTest.php`.
12. **`users.level` BUKAN sumber otorisasi.** Hak akses hanya dari assignment
   `(user, peran, organisasi)` yang dibaca `peranEfektif()`; tanpa assignment,
   lantainya warga. Kolom `level` tinggal catatan tampilan & sasaran
   notifikasi, dan Manajemen Akun-lah yang memelihara assignment-nya
   (`AkunController::selaraskanAssignment`). Cek izin baru wajib lewat
   `bolehkah('modul.aksi')`, jangan membaca kolom `level` maupun
   membandingkan nama level. Fixture tes pengurus dipasangkan perannya lewat
   `TestCase::pasangPeranSetaraLevel()`.
   Dijaga `tests/Feature/ManajemenAkunTest.php` dan `PeranScopeTest.php`.
13. **`levelEfektif()` hanya untuk LABEL dan penyaringan KEPEMILIKAN**
   ("warga hanya melihat suratnya sendiri"), bukan untuk izin. Ia mengembalikan
   satu peran teratas menurut `MatriksKapabilitas::URUTAN_TAMPIL`, yang sengaja
   BUKAN hierarki hak. Untuk izin selalu `bolehkah()`.

## Konvensi kode

- Controller gemuk, tanpa Form Request / Policy / Repository. Ikuti pola tetangga;
  kalau memperkenalkan Form Request atau Policy, catat alasannya di `.ai/DECISIONS.md`.
- ID bisnis dibuat manual dengan prefix + `uniqid()`: `KS-`, `KP-`, `BK-`, `TRX-`,
  `SRT-`, `ADU-`, `UMKM-`, `KGT-`, `STR-`, `SMB-`, `LOG-`, `kk_`, `ag_`.
- Konstanta domain (tarif, template WA, ambang) disimpan di `app_settings`,
  bukan di-hardcode. Default aman ditaruh di helper.
- Audit: tulis lewat `AuditLogService::log()`, jangan `AuditLog::create()` langsung
  (skema audit punya kolom yang mudah salah tulis).
- Kolom filter wajib punya index (lihat migrasi `..._add_indexes_to_hot_columns`).
  Kalau menambah query filter baru, tambahkan index-nya sekalian.
- Daftar yang bisa tumbuh dipaginasi, bukan `limit()` diam-diam. Navigasinya
  memakai `@include('partials.pagination', ['paginator' => $x])`.
- Jangan menaruh query di dalam Blade. Kalau layout butuh angka, pakai view
  composer di `AppServiceProvider`.

## Definisi selesai

Sebelum mengklaim selesai: `composer test` lulus (jalankan, baca hasilnya),
UI dicek di 360/768/1280px, dan `.ai/PROGRESS.md` + `.ai/TODO.md` sudah
diperbarui.
