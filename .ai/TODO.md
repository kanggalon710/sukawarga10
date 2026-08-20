# TODO

Diperbarui 2026-08-15 setelah penyelarasan project dengan standar pengembangan.
Riwayat lengkap ada di `.ai/PROGRESS.md`.

---

## Wajib dilakukan saat deploy

Bukan pekerjaan kode, tapi jangan dilewat.

- [x] ~~Koreksi identitas tenant Bagendit di produksi.~~ Batal 2026-08-17:
      user mengonfirmasi identitasnya memang RW 07, Banyuresmi - tidak ada
      yang perlu dikoreksi. Cetak surat sudah diuji dan mengikuti identitas
      tenant dengan benar.
- [ ] **Sesudah deploy matriks kapabilitas (2026-08-18):** di TIAP portal,
      angkat satu akun jadi **Sekretaris RW** lewat Manajemen Akun, lalu
      jalankan `php artisan izin:periksa` sampai bersih. Tanpa sekretaris,
      surat hanya bisa dibuat akun superadmin. Perubahan perilaku yang perlu
      diumumkan ke pengurus: ketua tidak lagi membuat/menyunting surat (hanya
      menandatangani), bendahara kehilangan seluruh menu Surat, petugas RT
      kehilangan Impor/Ekspor CSV warga, Reset Data naik ke superadmin, dan
      menu Surat Menyurat kini tampil untuk warga (rutenya memang sudah
      terbuka sejak dulu, hanya menunya yang disembunyikan).
- [x] ~~**Tiap tenant baru perlu satu langkah manual di cPanel.**~~ SELESAI
      2026-08-20: sertifikat wildcard `*.desa.jabnet.id` terpasang, dan vhost
      wildcard melayani host yang belum pernah dibuat. Membuat desa atau RW
      baru kini cukup lewat Manajemen Desa, tanpa menyentuh cPanel sama sekali.
- [x] ~~**Sertifikat wildcard `*.desa.jabnet.id`.**~~ SELESAI 2026-08-20.
      Terbit lewat DNS-01 ke API PowerDNS, berlaku sampai 18 November 2026,
      diperpanjang cron acme.sh (`42 0,6,12,18 * * *`) dengan kaitan
      `~/bin/pasang-ssl-desa.py` yang memasang ulang ke vhost. Perpanjangan
      sudah diuji paksa dan terbukti mengganti sertifikat yang disajikan.
- [ ] **Hapus 7 subdomain per-tenant lewat antarmuka cPanel** (Domains ->
      Manage -> Remove): `sukakarya`, `bagendit`, `bagendit-rw07`, `cibunar`,
      `cibunar-rw01`, `ygao`, `ygao-rw01`, semuanya berakhiran
      `.desa.jabnet.id`. JANGAN hapus `desa.jabnet.id` dan `*.desa.jabnet.id`.
      Tidak bisa lewat skrip: akun ini tidak punya API penghapus subdomain
      (UAPI hanya `addsubdomain`, `cpapi2` mustahil karena binari
      `/usr/local/cpanel/cpanel` tidak ada). Aman dilakukan, sudah dibuktikan
      host tanpa vhost sendiri jatuh ke wildcard dengan TLS sah. Sesudahnya
      periksa satu portal lama, misalnya `https://cibunar-rw01.desa.jabnet.id`.
- [x] ~~**Tinjau `webserver-allow-from`.**~~ DIPUTUSKAN 2026-08-20: kedua
      rentang `160.236.18.0/23` dan `103.194.46.0/23` memang dikehendaki
      pemilik (keduanya jaringan ISP-nya sendiri). JANGAN dicabut "demi
      keamanan" di sesi berikutnya; alasannya di `.ai/DECISIONS.md`.
- [x] ~~**Ganti sandi SSH `arkanova`.**~~ DIPUTUSKAN pemilik 2026-08-20:
      tidak diganti. `sudo` di server DNS memakai sandi yang sama.
- [ ] **Pemilik memasang kunci SSH-nya sendiri di server DNS.** Sekarang hanya
      `arkanova` yang punya kunci, dan kunci itu ada di mesin agen. Selama
      pemilik belum punya kuncinya sendiri, `PasswordAuthentication` TIDAK
      BOLEH dimatikan, karena akan mengunci pemilik dari servernya. Urutannya:
      buat kunci di mesin sendiri (`ssh-keygen -t ed25519`), tambahkan kunci
      publiknya ke `~arkanova/.ssh/authorized_keys`, BUKTIKAN login berbasis
      kunci berhasil, baru pertimbangkan `PasswordAuthentication no`.
      Mendesak karena fail2ban di sana sudah memblokir 645 alamat dan
      sandinya tidak diganti.
- [ ] **Healthcheck container `powerdns_admin` salah sasaran.** Ia menembak
      `http://127.0.0.1/` (port 80) sementara aplikasi mendengarkan di 9191,
      jadi statusnya selamanya `unhealthy` dan sudah gagal 725 ribu kali.
      Akibatnya alarm palsu, dan yang lebih berbahaya: kalau nanti aplikasi
      benar-benar mati, tidak ada yang bisa membedakannya. Perbaiki
      `healthcheck.test` di compose-nya jadi menembak port 9191.
- [ ] **JANGAN deploy lewat cPanel Git Version Control.** Ia hanya menarik
      berkas: migrasi tidak jalan dan cache rute tidak dibangun ulang, dan
      situs 500. Pakai tombol Perbarui Sekarang (terverifikasi bekerja
      2026-08-18) atau SSH.
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
- [ ] **PII warga sungguhan ada di riwayat git PUBLIK.** Commit `fceb54c` memasukkan
      nama, No. KK, dan tanggal lahir warga RW 07 yang asli ke
      `tests/Feature/ImportTenantTest.php` sebagai contoh impor. Sudah diganti sintetis
      di working tree 2026-08-20, tapi **penggantian itu tidak menghapus jejaknya dari
      riwayat** - siapa pun bisa `git log -p` dan membacanya. Pemilik perlu memutuskan:
      (a) terima sebagai sudah terlanjur, seperti PIN `463696`; atau (b) tulis ulang
      riwayat (`git filter-repo`) lalu force push, yang memutus semua klon yang ada.
      Yang tidak boleh: menganggapnya beres hanya karena working tree sudah bersih.

- [ ] **Sisir tes dan seeder untuk contoh data warga lain.** Yang di atas ketemu tidak
      sengaja saat menulis tes Rilis 2. Belum ada yang memeriksa sisanya secara
      menyeluruh. Aturan yang berlaku sejak sekarang: contoh identitas di tes memakai
      kode wilayah `999999`, dan berkas berisi data warga tinggal di `data-lokal/`
      (lihat `data-lokal/README.md`).

- [ ] **Fitur pindah warga antar-RW/desa + cegah NIK ganda lintas tenant.** Rencana
      lengkap beserta koreksi hasil uji rancangan ada di
      `~/.claude/plans/starry-zooming-cloud.md`. Rilis 0 (`anggota_id`) dan Rilis 1
      (pemeriksa identitas + tiga penjaga) SUDAH selesai 2026-08-20.
      **Berikutnya Rilis 2: cek NIK LINTAS TENANT.** Yang wajib ada di sana, semuanya
      sudah dianalisis dan jangan ditemukan ulang: (a) pencarian lintas tenant HARUS
      mengecualikan `status = 'pindah'`, kalau tidak warga yang baru pindah tidak bisa
      disunting selamanya karena arsipnya sendiri dianggap duplikat; (b) pesannya hanya
      menyebut Desa/RW, tidak pernah nama, alamat, atau nomor HP; (c) importir CSV tidak
      pernah menyebut lokasi per baris - satu unggahan 1.000 NIK akan jadi peta alamat
      1.000 orang; (d) kuota harian per pengurus, sesudah habis pesannya jadi generik;
      (e) log pengungkapan menyimpan NIK ter-hash, bukan mentah.
      Yang sudah selesai di Rilis 1: normalisasi NIK/No.KK (16 digit, tolak
      notasi ilmiah), index pada `keluargas.nik`/`noKK` dan `anggotas.nik`, cek duplikat
      dalam tenant, plus tiga penjaga yang harus ada SEBELUM status `pindah` punya makna:
      validasi `status` di `KeluargaController::update` (hari ini nol validasi, dan
      dropdown sudah menyediakan "Pindah" sehingga persetujuan ketua desa bisa dilewati),
      guard `PengaturanController::removeDuplicates` (mengunci pada `nama|rt`, akan
      menghapus KK aktif yang namanya sama dengan arsip), dan guard
      `KeluargaController::destroy` (tidak menyentuh iuran/transaksi, jadi menghapus KK
      berarti membuat riwayat uang yatim tanpa error). Ketiganya SUDAH terpasang.

- [ ] **`AuditLogService::log()` tidak bisa menulis untuk organisasi lain.** `AuditLog`
      memakai `MilikOrganisasi`, yang mengisi `organization_id` dari `TenantContext::rw()`
      - dan di host desa/platform nilainya NULL, sehingga barisnya tidak muncul di Log
      Sistem RW mana pun. Diperparah `/log` sendiri 404 di host desa karena `log` tidak
      ada di whitelist `ResolveTenant`. Artinya setiap aksi lintas tenant yang dilakukan
      admin platform hari ini praktis tidak teraudit. Butuh `logUntuk(?int $orgId, ...)`.

- [ ] **`anggotas.keluarga_id` menyimpan string `K-...`, bukan `keluargas.id`.** Nama kolomnya
      berbohong: ia menunjuk `keluargas.keluarga_id`, bukan primary key. Query yang menyamakannya
      dengan id numerik mengembalikan nol baris TANPA error, jadi salahnya terlihat seperti "warga
      ini belum punya anggota keluarga". Sudah sekali menyesatkan analisis data (lihat PROGRESS
      2026-08-20). Sebelum ada relasi Eloquent yang jelas atau kolom ini dinamai ulang, siapa pun
      yang menulis query mentah ke `anggotas` akan mengulang jebakan yang sama.

- [ ] **Perbaikan data massal tidak punya jalur beraudit.** Koreksi RW 07 (2026-08-20) terpaksa
      dijalankan lewat skrip langsung ke database karena aplikasi tidak menyediakan cara
      menggabungkan dua catatan orang atau memindahkan anggota antar-KK. Akibatnya perubahan itu
      tidak masuk AuditLog. Butuh minimal: pindah anggota antar-KK lewat UI, dan hapus KK yang
      menolak jalan bila masih dirujuk iuran/transaksi/akun.

- [ ] **Laporan diam-diam menebak jenis kelamin kepala keluarga.**
      `LaporanController` memakai `$kk->jenisKelaminKK ?? 'L'`, jadi KK tanpa
      jenis kelamin dihitung laki-laki tanpa peringatan apa pun. Di RW 07 ada
      2 KK seperti itu dan setidaknya satu perempuan menurut NIK-nya, jadi
      pecahan L/P meleset diam-diam. Minimal: tampilkan hitungan "belum diisi"
      di halaman laporan, jangan dilebur ke salah satu sisi.
- [ ] **Belum ada halaman mutu data.** Audit RW 07 (2026-08-20) menemukan
      hal-hal yang seharusnya kelihatan sendiri oleh pengurus tanpa perlu
      dibedah manual: orang yang sama muncul di dua KK, No.KK/NIK bukan 16
      digit atau tersimpan dalam notasi ilmiah, dan KK yang belum punya satu
      pun anggota. Semuanya bisa dideteksi query sederhana. Pertimbangkan
      perintah `php artisan data:periksa` dan/atau satu tab di Laporan.
- [ ] **Importer menerima No.KK/NIK rusak tanpa protes.** `ImportAnggota`
      melewatkan referensi yang tidak cocok dan melaporkannya (bagus), tapi
      `ImportPendataanKeluarga` tetap menyimpan No.KK bernilai
      `3.205063190736E+016` apa adanya. Tolak atau normalkan di batas impor,
      karena setelah masuk DB ia jadi kunci relasi yang tidak bisa dicocokkan.
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

- [x] ~~Logo kop surat masih satu untuk semua tenant.~~ Selesai 2026-08-17:
      setting `kop_logo` per tenant (upload/hapus/reset di Pengaturan tab
      Info RW), kop cetak mengikuti. Favicon/sidebar/beranda tetap logo bawaan.
- [ ] **Template surat kustom per tenant (fase berikutnya, 2026-08-17).**
      Permintaan user: isi bawaan tiap jenis surat bisa diedit per tenant
      (placeholder {pemohon}, {keperluan}, dst.), bahkan jenis surat baru.
      Ditunda dari rilis logo/nomor/alamat_rw supaya rilis tetap kecil.
      Pola yang disepakati untuk ditiru: template WA broadcast
      (`mpwa_templates` JSON di AppSetting, MpwaController).
- [ ] **Index unik nomorSurat per tenant (2026-08-17).** Cek duplikat nomor
      saat ini hanya di aplikasi (closure validasi + guard di store());
      race dua request bersamaan masih bisa lolos. Index unik butuh bersih-
      bersih duplikat lama dulu (nomorSurat nullable, data lama mungkin
      kembar) supaya migrasi tidak gagal di produksi.
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
- [x] **Level `sekretaris` adalah hantu.** Selesai 2026-08-18: jadi peran
      sungguhan (`rw_secretary`) dan pemegang tahap cap surat.
- [x] **Pembersihan sisa hierarki lama selesai 2026-08-18:** `CheckRole` +
      alias `role:`, `User::LEVEL_POWER`, dan seluruh helper izin berbasis nama
      level (`isSuperAdmin`, `isKetuaRW`, `isBendahara`, `isPetugasRT`,
      `canVoid`, `canManageUsers`, `canManageFinance`) dihapus; `canVoid()` di
      Buku Kas dan TransaksiController diganti `bolehkah('transaksi.void')`.
      Baris `app_settings` ber-key `role_permissions` dibuang migrasi
      `2026_08_18_000002`. Dikunci `tests/Feature/PensiunHierarkiLamaTest.php`.
- [ ] **Kapabilitas per-USER (override individual)** belum ada; matriks masih
      per peran. Baru relevan kalau ada RW yang butuh pengecualian satu orang.
- [ ] **Scoping data per-RT** ("petugas RT hanya melihat warga RT-nya") masih
      terbuka: `warga.lihat` memberi akses SELURUH tenant. Itu row-level
      security (menyentuh global scope), bukan kapabilitas - rilis tersendiri.
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
