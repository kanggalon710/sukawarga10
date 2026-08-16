# DECISIONS

Keputusan arsitektur: konteks, opsi, pilihan, alasan. Terbaru di atas.
Jangan menulis ulang entri lama; tambahkan entri koreksi.

## 2026-08-17 - Nomor RW & hostname bisa diganti; slug tetap beku
**Konteks:** Tenant Bagendit salah nomor RW + wilayah; keputusan
2026-08-16 menyatakan label/slug tak bisa diubah karena hostname terikat.
**Pilihan:** Revisi sebagian: hostname TIDAK lagi dianggap beku - resolver
membaca murni tabel domains (tanpa cache), jadi ganti nomor RW membuat
hostname primary baru dan menurunkan hostname lama jadi alias aktif
non-primary (alamat yang sudah dibagikan warga tetap hidup). Slug TETAP
beku: Organization::slugRt() membangun slug RT anak dari slug RW, dan
NotificationService/AkunController mencari org RT lewat slug itu -
mengubah slug memutus seluruh RT + assignment petugasnya. Konsekuensi:
slug lama (rw-07-bagendit) bisa tidak cocok dengan nama baru (RW 10);
itu diterima sebagai identifier historis, bukan tampilan. AppSetting
alamat_portal hanya dikoreksi bila masih menunjuk hostname lama - override
kustom milik tenant dihormati.

## 2026-08-16 - Cakupan kelola akun mengikuti tingkat HOST, bukan level user
**Konteks:** Owner minta kelola akun bertingkat; /akun lama menampilkan
semua user lintas tenant.
**Pilihan:** Hak kelola akun ditentukan pasangan (host, peran di rantai
leluhur host): platform=owner saja; desa=desa_admin/super_admin yang
assignment-nya di rantai leluhur desa (subtree sengaja TIDAK dihitung -
admin RW bukan pengelola desa); RW=superadmin efektif tenant. "Akun milik
cakupan" = ber-assignment di organisasi cakupan ATAU keluarganya tercatat
di sana; akun tanpa jangkar hanya terlihat owner. Level/RT hanya bisa
diubah di host RW karena sinkronisasi assignment butuh tenant RW - kolom
level tanpa assignment yang selaras adalah sumber kebingungan, bukan hak.
Matriks permission mengikuti organisasi host (platform=bawaan semua,
desa=bawaan RW-RW-nya, RW=lokal) lewat perbaikan target tulis
AppSetting::simpan. Kredensial diri sendiri lewat /akun-saya dengan
verifikasi PIN lama - pengurus tidak perlu tahu PIN siapa pun.

## 2026-08-16 - Domain root milik platform + fail-closed untuk host non-RW
**Konteks:** Insiden produksi: menghapus RW 10 lewat Manajemen Desa ikut
menghapus baris domain `desa.jabnet.id` (terdaftar milik org RW itu) dan
portal root 404.
**Pilihan:** (1) `desa.jabnet.id` = domain org PLATFORM; domain desa
`{slug}.desa.jabnet.id` milik org desa; domain RW milik org RW - domain mati
bersama organisasinya sendiri, tidak pernah bersama organisasi lain.
(2) Begitu host non-RW nyata, scope tenant TIDAK boleh fail-open: context
tanpa RW = `1 = 0`; agregat lintas tenant yang sah selalu
`withoutGlobalScope` eksplisit ber-komentar. (3) Pembatasan path host
non-RW ditegakkan DI `ResolveTenant`, bukan middleware terpisah: sorting
priority Laravel bisa mendahulukan `Authenticate` atas middleware kustom
(tamu di-redirect ke login alih-alih 404); `ResolveTenant` juga didaftarkan
ke priority list sebelum kontrak `AuthenticatesRequests`. (4) Halaman desa
publik hanya menampilkan ANGKA & tautan portal, tanpa data pribadi;
dashboard platform khusus `adalahAdminPlatform()`.

## 2026-08-16 - Branch production terpisah + update dari website
**Konteks:** `main` dipakai instalasi 1-desa khusus lama; portal multi-desa
butuh jalur rilis sendiri dan pemilik ingin update tanpa terminal.
**Pilihan:** (1) Branch `production` = satu-satunya sumber rilis portal;
`dev` = pengembangan; `main` dibekukan tanpa rewrite history (rilis
multi-desa berhenti mengalir ke sana - menulis ulang main lebih merusak
daripada membiarkannya). (2) Tombol update di website menjalankan shell
lewat Process dengan remote/branch DIKUNCI konstanta (tidak pernah dari
input), pull `--ff-only` saja (server yang berubah lokal = berhenti dengan
pesan, bukan merge diam-diam), composer hanya bila composer.lock berubah
(langkah termahal & paling rawan timeout), gagal = berhenti tanpa langkah
lanjutan, dan Cache::lock menahan klik ganda. Gerbangnya
`adalahAdminPlatform()` - admin tenant tidak boleh menyentuh kode server.
(3) Badge notifikasi hanya membaca cache (diisi saat "Periksa Pembaruan");
tidak ada fetch jaringan di jalur render halaman biasa.

## 2026-08-16 - Identitas bawaan netral + aturan CRUD tenant
**Konteks:** Multi-desa hidup; bawaan kode masih "Kampung Paru", dan
Manajemen Desa butuh aturan ubah/hapus.
**Pilihan:** (1) Bawaan identitas jadi "Portal Desa" - identitas bermerek
milik baris app_settings tenant, bukan kode; konsekuensi untuk instalasi
paru lama dicatat di PROGRESS. (2) Label/slug desa TIDAK bisa diubah:
hostname seluruh RW-nya dibangun dari label, mengubahnya mematikan semua
alamat yang sudah dibagikan warga - ganti nama tampilan boleh, ganti label
berarti buka desa baru + migrasi. (3) Hapus RW hanya bila kosong dari 10
model data tenant; kalau berisi, jalannya nonaktifkan (resolver menolak
domainnya) - data uang warga tidak boleh lenyap lewat satu tombol. Akun
admin RW yang dihapus dibiarkan hidup tanpa assignment (efektif warga):
menghapus akun orang bukan urusan tombol hapus organisasi. (4) Duplikat
desa = nama+kecamatan sama (case-insensitive) di label mana pun; label
terpakai dengan nama berbeda juga ditolak supaya salah ketik tidak
menempelkan RW ke desa lain.

## 2026-08-16 - Phase D: subdomain flat per RW + login tolak-dengan-alamat
**Konteks:** Membuka multi-desa (dua "Desa Cibunar" berbeda kecamatan) dengan
syarat warga hanya login di subdomain RW-nya; alamat harus ramah orang tua.
**Opsi:** (a) subdomain flat `cibunar-rw01.desa.jabnet.id`, (b) bersarang
`rw01.cibunar.desa.jabnet.id`, (c) langsung `cibunar01.jabnet.id`; untuk
login salah alamat: tolak+alamat benar / tolak generik / biarkan kosong.
**Pilihan (disetujui pemilik):** (a) flat, label pembeda `cibunar` vs
`cibunarkota`, login ditolak dengan pesan yang menyebutkan alamat RW asal.
**Alasan:** Flat = satu wildcard DNS `*.desa.jabnet.id` menutup semua tenant
selamanya (wildcard hanya berlaku satu tingkat; skema bersarang butuh wildcard
per desa), alamat lebih pendek, AutoSSL cPanel langsung jalan. Pesan penolakan
menyebut alamat benar hanya SETELAH PIN valid, jadi bukan kebocoran
keanggotaan - justru membimbing warga lansia yang salah ketik. Akun tanpa
jangkar (keluarga/assignment) sengaja tidak dikunci: mengunci akun legacy
dari semua pintu lebih buruk daripada membiarkan perilaku lama. PIN admin RW
dari `tenant:buat` acak dan hanya dicetak sekali - tidak ada lagi PIN bawaan
yang tercatat di git.

## 2026-08-16 - Superadmin dari Manajemen Akun ber-scope tenant, bukan platform
**Konteks:** Fallback `users.level` dicabut, jadi AkunController wajib
memasang assignment. Untuk level `superadmin`, assignment-nya bisa dipasang
di organisasi platform (kuasa semua tenant) atau di RW tenant request.
**Opsi:** (a) platform, meniru makna lama "superadmin = operator penuh";
(b) RW tenant.
**Pilihan:** (b). Assignment platform hanya lewat seeder/konsol, dan form
tenant tidak pernah menyentuh assignment di luar subtree RW-nya.
**Alasan:** Dengan (a), admin satu RW bisa mencetak admin lintas platform
dari form biasa - eskalasi hak antar tenant. Dengan (b), "superadmin" buatan
form berkuasa penuh DI TENANT ITU saja; efeknya identik selama single-tenant.
Konsekuensi lain yang diputuskan sekalian: satu peran per tenant per user
(form mengganti, tidak menumpuk); akun warga tidak diberi baris assignment
(lantai default `levelEfektif()`); lookup peran dari level lama
didisambiguasi `scope_type` karena `legacy_level` 'ketua_rw' dimiliki dua
peran (desa_admin & rw_admin).

## 2026-08-16 - Modul yang dimatikan dijawab 404, dan reset data mengikuti scope
**Konteks:** Penjaga rute feature flag butuh kode respons, dan `resetData`
harus berhenti TRUNCATE lintas tenant.
**Opsi:** (a) 403 "tidak berhak" vs (b) 404 "tidak ada" untuk modul mati;
(x) DELETE ber-scope vs (y) TRUNCATE + where manual untuk reset.
**Pilihan:** (b) dan (x).
**Alasan:** Bagi tenant yang modulnya dimatikan, modul itu memang tidak ada -
sama seperti resource tenant lain yang juga 404 (405/403 membocorkan
keberadaan). Untuk reset: DELETE lewat model ber-scope otomatis terbatas ke
tenant request dan bisa dibungkus transaksi (TRUNCATE memicu implicit commit).
Konsekuensi yang diterima: (1) auto-increment tidak ter-reset - tidak ada
yang bergantung padanya; (2) baris warisan ber-organization_id NULL tidak
ikut terhapus - itu bukan milik tenant mana pun, dan di produksi backfill
migrasi 000006 sudah mengisi semuanya; (3) `level_label` dipecah dua accessor
(mentah vs efektif) karena label efektif di loop daftar akun berarti satu
query assignment per baris (N+1).

## 2026-08-15 - Phase E2: isolasi tenant lewat global scope opt-in, bukan where manual
**Konteks:** Jalur uang memakai `findOrFail` polos; §21 menuntut setiap query
resource tenant menjawab scope-nya.
**Opsi:** (a) sisipkan `where('organization_id',...)` manual di tiap query
per controller, (b) global scope otomatis di SEMUA 12 model sekaligus lewat
MilikOrganisasi, (c) global scope di trait terpisah `ScopedKeOrganisasi`,
dipasang model demi model bersama tes isolasinya.
**Pilihan:** (c), dimulai dari `Transaksi` + `Keluarga`.
**Alasan:** (a) adalah pola yang pasti bolong satu tempat dan tidak melindungi
query baru - persis kelas bug yang §21 peringatkan. (b) meledakkan blast
radius: `User` TIDAK boleh di-scope baca (login mencari username lintas
tenant, dan akun seeder ber-organization_id null akan lenyap), `AuditLog`
level platform juga bukan kandidat. Dengan (c), tiap area diaktifkan sadar,
bersama tes isolasinya, dan model yang memang tidak boleh tersaring tidak
pernah tersentuh. Konsekuensi yang diterima: sampai semua area terpasang,
model yang belum ber-scope masih polos - daftarnya eksplisit di TODO, bukan
diasumsikan aman.

## 2026-08-15 - Phase E1: tabel roles lama di-rename, bukan drop; legacy_level sebagai jembatan
**Konteks:** E1 butuh katalog peran baru. Tabel `roles` era PWA (rtFilter,
readOnly, permissions json) mati total di kode tapi mungkin masih menyimpan
baris di produksi. Pemilik project menyetujui rekomendasi "jangan pakai ulang".
**Keputusan:**
1. **Rename ke `roles_legacy_pwa`, bukan DROP.** Nol kehilangan data; drop
   manual setelah produksi dipastikan kosong. `down()` mengembalikan nama.
2. **Kolom `roles.legacy_level`** memetakan tiap peran generik ke level lama,
   sehingga CheckRole cukup menerjemahkan assignment → level → hierarki yang
   sudah teruji. Alternatifnya (sistem permission granular penuh, §11) berarti
   menulis ulang seluruh pengecekan sekaligus - terlalu besar untuk satu fase
   dan tidak bisa dirilis bertahap. Kolom ini mati bersama `users.level` saat
   fallback dipensiunkan.
3. **Fallback ke `users.level`** bila user tanpa assignment relevan: membuat
   E1 nol-perubahan-perilaku untuk semua akun existing dan akun baru. Bahaya
   yang disadari: fallback berlaku lintas tenant, jadi WAJIB dicabut sebelum
   tenant kedua dibuka (tercatat di TODO dengan prasyaratnya).
4. **Helper izin User (isSuperAdmin dst) ikut membaca level efektif.** Tanpa
   ini assignment lolos middleware tapi ditolak cek lapis kedua di controller
   (AkunController) - ditemukan lewat tes, bukan tebakan.
5. **Memo level efektif dititip di TenantContext** (scoped per request), bukan
   di instance model: instance user hidup melintasi request dalam proses tes
   dan memonya membuat pengukuran query request kedua salah.

## 2026-08-15 - Phase C: cap organization_id lewat model event, bukan edit 11 controller
**Konteks:** Setelah kolom `organization_id` ada, setiap insert baru harus
mengisinya. 10 dari 12 model tenant ber-`$guarded = []` sehingga nilai dari
form bisa ikut masuk ke create().
**Opsi:** (a) edit setiap pemanggilan create di 11 controller, (b) default di
level DB, (c) satu trait dengan hook `creating`.
**Pilihan:** (c) trait `MilikOrganisasi`, dengan aturan: context request ada →
SELALU dicap dari context dan MENIMPA kiriman client; tanpa context → nilai
eksplisit dihormati, fallback ke satu-satunya RW aktif, dan berhenti menebak
begitu RW lebih dari satu.
**Alasan:** (a) diff raksasa yang pasti bolong satu-dua tempat dan tidak
melindungi pemanggil baru; (b) FK default tidak bisa dinamis per tenant.
Penimpaan paksa saat ada context adalah keputusan keamanannya: client tidak
pernah menentukan tenant, dan tanpa itu `$guarded = []` jadi jalur suntikan.
Konsekuensi yang diterima: alur "platform staff membuat data untuk tenant
lain lewat HTTP" belum bisa - memang belum ada, dan kalau nanti ada harus
lewat jalur eksplisit yang ber-otorisasi, bukan lewat form field.
Tanpa FK constraint DB pada 12 kolom baru: SQLite tidak bisa menambah FK ke
tabel existing, dan constraint yang hanya hidup di MySQL berarti dev dan
produksi berperilaku beda; integritas dijaga trait + tes (preseden
`users.keluarga_id`).

## 2026-08-15 - Phase B2: localhost jadi domain terdaftar, bukan pengecualian kode
**Konteks:** Resolver menolak hostname tak terdaftar dengan 404 (§14). Suite
tes dan `artisan serve` memakai `localhost`, jadi harus ada jalan masuk dev.
**Opsi:** (a) if `app()->environment('local')` lewati resolver, (b) daftar
pengecualian hostname di config, (c) daftarkan `localhost`/`127.0.0.1` sebagai
baris `domains` berstatus `dev` lewat migrasi.
**Pilihan:** (c).
**Alasan:** (a) dan (b) membuat jalur kode berbeda antara dev dan produksi,
persis kelas bug yang paling jarang tertangkap tes (tesnya sendiri jalan di
jalur pengecualian). Dengan (c), dev dan produksi melewati resolver yang sama
persis, dan kalau operator butuh hostname tambahan (akses via IP internal),
mereka menambah baris tabel, bukan mengubah kode. Konsekuensi kecil yang
diterima: baris localhost ikut ada di tabel produksi, terdokumentasi lewat
status `dev`.

## 2026-08-15 - Phase B1: bentuk tabel organizations/domains dan cara mengisinya
**Konteks:** Implementasi multi-tenant dimulai dari B1 (audit bagian 10). Empat
keputusan bentuk yang menetapkan preseden untuk fase berikutnya.
**Keputusan:**
1. **Tabel infrastruktur platform memakai Inggris snake_case + FK numerik**
   (`organizations.parent_id`), BUKAN pola domain (kolom Indonesia camelCase +
   ID bisnis string `prefix-uniqid`). Batasnya: tabel data warga tetap pola
   lama, tabel infrastruktur platform mengikuti dokumen arsitektur. Alasan: ID
   bisnis string lahir dari warisan PWA yang tidak berlaku untuk tabel baru,
   dan `organization_id` akan dirujuk banyak tabel sehingga FK numerik asli
   Laravel adalah jalur paling aman.
2. **Seed hierarki DI DALAM migrasi, bukan DatabaseSeeder.** `DEPLOY.md`
   menjadikan `db:seed` opsional di produksi; hierarki yang dititip di seeder
   bisa tidak pernah ada di produksi dan fase berikutnya jatuh. Preseden:
   backfill `users.keluarga_id`.
3. **RT diderivasi dari data nyata** (`keluargas.rt` union `users.rt`,
   dinormalisasi ke dua digit sehingga '1' dan '01' tidak jadi kembar), bukan
   daftar tebakan. Diverifikasi dengan data uji: 01+1+03+05 menghasilkan 3 RT.
4. **`desa.jabnet.id` SENGAJA tidak dimasukkan ke `domains`.** DNS-nya belum
   ada, dan di arsitektur target belum diputuskan hostname itu milik level desa
   atau sekadar nama baru portal RW 10. Memasukkannya sekarang berarti menebak
   pemetaan tenant. `sukawarga10.jabnet.id` justru masuk (status `legacy`,
   non-primary) supaya resolver B2 mengarahkannya ke RW 10, bukan 404.
**Ditunda secara eksplisit:** nasib tabel `roles` mati baru relevan di E1
(audit sempat menyebutnya prasyarat B1; itu terlalu ketat karena B1 tidak
menyentuhnya). Uji MySQL tetap prasyarat sebelum fase yang mengubah index/JSON;
belum bisa dijalankan di mesin ini karena PHP tanpa `pdo_mysql` (butuh root).

## 2026-08-15 - Alamat portal jadi setting sendiri, bukan turunan APP_URL
**Konteks:** Link yang dikirim ke warga lewat WhatsApp ditulis tetap sebagai
`sukawarga10.jabnet.id`, padahal produksi sudah pindah ke `paru.jabnet.id` dan
rencananya pindah lagi ke `desa.jabnet.id`.
**Opsi:** (a) ganti literalnya jadi `desa.jabnet.id`, (b) turunkan dari
`config('app.url')`, (c) setting sendiri `alamat_portal`.
**Pilihan:** (c), bawaan `paru.jabnet.id`.
**Alasan:** (a) mengirim warga ke alamat mati, karena `desa.jabnet.id` belum
resolve per hari ini (dicek, bukan diasumsikan). (b) kelihatan paling rapi tapi
paling rapuh: di mesin lokal `APP_URL` bernilai `http://localhost:8000`, dan satu
kesalahan setel di produksi langsung berubah jadi link rusak di pesan ke ratusan
warga, tanpa gejala di layar mana pun. (c) memisahkan "alamat yang dipakai
framework" dari "alamat yang dibaca manusia di WhatsApp", dan membuat pindah
domain jadi satu isian form. Nilainya divalidasi sebagai nama host supaya tidak
ada yang mengisi `https://...` lalu menghasilkan link ganda skema.
**Konsekuensi:** ada dua tempat yang harus diubah saat pindah domain (`APP_URL`
dan setting ini). Itu disengaja, dan urutannya ditulis di `DEPLOY.md`.

## 2026-08-15 - Identitas aplikasi jadi data (app_settings), bukan literal di kode
**Konteks:** Permintaan mengganti nama SukaWarga10 jadi Kampung Paru. Namanya
tertulis tetap di 8 berkas (layout, login, manifest, logo, 2 controller, service
WA, CSS), dan lokasi "RW 10 Sukakarya, Garut" muncul 12 kali di `MpwaService`
saja. Pertanyaan pemilik project di percakapan yang sama: apakah project turunan
untuk kampung lain terbentuk otomatis.
**Opsi:** (a) cari-ganti seluruh literal jadi "Kampung Paru", (b) tarik nama,
tagline, dan lokasi ke `app_settings` lalu baca lewat helper, (c) baca dari
`config/app.php` + `.env`.
**Pilihan:** (b), tiga key: `nama_aplikasi`, `tagline_aplikasi`, `lokasi_singkat`.
**Alasan:** (a) menyelesaikan permintaan hari ini tapi mengulang masalahnya
persis saat nama berubah lagi, dan pertanyaan soal project turunan menunjukkan itu
akan terjadi. (c) menaruh identitas di berkas yang hanya bisa diubah lewat SSH,
padahal pengurus RW sudah punya menu Pengaturan untuk nama RW dan ketua RW; nama
aplikasi masuk kategori yang sama. Konsekuensinya: satu query tambahan per
request (diingat per container), dan konstanta `MpwaService::FOOTER` serta
`MpwaController::DEFAULT_TEMPLATES` harus jadi method karena konstanta PHP tidak
boleh memanggil fungsi.

**Catatan tentang badge lokasi:** pemilik project memilih baris lokasi diganti
"Kampung Paru saja". Diisi bawaan `Garut, Jawa Barat` karena h1 dan tagline di
atasnya sudah menyebut Kampung Paru, sehingga persis "Kampung Paru" membuat nama
itu muncul tiga kali dalam empat baris. Ini nilai bawaan, bukan keputusan yang
dikunci: ubah di Pengaturan tanpa menyentuh kode.

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
