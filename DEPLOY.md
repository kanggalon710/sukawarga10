# Deploy ke Production — SukaWarga10

Paket ini berisi seluruh perubahan + data terbaru (101 KK RW-10 + 280 anggota, nomor WA ter-normalisasi).
Production memakai **MySQL** (`jabnet_rw10`). Data dibawa lewat artisan importer (DB-agnostic), bukan file SQLite.

## 0. Sebelum mulai
- Server sudah punya `.env` sendiri (MySQL) — paket ini **tidak menyertakan `.env`**, jadi konfigurasi produksi aman.
- **Tidak ada variabel `.env` baru** yang perlu ditambah.
- **Backup database produksi dulu** (mysqldump) sebelum migrate/import.

## 1. Upload kode
Pull dari git ATAU upload & extract arsip ini ke folder aplikasi, lalu:
```bash
cd /var/www/sukawarga10        # sesuaikan path
composer install --no-dev --optimize-autoloader
```
> `node_modules`/`npm build` TIDAK diperlukan — CSS dimuat langsung dari `public/css/styles.css` & `public/css/bi-report.css`.

## 2. Migrasi database (skema + perbaikan data)
```bash
php artisan migrate --force
```
Menjalankan migrasi baru:
- `..._add_demografi_to_anggotas` — kolom anggota (pendidikan, status kawin, agama, dll)
- `..._add_params_to_keluargas` — kolom keluarga (daya listrik, internet, tanggungan, rawan bencana, kesehatan)
- `..._migrate_keluarga_canonical_format` — rapikan sanitasi/bansos ke format kanonik (idempotent)
- `..._normalize_wa_numbers` — normalisasi semua nomor WA lama → `62xxxx` (idempotent)

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
- `import:keluarga` — 95 KK RW-10 (+6 baris non-RW10 ikut), tulis kolom kanonik + bansos boolean + noHP ter-normalisasi.
- `import:anggota` — 280 anggota di-link ke KK induk via No.KK; baris "Kepala Keluarga" melengkapi tgl lahir + jenis kelamin KK (tidak dobel).

## 4. Akun login
Importer tidak membuat akun. Pastikan akun admin produksi ada. Bila perlu seed admin default:
```bash
php artisan db:seed --class=DatabaseSeeder --force   # admin / 123456 (GANTI PIN setelah login!)
```

## 5. Optimasi + bersihkan cache
```bash
php artisan config:clear && php artisan view:clear && php artisan route:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link        # bila belum ada symlink storage
chmod -R ug+rw storage bootstrap/cache
```

## 6. Verifikasi
- Buka `https://sukawarga10.jabnet.id` → login → **Dashboard** (101 KK, indeks kesejahteraan terisi).
- **Laporan → Demografi** → piramida penduduk + section Pendidikan & Kesehatan aktif.
- **Pengaturan → WhatsApp API** → isi API key & sender MPWA → test koneksi → broadcast (nomor otomatis `62xxxx`).

## Ringkasan fitur yang dideploy
Redesign UI (1 font Source Sans 3) · Dashboard indeks kesejahteraan · fix dual-write data · BI Laporan (ranked-bar/KPI/piramida diverging/tabel) · form warga lengkap (anggota demografis, kesehatan, daya listrik dll) · importer KK & anggota · **normalisasi nomor WA otomatis** (insert + broadcast MPWA).
