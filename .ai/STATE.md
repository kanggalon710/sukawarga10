# STATE - Kampung Paru / Sukawarga
Updated: 2026-09-30 by claude-opus-5.5 (Claude Code)

## What this is
Portal informasi, administrasi, dan penagihan iuran warga berbasis Laravel 12,
Blade, dan CSS biasa. DUA instalasi dari repo yang sama di akun cPanel
`jabnet@103.194.47.165`, keduanya mengikuti branch `production`:
- Produksi `https://desa.jabnet.id` (`~/repositories/desa-manage`), data warga nyata.
- Demo `https://demo-sukawarga.jabnet.id` (`~/repositories/demo-sukawarga`), data
  fiktif, `DEMO_MODE=true` (WA mati, pita "Situs demo"). Rincian: DEPLOY.md.
Repo GitHub `kanggalon710/sukawarga10` bersifat publik.

## Run and verify
```bash
composer install
cp .env.example .env && php artisan key:generate
ADMIN_USERNAME=namaanda composer setup   # seeder WAJIB ADMIN_USERNAME
composer test                            # 533 lulus, 2289 assertion (dengan .env)
php artisan serve
```
Demo lokal: `DEMO_MODE=true DEMO_HOST=demo.localhost APP_URL=http://demo.localhost`
lalu `db:seed --class=DemoSeeder` pada DB kosong; buka `http://demo.localhost:8000`.

## Works
- `main` = `dev` = `production` = `90ca47d`; produksi & demo ter-deploy di commit ini.
- Phase 1 P0 keamanan (lihat PROGRESS 2026-09-30) aktif di produksi.
- Merek per tenant dari Pengaturan > Tampilan (`MerekAplikasi`): logo, ikon/favicon,
  warna utama (hex + kontras 4,5:1), manifest per tenant. Tanpa setting = tampilan lama.
- Iuran: halaman bayar kini memakai `keluargas.id` numerik (dulu 404 setiap kali;
  produksi belum pernah mencatat iuran). Dashboard & Laporan ikut membaca id numerik.
- Browser: produksi 360/768/1280 bersih; demo live 50/50 cek; lokal 93/93 + bayar lewat UI.

## In progress
Tidak ada pekerjaan setengah jadi. Berikutnya: Phase 2 dari
`.ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md` (CI, APP_KEY tes, upgrade Laravel >= 12.69
+ Flysystem, Larastan, rapikan baseline Pint).

## Blocked, needs a human
- Aset merek demo asli (logo, ikon, warna, nama): unggah via Pengaturan > Tampilan demo.
- Kunci MPWA produksi belum ada (WA produksi tidak terkirim sampai diisi).
- Rotasi PIN akun lama di repo publik (`admin`, `jabnet`, akun `auth.js` lama).
- Keputusan pembersihan riwayat git publik; kebijakan SEO halaman publik desa.

## Traps
- `ResolveTenant` membatasi host platform/desa ke beberapa jalur (`app/Http/Middleware/ResolveTenant.php`);
  rute publik baru yang dirujuk semua halaman wajib ditambahkan di sana.
- `iuran_*.keluarga_id` & `transaksis.refKeluargaId` = `keluargas.id` NUMERIK, bukan `kk_...`
  (lihat `ScopedKeOrganisasiViaKeluarga`). `anggotas.keluarga_id` justru ID bisnis.
- Rahasia MPWA hanya lewat `AppSetting::simpanRahasia()/rahasia()`; `simpan()` menolaknya.
- `DemoSeeder` menghapus SEMUA domain di DB-nya; pengamannya APP_URL==DEMO_HOST + DB kosong.
- Tes yang memakai `Http::fake` dua kali: stub pertama menang; pakai closure bersaklar.

## Recently touched
claude-opus-5.5 (Claude Code), 2026-09-30: `app/Services/{MerekAplikasi,PenyimpanBerkas,
MpwaService,PembukaTenant}.php`, `app/Http/Controllers/{Pengaturan,Manifest,Dashboard,
Laporan,Transaksi}Controller.php`, `app/Http/Middleware/ResolveTenant.php`,
`app/Models/AppSetting.php`, `database/seeders/DemoSeeder.php`, `config/app.php`,
views (layout, login, beranda, pengaturan, billing, tenant, 3 partial baru),
`public/css/styles.css`, `public/site.webmanifest` (dihapus -> rute), 4 berkas tes baru.
Server: DNS A `demo-sukawarga.jabnet.id`, subdomain cPanel, DB `jabnet_demosukawarga`.
Berkas `stai-garut-r-7095b88a.webp` milik pengguna, tidak disentuh.
