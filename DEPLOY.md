# Deploy ke Production - Kampung Paru

Paket ini berisi seluruh perubahan + data terbaru (101 KK RW-10 + 280 anggota, nomor WA ter-normalisasi).
Production memakai **MySQL** (`jabnet_rw10`). Data dibawa lewat artisan importer (DB-agnostic), bukan file SQLite.

## 0. Sebelum mulai
- Server sudah punya `.env` sendiri (MySQL) - paket ini **tidak menyertakan `.env`**, jadi konfigurasi produksi aman.
- **Tidak ada variabel `.env` baru** yang perlu ditambah. Identitas aplikasi
  (nama, tagline, lokasi, alamat portal) diatur lewat menu Pengaturan, bukan `.env`.
- **Backup database produksi dulu** (mysqldump) sebelum migrate/import.

## 1. Upload kode
Pull dari git ATAU upload & extract arsip ini ke folder aplikasi, lalu:
```bash
cd /var/www/paru               # sesuaikan path
composer install --no-dev --optimize-autoloader
```
> `node_modules`/`npm build` TIDAK diperlukan - CSS dimuat langsung dari `public/css/styles.css` & `public/css/bi-report.css`.

## 2. Migrasi database (skema + perbaikan data)
```bash
php artisan migrate --force
```
Menjalankan migrasi baru:
- `..._add_demografi_to_anggotas` - kolom anggota (pendidikan, status kawin, agama, dll)
- `..._add_params_to_keluargas` - kolom keluarga (daya listrik, internet, tanggungan, rawan bencana, kesehatan)
- `..._migrate_keluarga_canonical_format` - rapikan sanitasi/bansos ke format kanonik (idempotent)
- `..._normalize_wa_numbers` - normalisasi semua nomor WA lama → `62xxxx` (idempotent)

Ditambah lima migrasi fondasi multi-tenant (`2026_08_15_000004` s.d. `000008`):
organizations + domains + seed hierarki RW 10, pendaftaran hostname dev,
kolom `organization_id` + backfill di 12 tabel, katalog peran +
`user_role_assignments` + backfill dari `users.level`, dan `app_settings`
per organisasi. Semuanya additive dan idempotent, tapi **BACA OUTPUTNYA**:
- Baris backfill per tabel harus menyebut jumlah yang masuk akal (≈101 KK dst).
- `PERINGATAN` pada backfill peran berarti ada akun `petugas_rt` tanpa
  organisasi RT. Sejak hak akses hanya dari assignment, akun itu efektif
  warga - buka Manajemen Akun, isi RT-nya, lalu simpan ulang levelnya.

## 3. Muat data warga (101 KK + 280 anggota)

### ⚠️ PENTING soal `--fresh`
`--fresh` pada `import:keluarga` **MENGHAPUS** seluruh data keluarga, anggota, transaksi, & iuran lama.
- **Deploy awal / produksi belum ada data transaksi penting** → pakai `--fresh` (aman, bersih).
- **Produksi sudah ada pembayaran/iuran yang harus dijaga** → **JANGAN** `--fresh`. Hubungi dulu / lakukan import selektif.

### Perintah (skenario deploy awal)
```bash
php artisan import:keluarga "database/seed-data/keluarga.csv" --fresh
php artisan import:anggota  "database/seed-data/anggota.csv"  --fresh
```
- `import:keluarga` - 95 KK RW-10 (+6 baris non-RW10 ikut), tulis kolom kanonik + bansos boolean + noHP ter-normalisasi.
- `import:anggota` - 280 anggota di-link ke KK induk via No.KK; baris "Kepala Keluarga" melengkapi tgl lahir + jenis kelamin KK (tidak dobel).

## 4. Akun login
Importer tidak membuat akun. Pastikan akun admin produksi ada. Bila perlu seed admin default:
```bash
php artisan db:seed --class=DatabaseSeeder --force   # admin / 463696 (GANTI PIN setelah login!)
```

## 5. Optimasi + bersihkan cache
```bash
php artisan config:clear && php artisan view:clear && php artisan route:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link        # bila belum ada symlink storage
chmod -R ug+rw storage bootstrap/cache
```

## 6. Verifikasi
- Buka `https://paru.jabnet.id` → login → **Dashboard** (101 KK, indeks kesejahteraan terisi).
- **Uji login SEMUA level akun** (ketua RW, bendahara, petugas RT, warga) -
  hak akses kini dari tabel assignment hasil backfill, bukan kolom level.
  Kalau ada akun yang mendadak "jadi warga", lihat catatan PERINGATAN di
  langkah 2.
- **Akses lewat hostname selain `paru.jabnet.id`/`sukawarga10.jabnet.id`
  (termasuk IP server langsung) kini 404.** Itu disengaja (resolver tenant).
  Kalau operator butuh hostname tambahan, daftarkan barisnya di tabel
  `domains`, jangan mengubah kode.
- **Laporan → Demografi** → piramida penduduk + section Pendidikan & Kesehatan aktif.
- **Pengaturan → WhatsApp API** → isi API key & sender MPWA → test koneksi → broadcast (nomor otomatis `62xxxx`).
- Satu pembayaran uji → cek kolom `kas`, `transaksi_id`, dan `organization_id` terisi.

## 7. Pindah domain (paru.jabnet.id → desa.jabnet.id)

Domain produksi saat ini `paru.jabnet.id`. Per 2026-08-16 DNS `desa.jabnet.id`
**sudah resolve** ke IP yang sama (103.194.47.165), tapi web server belum
mengenalnya (HTTPS masih jatuh ke vhost default akun lain). Tidak ada
perubahan kode yang diperlukan - hanya konfigurasi server + dua baris data.

Urutan yang benar:

1. **DNS `desa.jabnet.id`** mengarah ke server yang sama (SUDAH), lalu
   terbitkan sertifikat TLS-nya (di cPanel: AutoSSL setelah domainnya dibuat).
2. **Tambahkan domain baru ke konfigurasi web server** sebagai `ServerAlias` /
   `server_name` tambahan (di cPanel: Domains → Create a New Domain dengan
   document root yang SAMA dengan paru, yaitu folder `public` aplikasi),
   jangan langsung menggantikan yang lama. Selama masa transisi keduanya harus
   hidup, karena warga masih memegang link lama dari pesan WhatsApp yang
   sudah terkirim.
3. **Daftarkan hostname di tabel `domains`** - resolver tenant menjawab 404
   untuk hostname yang tidak terdaftar (`desa.jabnet.id` sengaja tidak ikut
   seed migrasi):
   ```bash
   php artisan tinker --execute="\App\Models\Domain::firstOrCreate(['hostname' => 'desa.jabnet.id'], ['organization_id' => \App\Models\Organization::where('slug', 'rw-10-sukakarya')->value('id'), 'is_primary' => false, 'status' => 'aktif']);"
   ```
4. **Ubah `APP_URL`** di `.env` produksi jadi `https://desa.jabnet.id`, lalu
   `php artisan config:clear && php artisan config:cache`.
5. **Ubah "Alamat Portal"** di menu **Pengaturan > Info RW > Identitas Aplikasi**
   jadi `desa.jabnet.id`. Ini yang menentukan link di pesan WhatsApp ke warga.
   Isi nama domain saja, tanpa `https://` dan tanpa garis miring; form menolak
   nilai yang memakai skema atau path.
6. **Uji satu pesan sungguhan**: Pengaturan > WhatsApp API > test koneksi, lalu
   periksa link di pesan yang masuk sudah memakai domain baru dan bisa dibuka.
7. **Biarkan `paru.jabnet.id` hidup** dengan redirect 301 ke domain baru,
   setidaknya sampai satu siklus tagihan penuh berlalu.

Catatan: `sukawarga10.jabnet.id` masih hidup di IP yang berbeda
(103.194.46.164) dan kemungkinan deployment lama. Pastikan tidak ada warga yang
masih diarahkan ke sana, dan matikan atau redirect kalau memang sudah tidak
dipakai.

## 8. Membuka desa/RW baru (Phase D)

Satu instalasi melayani banyak desa/RW; tiap RW adalah tenant dengan subdomain,
identitas, tarif, dan datanya sendiri. Skema alamatnya flat:
`{label}-rw{nn}.desa.jabnet.id` (mis. `cibunar-rw01.desa.jabnet.id`).

1. **DNS wildcard (SEKALI SAJA, berlaku untuk semua tenant selamanya):**
   tambah record A `*.desa.jabnet.id` → IP server. Setelah ini tenant baru
   tidak butuh perubahan DNS lagi.
2. **Buka tenant** - dua cara, hasilnya sama (logika satu sumber):
   - **Lewat website:** login sebagai admin platform → menu **Manajemen
     Desa** → form "Buka Desa / Tambah RW". PIN admin per RW tampil SEKALI
     di halaman hasil - catat saat itu juga.
   - **Lewat terminal** (idempotent, aman diulang untuk menambah RW):
     ```bash
     php artisan tenant:buat "Desa Cibunar" cibunar --kecamatan="Tarogong Kidul" --rw=01,02,03
     ```
   Keduanya membuat organisasi desa + RW, baris `domains`, dan akun admin per
   RW (username `{label}-rw{nn}`, PIN acak sekali-tayang). Dua desa bernama
   sama boleh, asalkan labelnya beda (mis. `cibunar` vs `cibunarkota`).
   Menu Manajemen Desa hanya tampil untuk pemegang super_admin PLATFORM
   (akun `admin`/`jabnet` bawaan) - superadmin buatan Manajemen Akun tidak.
3. **cPanel per subdomain:** Domains → Create a New Domain →
   `cibunar-rw01.desa.jabnet.id`, document root = folder `public` aplikasi
   (sama untuk semua tenant) → Run AutoSSL. (AutoSSL tidak menerbitkan
   wildcard, jadi tiap subdomain tetap didaftarkan; DNS-nya sudah ditutup
   wildcard di langkah 1.)
4. **Serahkan akses ke admin RW:** login dengan akun dari langkah 2 →
   menu Pengaturan: identitas (nama tampilan, tagline, lokasi),
   **Alamat Portal = hostname RW-nya**, tarif iuran, WhatsApp API →
   ganti PIN lewat Manajemen Akun.
5. **Kredensial MPWA satu desa (opsional):** tanam sekali di baris
   `app_settings` ber-`organization_id` DESA (lewat tinker), seluruh RW di
   bawahnya mewarisi; RW yang mengisi sendiri lewat Pengaturan menimpa
   warisan itu.
6. **Perilaku login lintas tenant:** warga/pengurus yang mencoba login di
   subdomain RW lain ditolak dengan pesan yang menyebutkan alamat portal
   RW asalnya. Akun lama tanpa tautan keluarga/peran tetap bisa login di
   mana pun (transisi).

## Ringkasan fitur yang dideploy
Redesign UI (1 font Source Sans 3) · Dashboard indeks kesejahteraan · fix dual-write data · BI Laporan (ranked-bar/KPI/piramida diverging/tabel) · form warga lengkap (anggota demografis, kesehatan, daya listrik dll) · importer KK & anggota · **normalisasi nomor WA otomatis** (insert + broadcast MPWA).
