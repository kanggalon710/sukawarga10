# STATE - Kampung Paru / Sukawarga
Updated: 2026-09-30 by claude-opus-5.5 (Claude Code)

## What this is
Portal informasi, administrasi, dan penagihan iuran warga berbasis Laravel 12,
Blade, dan CSS biasa. Produksi aktif di `https://desa.jabnet.id`; data produksi
memuat uang iuran dan data pribadi warga. Repo GitHub `kanggalon710/sukawarga10`
bersifat publik.

## Run and verify
```bash
composer install
cp .env.example .env && php artisan key:generate
ADMIN_USERNAME=namaanda composer setup   # seeder WAJIB ADMIN_USERNAME
composer test                            # 493 lulus, 1947 assertion (dengan .env)
php artisan serve
```
Tanpa `.env`, tes masih gagal karena APP_KEY (Phase 2). Pint: berkas baru bersih;
berkas lama tidak bertambah pelanggaran (baseline 34 berkas, Phase 2).

## Works
- Phase 1 (P0 keamanan) SELESAI dan TER-DEPLOY 2026-09-30: `main`, `dev`, dan
  `production` = `c5aed80` (termasuk `f1f3084` yang sebelumnya belum di produksi).
  Server `jabnet@103.194.47.165:~/repositories/desa-manage`, backup sebelum
  deploy di `~/cadangan/p0-2026-09-30/` (db.sql.gz, env.bak, commit-sebelum.txt):
  unggahan lewat `PenyimpanBerkas` (sniff isi, batas ukuran, kode ulang GD,
  disk privat, `DokumenWargaController` berizin); lupa PIN berkode sekali pakai
  + pembatas laju + jawaban seragam; kunci MPWA terenkripsi, tidak pernah ke
  view, host gateway dari config + allow-list; seeder tanpa PIN literal;
  galat mentah diganti kode rujukan (`laporGagal`).
- Review independen (skill code-review, high): 10 temuan, semua diperbaiki.
- Browser 360/768/1280: 268/268 cek lolos (overflow, label, fokus, konsol,
  status galat/jaringan/batas laju). Skrip: lihat PROGRESS 2026-09-30.
- Matriks kapabilitas, feature flag, scope tenant, AppSetting bertingkat tetap.

## In progress
Phase 1 selesai dan aktif di produksi. Berikutnya Phase 2
(`.ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md`): CI, APP_KEY tes, upgrade
Laravel >= 12.69 + Flysystem, Larastan, rapikan 34 berkas Pint.

## Blocked, needs a human
- Rotasi PIN akun lama yang pernah tercatat di repo publik (`admin`, `jabnet`,
  akun dari `auth.js` lama) - rilis P0 sudah aktif, rotasi aman dilakukan.
- Produksi TIDAK punya kunci MPWA di database (migrasi mengenkripsi 0 baris):
  WA tidak terkirim sampai kunci diisi lewat Pengaturan > WhatsApp API.
- Uji manual oleh pengurus: unggah dokumen KK dan alur lupa PIN end-to-end
  (butuh kunci MPWA dan akun nyata; agen tidak memakai kredensial produksi).
- Keputusan pembersihan riwayat git publik (data warga + PIN lama).
- Kebijakan SEO halaman publik desa (robots saat ini memblokir semua).

## Traps
- `AppSetting::simpan('mpwa_api_key', ..)` MELEMPAR exception: rahasia wajib
  lewat `simpanRahasia()`/`rahasia()` (`app/Models/AppSetting.php`). Mengganti
  APP_KEY membuat kunci MPWA tak terbaca.
- Unggahan berkas: JANGAN `->store()` langsung; pakai `PenyimpanBerkas`
  (`app/Services/PenyimpanBerkas.php`). Dokumen warga di disk `local`, bukan `public`.
- Lupa PIN memakai `defer()`: di tes wajib `$this->withoutDefer()`; kedua
  `Http::fake()` TIDAK menimpa stub pertama (pakai closure bersaklar).
- Rollback rilis P0: `migrate:rollback --step=3` SELAGI kode baru terpasang.
- Query bulanan laporan dalam loop (`LaporanController.php:47-49`) dan WA
  broadcast sinkron masih ada (Phase 3).

## Recently touched
Oleh claude-opus-5.5 (Claude Code), 2026-09-30: `app/Services/{PenyimpanBerkas,
MpwaService,PembaruAplikasi,AuditLogService,PemeriksaNikWarga}.php`,
`app/Http/Controllers/{DokumenWarga,Keluarga,ProfilWarga,Pengaturan,WebAuth,
Mpwa,ExportImport}Controller.php`, `app/Models/{AppSetting,PemulihanPin}.php`,
`app/Console/Commands/{AmankanDokumenWarga,ImportPendataanKeluarga}.php`,
`app/helpers.php`, `app/Providers/AppServiceProvider.php`, 3 migrasi
`2026_09_30_*`, seeder, config (app/services/filesystems), routes, view
login/pengaturan/pembaruan/warga + 2 partial, `public/css/styles.css`,
7 berkas tes baru + 5 disesuaikan, README/DEPLOY/.env.example.
Berkas `stai-garut-r-7095b88a.webp` milik pengguna, tidak disentuh.
