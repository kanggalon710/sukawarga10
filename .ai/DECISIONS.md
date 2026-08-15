# DECISIONS

Keputusan arsitektur: konteks, opsi, pilihan, alasan. Terbaru di atas.
Jangan menulis ulang entri lama; tambahkan entri koreksi.

## 2026-08-15 - Menambah .ai/HANDOFF.md di luar tiga berkas baku
**Konteks:** Standar global menetapkan `.ai/` berisi PROGRESS, TODO, dan
DECISIONS. Ketiganya bagus untuk riwayat, backlog, dan alasan, tapi tidak ada
yang menjawab "saya agent baru, apa keadaan sekarang dan apa yang boleh saya
percaya?" dalam sekali baca. PROGRESS sudah tumbuh jadi kronologi panjang.
**Opsi:** (a) taruh orientasi di kepala PROGRESS, (b) taruh di AGENTS.md,
(c) berkas tersendiri.
**Pilihan:** (c) `.ai/HANDOFF.md`, dirujuk dari AGENTS.md dan README.
**Alasan:** (a) membuat PROGRESS berhenti jadi kronologi murni dan entri
teratasnya harus ditulis ulang tiap kali, padahal aturannya entri lama tidak
boleh diubah. (b) mencampur *aturan* (stabil) dengan *keadaan* (berubah tiap
sesi), dan itu yang paling cepat membuat AGENTS.md jadi tidak dipercaya.
Pemisahan aturan/keadaan lebih penting daripada kepatuhan harfiah pada daftar
tiga berkas. Konsekuensinya: HANDOFF wajib ikut diperbarui saat keadaan berubah,
dan kewajiban itu ditulis di bagian akhir berkasnya sendiri.

## 2026-08-15 - Toolchain Vite/Tailwind dihapus (mengoreksi keputusan di bawah)
**Konteks:** Entri sebelumnya memutuskan membiarkan toolchain yang tidak terpakai
dan cukup mendokumentasikannya. Saat menyelaraskan project dengan standar,
keputusan itu ditinjau ulang.
**Opsi:** (a) tetap dibiarkan + didokumentasikan, (b) dihapus, (c) dipakai sungguhan.
**Pilihan:** (b) dihapus - `package.json`, `package-lock.json`, `vite.config.js`,
`resources/css/`, `resources/js/`, dan `welcome.blade.php` yang memanggil `@vite`.
Script `composer setup`/`composer dev` ikut disesuaikan agar tidak memanggil npm.
**Alasan:** Perkakas mati yang menyaru sebagai perkakas hidup adalah jebakan, dan
dokumentasi tidak menghilangkan jebakannya - cepat atau lambat ada yang menulis
class Tailwind lalu bingung kenapa tidak berpengaruh. `DEPLOY.md` sendiri sudah
menyatakan `npm build` tidak diperlukan. Kalau nanti benar-benar mau memakai
Tailwind, memasangnya kembali cuma satu perintah npm.

## 2026-08-15 - Kepemilikan data warga diikat lewat kunci, bukan nama
**Konteks:** `ProfilWargaController` mencari KK milik user dengan
`where('nama', $user->namaLengkap)->orWhere('noHP', $user->wa)`. Dua warga
bernama sama bisa saling membuka dan menghapus anggota keluarga orang lain.
**Opsi:** (a) pertajam pencocokan nama (tambah RT, tanggal lahir), (b) tambah
kolom `users.keluarga_id` sebagai kunci eksplisit, (c) biarkan dan beri peringatan.
**Pilihan:** (b), dengan backfill yang hanya menautkan kandidat tidak ambigu.
**Alasan:** Nama bukan identitas, dan mempertajam heuristik hanya mengecilkan
peluang salah, tidak menghilangkannya. Untuk data pribadi warga, gagal-tertutup
(akun ambigu diminta menghubungi admin RT) lebih baik daripada berhasil-menebak.

## 2026-08-15 - Notifikasi WA tetap sinkron, belum dipindah ke queue
**Konteks:** Standar melarang menahan jalur request dengan pekerjaan yang bisa
dilakukan setelah respons. Pengiriman WA ke pengurus dilakukan satu per satu di
dalam request.
**Opsi:** (a) pindahkan ke queue job sekarang, (b) biarkan sinkron dan catat.
**Pilihan:** (b) untuk sekarang, dicatat di `.ai/TODO.md`.
**Alasan:** `QUEUE_CONNECTION=database`, tapi belum ada bukti produksi menjalankan
worker, dan `DEPLOY.md` tidak menyebutkannya. Memindahkan ke queue tanpa worker
membuat notifikasi berhenti terkirim sama sekali - lebih buruk daripada request
yang lambat. Pemindahan harus satu paket dengan menyiapkan worker di server.

## 2026-08-15 - Zona waktu aplikasi diubah ke Asia/Jakarta
**Konteks:** `config/app.php` mengunci `'timezone' => 'UTC'`. Perhitungan
"bulan ini" untuk tagihan dan stempel waktu audit memakai zona aplikasi.
**Opsi:** (a) biarkan UTC, (b) Asia/Jakarta.
**Pilihan:** (b), lewat `env('APP_TIMEZONE', 'Asia/Jakarta')`.
**Alasan:** Sistem ini melayani satu RW di Garut. Dengan UTC, pembayaran yang
dicatat sebelum pukul 07.00 WIB tanggal 1 masih terhitung bulan sebelumnya -
salah tagih di batas bulan. Efek sampingnya: stempel waktu lama yang ditulis
sebagai UTC akan tampil bergeser 7 jam. Kolom `tanggal` bertipe date tidak
terpengaruh, jadi angka iuran aman. Trade-off ini dicatat di TODO deploy.

## 2026-08-15 - Vite/Tailwind dibiarkan terpasang tapi tidak dipakai (SUDAH DIKOREKSI)
> Dikoreksi oleh entri paling atas tertanggal sama. Dibiarkan di sini sebagai riwayat.

**Konteks:** `package.json`, `vite.config.js`, dan `resources/css/app.css`
mengonfigurasi Tailwind 4, tapi tidak ada Blade yang memanggil `@vite`. Styling
nyata datang dari `public/css/styles.css` (96 KB, plain CSS).
**Opsi:** (a) hapus toolchain Vite/Tailwind, (b) migrasikan Blade ke Tailwind,
(c) biarkan apa adanya dan dokumentasikan.
**Pilihan:** (c) untuk sekarang, didokumentasikan di `AGENTS.md`.
**Alasan:** Ini status quo yang diwarisi, bukan keputusan yang diambil sesi ini.
Menghapus atau memigrasikan keduanya pekerjaan besar yang butuh persetujuan
pemilik project. Yang berbahaya adalah ketidaktahuannya - agen berikutnya bisa
menulis class Tailwind yang diam-diam tidak pernah ter-compile. Itu yang ditutup
dengan dokumentasi. Usulan pembersihan ada di `.ai/TODO.md`.
