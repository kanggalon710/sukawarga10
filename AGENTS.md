# AGENTS.md — SukaWarga10

Aturan khusus project ini. Aturan universal ada di standar global agent.
Kalau bertabrakan, file ini yang menang.

## Apa ini

Sistem informasi & billing komunitas **RW 10 Kel. Sukakarya, Kec. Tarogong Kidul,
Garut**. Produksi: `https://sukawarga10.jabnet.id`. Data nyata ~101 KK / ~280 jiwa.
Bukan demo, bukan proyek latihan: uang iuran warga dan data pribadi ada di dalamnya.

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

Tidak ada Node, npm, Vite, atau Tailwind di project ini — toolchain-nya dihapus
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
`noHP`, `ikutSampah`, `tanggalLahirKK`) — beda dari konvensi snake_case Laravel.
Ikuti yang sudah ada, jangan "dirapikan" jadi snake_case.

## Kosakata domain

| Istilah | Arti |
|---|---|
| KK / Keluarga | Satu kartu keluarga = satu baris `keluargas` |
| Anggota | Anggota keluarga selain kepala KK (`anggotas`) |
| Iuran Sampah | Ditagih **per minggu**, kunci `BLN-Mn` (mis. `JAN-M1`) di `iuran_sampahs.weeks` |
| Padaringan | Iuran **per bulan**, kunci `JAN`..`DES` di `iuran_padaringans.months` |
| Kas | `umum` / `sampah` / `padaringan` — kolom `transaksis.kas` |
| Void | Pembatalan transaksi (tidak dihapus), hanya superadmin & ketua_rw |
| Tunggakan | KK peserta yang belum lunas periode berjalan |
| MPWA | Gateway WhatsApp pihak ketiga untuk notifikasi |

**Total jiwa** = jumlah KK aktif + jumlah anggota. Definisi ini dipakai di
Dashboard, Laporan, dan accessor `Keluarga::totalJiwa` — jaga tetap satu definisi.

## Peran & akses

Level: `superadmin` > `ketua_rw` > `bendahara` > `petugas_rt` > `warga`
(`admin` diperlakukan setara superadmin). Dua mekanisme terpisah:

- `userCan($menuKey)` di `app/helpers.php` → **hanya untuk tampil/sembunyi menu.**
  Ini BUKAN pengamanan. Jangan pernah jadikan satu-satunya penjaga endpoint.
- Middleware `role:` (`app/Http/Middleware/CheckRole.php`) → penjaga rute sungguhan.

**Aturan wajib:** setiap rute yang mengubah data harus punya middleware `role:`
atau pengecekan kepemilikan eksplisit di controller. Beberapa rute lama belum
memenuhi ini (lihat `.ai/TODO.md`); jangan tiru polanya.

## Aturan yang mudah dilanggar

Baca `.ai/TODO.md` sebelum menyentuh area ini.

1. **`$fillable` harus cocok dengan kolom tabel.** Laravel membuang atribut
   non-fillable tanpa bersuara. `Transaksi` dan `User` pernah menyebut kolom yang
   tidak ada sekaligus melewatkan kolom yang benar-benar ditulis, dan akibatnya
   seluruh pencatatan uang gagal. Ada tes yang mengunci ini
   (`tests/Feature/PencatatanIuranTest.php`) — jangan dilewati.
2. **`update()` juga tunduk pada `$fillable`.** Kolom void pada `Transaksi`
   sengaja tidak fillable, jadi aksi void memakai `forceFill()->save()`.
3. **Pengiriman WhatsApp hanya lewat `MpwaService`.** `NotificationService`
   mengurus penyaluran (siapa yang dikirimi) dan mendelegasikan pengirimannya.
   Jangan menambah pemanggil `Http::post` ke gateway di tempat lain.
4. **Nomor WA** selalu lewat `normalizeWa()` di `app/helpers.php` → format `62xxxx`.
   Jangan menulis normalisasi sendiri.
5. **Uang** selalu lewat `formatRupiah()`. Jangan `number_format` manual di Blade.
6. **`AppSetting`** adalah key-value yang ikut mengatur otorisasi
   (`role_permissions`) dan ambang kemiskinan (`garis_kemiskinan`). Form
   Pengaturan memakai whitelist key; jangan diganti jadi menerima
   `$request->all()`.
7. **Kepemilikan data warga** diikat lewat `users.keluarga_id`, bukan kecocokan
   nama. Jangan pernah mencari KK milik seseorang lewat `nama`.

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
