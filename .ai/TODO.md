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

## Belum dikerjakan

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
- [ ] **UI belum dicek di 360/768/1280px** setelah perubahan ini. Yang berubah
      secara visual hanya dua hal: navigasi pagination (baru) dan kotak pencarian
      global yang kini disembunyikan untuk level warga.
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
