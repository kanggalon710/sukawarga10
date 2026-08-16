# TODO

Diperbarui 2026-08-15 setelah penyelarasan project dengan standar pengembangan.
Riwayat lengkap ada di `.ai/PROGRESS.md`.

---

## Wajib dilakukan saat deploy

Bukan pekerjaan kode, tapi jangan dilewat.

- [ ] **Backup database produksi** sebelum `php artisan migrate --force`.
      Ada tiga migrasi baru: `users.keluarga_id` (+backfill),
      `transaksis.periode`, dan index pada 9 tabel.
- [ ] **Cek hasil backfill `users.keluarga_id`.** Migrasi mencetak berapa akun
      warga yang tertaut dan berapa yang ambigu. Akun yang ambigu tidak bisa
      membuka menu "Profil Saya" sampai `keluarga_id`-nya diisi manual. Ini
      disengaja: menebak KK yang salah jauh lebih berbahaya.
- [ ] **Ganti PIN akun `admin` dan `jabnet`** lewat menu Manajemen Akun.
      PIN bawaannya ada di seeder yang masuk git.
- [ ] **PIN `admin` di produksi TIDAK ikut berubah oleh seeder.** Seeder
      create-if-missing, dan akun `admin` sudah ada di DB produksi, jadi
      `db:seed` akan melewatinya. PIN bawaan di seeder diubah jadi `463696`
      (2026-08-15) hanya berlaku untuk instalasi baru. Untuk produksi, setel
      manual lewat Manajemen Akun.
- [ ] **Pastikan kredensial lama sudah mati.** `public/js/auth.js` yang berisi
      `admin/123456`, `ketuarw/111111`, `bendahara/222222`, `rt01/010101`,
      `rt02/020202` sudah dihapus dari repo, tapi akunnya mungkin masih ada di
      DB produksi. Cek dan nonaktifkan atau ganti PIN-nya.
- [ ] **Set `SESSION_SECURE_COOKIE=true`** di `.env` produksi (HTTPS).
- [ ] **Set `APP_TIMEZONE=Asia/Jakarta`** atau biarkan default barunya.
      Efek samping: stempel waktu lama yang tersimpan sebagai UTC akan tampil
      bergeser 7 jam. Kolom `tanggal` (tipe date) tidak terpengaruh, jadi
      perhitungan iuran aman.
- [ ] **Uji satu pembayaran sungguhan di produksi** setelah deploy, lalu cek
      kolom `kas` dan `transaksi_id` benar terisi.

## Multi-tenant (B1-F + pengerasan selesai; berikutnya D, prasyaratnya di bawah)

Visi: `AI_AGENT_MULTI_TENANT_ARCHITECTURE.md`. Peta fase + statusnya:
`.ai/AUDIT-MULTITENANT.md` bagian 10.

- [x] **B1 selesai 2026-08-15:** tabel `organizations` + `domains` + seed
      hierarki existing di migrasi `2026_08_15_000004`. Additive murni; 73 tes
      lama tetap hijau, 6 tes baru di `tests/Feature/OrganisasiTest.php`.
- [x] **B2 selesai 2026-08-15:** `TenantContext` (scoped) + middleware
      `ResolveTenant` di grup web. Hostname tak terdaftar/nonaktif → 404 tanpa
      fallback; `localhost`/`127.0.0.1` terdaftar resmi status `dev` (migrasi
      `2026_08_15_000005`). 9 tes di `TenantResolverTest`.
- [x] **C selesai 2026-08-15** (kecuali verifikasi MySQL): kolom
      `organization_id` + backfill di 12 tabel (migrasi `2026_08_15_000006`),
      trait `MilikOrganisasi` mengecap baris baru dari TenantContext dan
      menimpa kiriman client. 8 tes di `KepemilikanOrganisasiTest`.
- [x] **Verifikasi MySQL selesai 2026-08-15.** Seluruh 96 tes lulus di
      MariaDB 11.8.8 lokal (DB `paru_test`, user `paru_test`), termasuk
      `whereJsonContains` pada `transaksis.periode` yang lama tertunda, dan
      seluruh 40 migrasi naik + rollback 3 migrasi multi-tenant turun-naik
      bersih. Cara ulang: `DB_CONNECTION=mysql DB_HOST=127.0.0.1
      DB_DATABASE=paru_test DB_USERNAME=paru_test DB_PASSWORD=paru_test
      php artisan test`.
      CATATAN LINGKUNGAN: PHP mesin ini binary custom 8.4.23 di /usr/bin/php
      yang TIDAK dimiliki paket RPM mana pun, dengan modul di
      /usr/lib64/php/modules. JANGAN `dnf install php-*` di mesin ini: paket
      Fedora 44 berversi 8.5 dan menimpa modul 8.4 (sudah pernah terjadi dan
      dipulihkan dari RPM php 8.4.24 fc43). Driver pdo_mysql kini terpasang
      dari ekstraksi fc43.
- [x] **E1 selesai 2026-08-15:** katalog peran generik + `user_role_assignments`
      (migrasi `2026_08_15_000007`), CheckRole/userCan/helper izin membaca
      level efektif (assignment ber-scope, fallback `users.level`). Tabel
      `roles` lama di-rename ke `roles_legacy_pwa` - **drop manual setelah
      produksi dipastikan tidak menyimpan baris berharga.**
- [ ] **Pensiunkan fallback `users.level`** saat tenant kedua sungguhan
      dibuka: fallback membuat level lama berlaku di SEMUA tenant, aman
      selama single-tenant, bocor begitu multi. Sekalian: user BARU yang
      dibuat lewat Manajemen Akun belum otomatis dapat assignment (masih
      mengandalkan fallback) - AkunController perlu memasang assignment saat
      pembuatan akun sebelum fallback dicabut.
- [x] **E2 tahap 1 (jalur uang) selesai 2026-08-15:** global scope
      `ScopedKeOrganisasi` di `Transaksi` + `Keluarga`; findOrFail lintas
      tenant otomatis 404. 8 tes di `IsolasiTenantTest`.
- [x] **E2 tahap 2 selesai 2026-08-15:** seluruh 9 area sisa ber-scope, plus
      scope turunan untuk Anggota/IuranSampah/IuranPadaringan lewat subquery
      keluargas. 14 tes isolasi total di `IsolasiTenantTest`.
- [x] **Sisa kecil E2 selesai 2026-08-16:** `$user->level` mentah di
      Surat/AduanController + layout diganti `levelEfektif()`; pembaca
      `DB::table` (`removeDuplicates`, ranking RT laporan) pindah ke Eloquent
      ber-scope; `resetData` kini mass delete ber-scope dalam transaksi,
      bukan TRUNCATE lintas tenant. Sisa `$user->level` mentah tinggal
      rujukan hantu `sekretaris` (lihat "Belum dikerjakan").
- [ ] **Inkonsistensi warisan `keluarga_id`:** `anggotas.keluarga_id` berisi
      ID bisnis string, `iuran_*.keluarga_id` berisi id numerik keluargas.id
      (alur bayar menyimpan parameter rute). Scope turunan sudah sadar-kolom;
      normalkan saat ada kesempatan migrasi data yang tenang.
- [ ] **AppSetting & MPWA per-tenant adalah Phase F**, jangan dicampur ke E2.
- [ ] **Deploy berikutnya:** setelah migrasi B1+B2 masuk produksi, verifikasi
      `https://paru.jabnet.id` tetap 200 dan host asing/IP langsung ke server
      kini 404. Kalau operator perlu hostname tambahan (mis. akses via IP
      internal), daftarkan lewat tabel `domains`, bukan mengubah kode.
- [x] **F selesai 2026-08-16:** `app_settings` per organisasi (migrasi
      `2026_08_15_000008`), inheritance platform→desa→RW lewat
      `AppSetting::nilai()/semuaEfektif()/simpan()`, kredensial & template
      MPWA per tenant, feature flags `fitur_<modul>` di `userCan()`.
      6 tes di `PengaturanTenantTest`.
- [x] **Penjaga rute feature flag selesai 2026-08-16:** middleware
      `fitur:<modul>` (`PastikanFiturAktif`) membungkus seluruh blok rute
      modul; modul mati menjawab 404 konsisten dengan resource tenant lain.
      Rute modul BARU wajib ikut dibungkus (aturan di `AGENTS.md`).
- [ ] **Aturan wajib baca setting:** SELURUH pembacaan lewat
      `AppSetting::nilai()`/`semuaEfektif()`, penulisan lewat `simpan()`.
      Query `where('key')` polos tidak tahu inheritance dan bisa mengambil
      baris tenant lain - sudah nol di kode, jaga tetap nol.
- [ ] Fase G/H (manajemen tenant, impersonation ber-audit, queue, monitoring)
      sesuai bagian 10 audit.

## Belum dikerjakan

- [x] ~~Dokumentasi masih menyebut SukaWarga10.~~ Selesai 2026-08-15: `README.md`,
      `AGENTS.md`, `DEPLOY.md`, `.ai/HANDOFF.md`, dan `.env.example` sudah ikut.
- [ ] **Nama berkas aset dan nama repo masih "sukawarga".**
      `public/logo-sukawarga.svg`, `public/logo-sukawarga-icon.svg`, dan repo
      `kanggalon710/sukawarga10`. Isi logonya sudah generik ("Portal Desa"),
      hanya nama berkasnya yang belum. Mengganti nama berkas berarti menyentuh 5
      rujukan `asset()` sekaligus memastikan cache browser dan PWA tidak memegang
      yang lama, jadi dijadikan pekerjaan tersendiri.
- [ ] **Pindah domain ke `desa.jabnet.id`.** Langkah lengkap ada di `DEPLOY.md`
      bagian "Pindah domain". Penghambat: per 2026-08-15 `desa.jabnet.id` belum
      resolve, sedangkan `paru.jabnet.id` hidup di 103.194.47.165. Tidak ada
      perubahan kode yang diperlukan; setelah DNS jadi, cukup ubah `APP_URL` dan
      setting `alamat_portal` lewat menu Pengaturan.
- [ ] **`sukawarga10.jabnet.id` masih hidup** di IP berbeda (103.194.46.164),
      kemungkinan deployment lama. Pastikan tidak ada warga yang masih diarahkan
      ke sana; matikan atau redirect kalau sudah tidak dipakai. Pesan WhatsApp
      lama yang sudah terkirim masih memuat alamat itu.
- [ ] **Form Pengaturan belum ramah mobile.** Seluruh field memakai
      `grid-template-columns:1fr 1fr` inline (tetap 2 kolom di 360px), tinggi
      input sekitar 40px (di bawah 44px), dan `font-size:14px` yang memicu zoom
      otomatis iOS. Field baru "Identitas Aplikasi" sengaja mengikuti pola
      tetangganya supaya tidak belang; perbaikannya sekalian untuk satu halaman.
      Media query tidak bisa lewat style inline, jadi perlu dipindah ke
      `public/css/styles.css`.
- [ ] **Alamat bawaan impor masih "RW 10 Sukakarya".**
      `ExportImportController:314` memakainya sebagai alamat cadangan saat kolom
      Alamat kosong. Itu data, bukan merek, jadi tidak ikut diganti saat rename.
      Untuk project turunan, nilainya perlu ikut Pengaturan.
- [ ] **Gaya kode belum seragam.** `./vendor/bin/pint --test` melaporkan 43 berkas
      lama menyimpang dari preset Laravel. Sengaja tidak diformat ulang supaya
      diff perbaikan tetap terbaca. Kalau mau dibereskan, jalankan
      `./vendor/bin/pint` sebagai commit tersendiri yang isinya hanya format.
- [ ] **Notifikasi WA masih menahan request.** `notifyPengurus()` dan
      `notifyRT()` mengirim satu per satu secara sinkron, jadi request menunggu
      panggilan HTTP ke gateway. Belum dipindah ke queue karena produksi belum
      tentu menjalankan worker, dan job yang mengantre tanpa worker berarti
      notifikasi tidak pernah terkirim. Perlu keputusan: jalankan worker
      (`queue:work` + supervisor) lalu pindahkan pengiriman ke job.
- [ ] **Broadcast MPWA belum dipaginasi/dibatasi.** `MpwaController::broadcast`
      melakukan loop `usleep(300000)` per penerima di dalam request. Dengan 101 KK
      itu sekitar 30 detik. Kandidat kuat untuk dipindah ke background.
- [ ] **Pagination belum dipasang di semua daftar.** Sudah: Log Sistem, Data Warga.
      Belum: Surat, Aduan, UMKM, Kegiatan, Buku Kas, riwayat billing. Partial-nya
      sudah ada (`partials/pagination`), tinggal dipasang.
- [ ] **Level `sekretaris` adalah hantu.** Dirujuk di `SuratController::index`
      (`$isAdmin`) tapi tidak ada di hirarki `CheckRole` maupun
      `getDefaultPermissions()`. Tahap approval terakhir dikerjakan `superadmin`.
      Putuskan: hapus rujukannya, atau jadikan level sungguhan.
- [ ] **Belum diuji di MySQL.** Seluruh verifikasi memakai SQLite. Yang perlu
      diperhatikan khusus: kolom `transaksis.periode` bertipe `json`, dan
      `whereJsonContains` dipakai di tes.
- [ ] **UI baru sebagian dicek di 360/768/1280px.** Sudah: halaman login
      (dipotret lewat headless Chrome, bersih di ketiga lebar). Belum: navigasi
      pagination, kotak pencarian global yang kini disembunyikan untuk warga, dan
      blok Identitas Aplikasi di halaman Pengaturan.
      Caranya: `php artisan serve --port=8124`, lalu
      `google-chrome --headless --disable-gpu --no-sandbox --hide-scrollbars`
      `--screenshot=out.png --window-size=360,900 http://127.0.0.1:8124/<rute>`.
- [ ] **Cakupan tes masih di jalur utama.** Belum tertutup: importir CSV
      (`ImportAnggota`, `ImportPendataanKeluarga`), ekspor, alur approval surat
      bertingkat, dan `LaporanController`.
- [ ] **`ExportImportController` belum ditinjau** (488 baris, terbesar di repo).
      Menyentuh berkas unggahan dan data pribadi, jadi perlu ditinjau tersendiri.

## Ide yang sengaja ditolak

- **Memindahkan PIN seeder ke `.env`.** Sempat dipertimbangkan, tapi `DEPLOY.md`
  menyatakan tidak ada variabel `.env` baru, dan pemilik project meminta PIN
  tertentu. Risikonya dicatat di atas, bukan disembunyikan.
- **Memformat ulang seluruh repo dengan Pint.** Melanggar aturan "jangan
  memformat ulang baris yang tidak disentuh" dan akan menenggelamkan diff
  perbaikan keamanan di antara ribuan baris perubahan gaya.
