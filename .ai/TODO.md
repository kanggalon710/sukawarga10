# TODO

Diperbarui 2026-08-15 setelah penyelarasan project dengan standar pengembangan.
Riwayat lengkap ada di `.ai/PROGRESS.md`.

---

## Wajib dilakukan saat deploy

Bukan pekerjaan kode, tapi jangan dilewat.

- [ ] **Koreksi identitas tenant Bagendit di produksi (2026-08-17).** Lewat
      `desa.jabnet.id/tenant`: edit desa (kecamatan Warudoyong, kabupaten
      Kota Sukabumi) lalu ganti nomor RW 07 -> 10. Setelahnya buat subdomain
      `bagendit-rw10.desa.jabnet.id` di cPanel (docroot `public`) + AutoSSL.
      Alamat lama JANGAN dihapus dari cPanel - masih hidup sebagai alias.
- [ ] **Backup database produksi** sebelum `php artisan migrate --force`.
      Selain migrasi lama (`users.keluarga_id`, `transaksis.periode`, index),
      kini ada LIMA migrasi multi-tenant `2026_08_15_000004`-`000008`
      (organizations/domains, hostname dev, organization_id + backfill,
      peran + assignment + backfill, app_settings per org). Baca output
      backfill-nya - rincian di `DEPLOY.md` langkah 2.
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
- [ ] **Baca output backfill peran migrasi `2026_08_15_000007`.** Sejak
      fallback `users.level` dicabut, `petugas_rt` yang dilewati backfill
      (kolom `rt` kosong/tidak cocok organisasi RT) efektif jadi warga.
      Perbaikannya lewat Manajemen Akun: isi RT lalu simpan ulang levelnya.
      Uji juga login tiap level akun setelah deploy.

## Multi-tenant (B1-F + pengerasan + D selesai; berikutnya G/H)

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
- [x] **Fallback `users.level` pensiun 2026-08-16.** AkunController kini
      memasang/menyelaraskan/menghapus assignment (satu peran per tenant di
      subtree RW; petugas_rt di organisasi RT-nya, dibuat bila belum ada;
      assignment platform tak tersentuh form). `levelEfektif()` tanpa
      assignment = warga. Kolom `users.level` tinggal catatan tampilan &
      sasaran notifikasi. 7 tes di `ManajemenAkunTest`.
- [x] **Sasaran notifikasi WA per tenant selesai 2026-08-16:**
      `notifyPengurus()`/`notifyByLevel()`/`notifyRT()` menyasar pemegang
      assignment yang relevan dengan tenant request (leluhur + subtree);
      jalur konsol tetap berbasis kolom sampai fase queue (G). 5 tes di
      `NotifikasiTenantTest`. Broadcast MPWA sudah ter-scope lewat model
      Keluarga. **Seluruh prasyarat Phase D kini selesai** - tinggal
      menyediakan domain + organisasi tenant kedua sungguhan.
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
- [x] **Phase D selesai 2026-08-16:** perintah `tenant:buat` (desa + RW +
      domain + admin per RW, PIN acak sekali-tayang), skema subdomain flat
      `{label}-rw{nn}.desa.jabnet.id`, penjaga login lintas tenant
      (tolak + tunjukkan alamat RW asal), bocoran statistik halaman login
      ditutup. 12 tes baru (`BuatTenantTest`, `LoginTenantTest`); runbook di
      `DEPLOY.md` bagian 8. Operasional Cibunar: tinggal wildcard DNS
      `*.desa.jabnet.id` + jalankan perintah + cPanel per subdomain.
- [x] **G tahap 1 selesai 2026-08-16:** halaman "Manajemen Desa" (/tenant)
      untuk buka desa/RW dari browser, khusus `adalahAdminPlatform()`;
      logika satu sumber di `App\Services\PembukaTenant` (dipakai juga CLI
      `tenant:buat`). 6 tes di `ManajemenTenantTest`.
- [x] **G tahap 2 selesai 2026-08-16:** CRUD tenant (ubah nama desa,
      aktif/nonaktifkan RW, hapus RW kosong / desa tanpa RW) + safeguard
      duplikat nama+kecamatan; identitas bawaan netral "Portal Desa" +
      label tenant dinamis (`tenantSaatIni()`); sebutan MPWA → WA.
      9 tes baru; aturan di DECISIONS.
- [x] **G tahap 3 selesai 2026-08-16:** branch `production` (rilis portal)
      terpisah dari `main` (beku, 1-desa lama); halaman "Pembaruan Sistem"
      dengan notifikasi versi baru + update satu klik (pull ff-only +
      composer kondisional + migrate + cache), khusus admin platform.
      9 tes di `PembaruanTest`. Rilis = ff-merge dev → production.
- [ ] **Cek pembaruan terjadwal + `.cpanel.yml` (opsional, 2026-08-17).**
      Ditawarkan saat membuat update satu klik tapi belum dipilih user:
      (a) command `pembaruan:cek` terjadwal harian supaya badge muncul
      sendiri (butuh cron `schedule:run` di cPanel); (b) `.cpanel.yml`
      supaya "Deploy HEAD Commit" cPanel ikut migrate + rebuild cache.
      Selama belum ada, jalur cPanel "Update from Remote" JANGAN dipakai
      untuk update (tanpa migrate/cache) - pakai tombol di website.
- [ ] **G lanjutan:** integrasi API cPanel (buat subdomain + AutoSSL otomatis
      dari halaman Manajemen Desa) - HANYA perlu bila wildcard
      `*.desa.jabnet.id` ber-AutoSSL ternyata tidak didukung hosting; tunggu
      hasil uji pemilik. Butuh API token cPanel disimpan aman di server.
- [ ] Fase G/H sisanya (impersonation ber-audit, queue worker + antrian WA,
      monitoring) sesuai bagian 10 audit.

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
      bagian "Pindah domain". Update 2026-08-16: DNS SUDAH resolve ke IP yang
      sama dengan paru (103.194.47.165), tapi web server belum mengenal
      hostname-nya (HTTPS jatuh ke vhost default akun lain). Sisa pekerjaan:
      buat domainnya di cPanel (document root sama, folder `public`), AutoSSL,
      daftarkan hostname di tabel `domains` (kalau tidak: 404), lalu ubah
      `APP_URL` + setting `alamat_portal`. Tanpa perubahan kode.
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
