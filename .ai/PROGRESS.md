# PROGRESS

Catatan pekerjaan, terbaru di atas. Jelaskan KENAPA, bukan APA (git sudah mencatat apa).

## 2026-08-16 - Fallback users.level pensiun; Manajemen Akun memelihara assignment
**Agen:** claude | **Status:** selesai
**Kenapa:** Prasyarat terakhir Phase D: fallback membuat kolom level lama
berlaku di SEMUA tenant - aman selama single-tenant, bocor begitu tenant kedua
hidup. Fallback tidak bisa dicabut sebelum AkunController memasang assignment,
karena akun baru dari Manajemen Akun akan lahir tanpa hak.
**Perubahan:**
- `AkunController::store/update` memanggil `selaraskanAssignment()`: satu
  peran per tenant di subtree RW request (yang lama diganti), petugas_rt
  ditempatkan di organisasi RT-nya (dibuat bila belum ada, normalisasi sama
  dengan seed B1), assignment platform tidak pernah disentuh form tenant.
  `destroy` ikut menghapus assignment (tabel tanpa FK cascade). Validasi baru:
  `rt` wajib bila level petugas_rt.
- `User::levelEfektif()`: `?? $this->level` dicabut; tanpa assignment relevan,
  lantainya warga. Kolom `users.level` tinggal catatan tampilan & sasaran
  notifikasi.
- `Organization::idSubtree()` diekstrak (dipakai AkunController dan
  `levelEfektifUntuk` tanpa query tambahan).
- Temuan saat implementasi: lookup peran via `legacy_level` ambigu
  (desa_admin & rw_admin sama-sama 'ketua_rw') - didisambiguasi lewat
  `scope_type` organisasi sasaran.
- Fixture tes pengurus kini lewat `TestCase::pasangPeranSetaraLevel()`;
  pesan peringatan backfill E1 diperbarui (migrasi belum pernah jalan di
  produksi).
**File:** app/Http/Controllers/AkunController.php, app/Models/User.php,
app/Models/Organization.php, app/Http/Middleware/CheckRole.php,
app/helpers.php, database/migrations/2026_08_15_000007, tests/TestCase.php,
tests/Feature/ManajemenAkunTest.php (baru, 7 tes), 6 file tes lain
**Catatan:** 139 tes (333 assertion) hijau di SQLite dan MariaDB 11.8.8.
Sasaran notifikasi WA masih membaca kolom level lintas tenant - tercatat
sebagai prasyarat D tersisa di TODO.

## 2026-08-16 - Pengerasan pra-tenant-kedua: sisa E2 + penjaga rute feature flag
**Agen:** claude | **Status:** selesai
**Kenapa:** Empat lubang yang tercatat di TODO harus tertutup sebelum Phase D
(tenant kedua) aman dibuka: `resetData` masih TRUNCATE lintas tenant,
`removeDuplicates` dan ranking laporan membaca `DB::table` (tak tersaring
scope), beberapa controller/view masih membaca `users.level` mentah (hak dari
assignment tidak pernah tampil), dan feature flag baru menyembunyikan menu
tanpa menjaga rutenya.
**Perubahan:**
- Middleware `fitur:<modul>` (`PastikanFiturAktif`): modul yang dimatikan
  (`fitur_<modul> = 0`) kini 404 di rutenya, bukan cuma hilang dari sidebar.
  Seluruh blok rute modul di `routes/web.php` dibungkus per menu key.
- `resetData` diganti dari TRUNCATE ke mass delete Eloquent ber-scope: hanya
  data tenant request yang terhapus, kini juga terbungkus transaksi.
  `removeDuplicates` dan ranking RT laporan pindah ke Eloquent.
- `SuratController`, `AduanController`, layout sidebar/header memakai
  `levelEfektif()`; `level_label` dipecah dua accessor (mentah untuk daftar
  Manajemen Akun agar tidak N+1, efektif untuk user login).
- Temuan tes: fixture `trxAsing` di `IsolasiTenantTest` selama ini
  ber-organization_id NULL karena `Transaksi` ber-`$fillable` membuang kiriman
  `organization_id`; diperbaiki lewat penetapan properti langsung.
**File:** app/Http/Middleware/PastikanFiturAktif.php, routes/web.php,
app/Http/Controllers/{Pengaturan,Laporan,Surat,Aduan}Controller.php,
app/Models/User.php, app/helpers.php, resources/views/layouts/app.blade.php,
bootstrap/app.php, tests/Feature/{PeranScope,IsolasiTenant,PengaturanTenant}Test.php
**Catatan:** 132 tes (301 assertion) hijau di SQLite dan MariaDB 11.8.8.
Fallback `users.level` sendiri BELUM dipensiunkan (tetap prasyarat tenant
kedua, butuh AkunController memasang assignment saat pembuatan akun).

## 2026-08-16 - Phase F: app_settings per organisasi + inheritance + feature flags
**Agen:** claude | **Status:** selesai
**Kenapa:** Blocker skema terakhir dari audit (unique `app_settings.key`
mengunci satu nilai untuk seluruh platform), dan §37 menuntut branding tenant A
tidak mengubah tenant B. Tanpa ini, tenant kedua MEWARISI kunci API WhatsApp
dan identitas RW 10.
**Perubahan:**
- Migrasi `2026_08_15_000008`: kolom `organization_id` nullable di
  `app_settings`, backfill SEMUA baris existing ke RW 10 (karena memang diisi
  pengurus RW 10 - membiarkannya NULL berarti tenant baru mewarisi kredensial
  RW 10), lalu `unique(key)` diganti `unique(organization_id, key)`. `down()`
  membersihkan duplikat lintas org sebelum mengembalikan unique lama, diuji
  dengan baris duplikat hidup di MySQL.
- `AppSetting::nilai()/semuaEfektif()/simpan()`: SELURUH pembacaan setting kini
  satu query per request (memo di TenantContext) dengan inheritance
  platform (NULL) → desa → RW, yang terdekat menang; penulisan selalu di
  organisasi tenant. 21 titik baca/tulis lama dikonversi (helpers, MpwaService,
  MpwaController, Pengaturan/Akun/Transaksi/Dashboard/SuratController).
- `identitasAplikasi()` tidak lagi pakai cache container sendiri; ikut memo
  setting per request, jadi otomatis per-tenant.
- Feature flags §18: helper `fiturAktif()` (setting `fitur_<modul>`, tanpa
  baris = aktif) dicek paling awal di `userCan()` - mematikan modul untuk
  SEMUA level termasuk admin, karena ini ketersediaan modul, bukan izin.
  Kejujuran cakupan: baru menu yang hilang; penjagaan rute per fitur belum.
- TenantContext dapat memo generik `ingat()/lupakan()` + `rantaiLeluhurIds()`
  (satu query peta induk, dipakai resolver setting).
- 6 tes `tests/Feature/PengaturanTenantTest.php`: identitas per hostname,
  inheritance + penimpaan, tarif tenant lain tidak mempengaruhi pembayaran,
  form menulis di org tenant tanpa menimpa default platform, flag mematikan
  modul untuk admin, flag tenant lain tidak berlaku di sini.
**File:** database/migrations/2026_08_15_000008_scope_app_settings_to_organizations.php,
app/Models/AppSetting.php, app/Services/TenantContext.php, app/helpers.php,
app/Http/Controllers/{Pengaturan,Mpwa,Akun,Transaksi,Dashboard,Surat}Controller.php,
app/Services/MpwaService.php, tests/Feature/PengaturanTenantTest.php
**Catatan:** 124 tes lulus di SQLite DAN MariaDB 11.8.8; migrasi diuji
turun-naik di MySQL termasuk kasus duplikat lintas org saat down. Diketahui
dan diterima: unique komposit MySQL mengizinkan beberapa baris NULL per key
(keunikan default platform dijaga jalur tulis `simpan()`, bukan constraint).
Pint: berkas baru pass, 8 berkas lama yang disentuh identik baseline HEAD.

## 2026-08-15 - Phase E2 tahap 2: isolasi tenant seluruh area data
**Agen:** claude | **Status:** selesai
**Kenapa:** Melengkapi tahap 1. Sisa area (surat, aduan, umkm, kegiatan,
pengeluaran, sumbangan, setor, pendaftaran, log) masih polos, dan model
turunan keluarga (anggota, iuran) belum tersaring sendiri.
**Perubahan:**
- `ScopedKeOrganisasi` dipasang di 9 model sisa: Surat, Aduan, Umkm, Kegiatan,
  Pengeluaran, Sumbangan, SetorSampah, Pendaftaran, AuditLog. Bonus alami:
  penomoran surat (`max(nomorUrut)`) kini per tenant, dan badge pendaftaran
  di view composer ikut tersaring.
- Trait baru `ScopedKeOrganisasiViaKeluarga` untuk model tanpa kolom org yang
  kepemilikannya lewat keluarga: Anggota, IuranSampah, IuranPadaringan.
  Saringannya subquery ke keluargas yang sudah ber-scope (satu query, bukan
  whereHas per baris).
- TEMUAN WARISAN dari tes: `anggotas.keluarga_id` berisi ID bisnis string
  (kk_...), tapi `iuran_*.keluarga_id` berisi id NUMERIK keluargas.id (alur
  bayar menyimpan parameter rute apa adanya). Trait turunan dibuat
  sadar-kolom (`kolomRujukanKeluarga()`, model iuran meng-override ke 'id').
  Inkonsistensi ini dicatat di TODO untuk dinormalkan suatu saat.
- 6 tes isolasi baru di `IsolasiTenantTest` (total 14): surat asing
  (list/show/approve/hapus), penomoran surat independen antar tenant, aduan/
  umkm/kegiatan asing, pendaftaran asing (list + approve), log asing, dan
  anggota asing tersaring lewat keluarganya.
**File:** app/Models/Concerns/ScopedKeOrganisasiViaKeluarga.php, 12 model di
app/Models/, tests/Feature/IsolasiTenantTest.php
**Catatan:** 118 tes lulus di SQLite DAN MariaDB 11.8.8. Yang SENGAJA tidak
di-scope: `User` (login mencari username lintas tenant; pembatasan Manajemen
Akun per tenant adalah pekerjaan platform berikutnya) dan `Organization`/
`Domain`/`Role`/`UserRoleAssignment` (tabel infrastruktur). `DB::table` di
removeDuplicates/resetData masih polos - sudah di TODO sejak tahap 1.

## 2026-08-15 - Phase E2 tahap 1: isolasi tenant jalur uang (global scope)
**Agen:** claude | **Status:** selesai
**Kenapa:** Query jalur uang memakai `findOrFail` polos (bayar sampah/
padaringan, void) - persis pola IDOR §21 begitu tenant kedua ada. Menyisipkan
`where` manual per query adalah pola yang paling gampang bolong, jadi dipilih
global scope opt-in per model.
**Perubahan:**
- Trait `ScopedKeOrganisasi`: global scope membaca TenantContext, menyaring
  ke `organization_id` RW tenant; di konsol (context kosong) tidak menyaring
  supaya importer tetap bekerja. Nama tabel dikualifikasi untuk join.
- Dipasang di `Transaksi` dan `Keluarga`. Efeknya menjalar ke SEMUA pembaca
  kedua model itu (billing, buku kas, dashboard, laporan, warga) termasuk
  `findOrFail`: baris tenant lain otomatis 404.
- `IuranSampah`/`IuranPadaringan` belum di-scope (tidak punya kolom org;
  scope-nya turunan keluarga). Aman transitif: tampilannya selalu di-join ke
  keluargas yang tersaring, dan jalur tulisnya lewat `Keluarga::findOrFail`
  yang tersaring. Dicatat sebagai penyempurnaan E2 berikutnya.
- 8 tes `tests/Feature/IsolasiTenantTest.php`: daftar warga/billing/buku kas
  bersih dari milik tenant lain, IDOR by-id 404, bayar untuk KK asing 404
  tanpa transaksi tercipta, void transaksi asing 404 tanpa perubahan, konsol
  tetap melihat semua, escape hatch `withoutGlobalScope` eksplisit.
- Aturan baru di AGENTS.md: query resource tenant wajib lewat Eloquent karena
  `DB::table()` tidak tersaring scope.
**File:** app/Models/Concerns/ScopedKeOrganisasi.php,
app/Models/{Transaksi,Keluarga}.php, tests/Feature/IsolasiTenantTest.php, AGENTS.md
**Catatan:** 112 tes lulus di SQLite DAN MariaDB 11.8.8 (8 baru); 104 tes lama
tak tersentuh membuktikan nol perubahan perilaku untuk tenant tunggal. Dua
kegagalan di tengah jalan adalah data uji saya (nama param `minggu` dan field
wajib validasi), bukan bug kode. Yang BELUM di-scope: Surat, Aduan, Umkm,
Kegiatan, Pengeluaran, Sumbangan, SetorSampah, Pendaftaran, AuditLog, plus
pembaca `DB::table` di PengaturanController/LaporanController - lanjutan E2.

## 2026-08-15 - Phase E1 multi-tenant: peran generik + assignment ber-scope
**Agen:** claude | **Status:** selesai
**Kenapa:** Lanjutan C, disetujui pemilik project termasuk rekomendasi tidak
memakai ulang tabel `roles` era PWA. Otorisasi harus bisa menjawab "peran apa,
DI organisasi mana", bukan cuma level global - fondasi §8-§10 dokumen arsitektur.
**Perubahan:**
- Migrasi `2026_08_15_000007`: tabel `roles` lama di-RENAME ke
  `roles_legacy_pwa` (bukan drop - produksi mungkin masih menyimpan baris),
  tabel `roles` baru (katalog 6 peran generik dengan kolom `legacy_level`
  sebagai jembatan transisi) + `user_role_assignments` (user, role, org,
  unik bertiga), backfill dari `users.level`: superadmin/admin →
  super_admin@platform, ketua_rw → rw_admin@RW10, bendahara → rw_finance@RW10,
  warga → warga@RW10, petugas_rt → rt_admin@RT-nya (yang RT-nya tak
  ditemukan dilewati dengan peringatan, fallback tetap melindungi).
- `User::levelEfektif()`: assignment relevan (organisasi di rantai leluhur
  ATAU subtree tenant) yang terkuat menang; tanpa assignment jatuh ke
  `users.level`. Maksimal 2 query konstan; memo per request dititip di
  TenantContext (memo di instance model bocor antar request dalam tes).
- CheckRole, `userCan()`, dan seluruh helper izin User (isSuperAdmin, canVoid,
  canManageFinance, dst) membaca level efektif - tanpa ini hak dari assignment
  lolos middleware tapi ditolak cek lapis kedua di controller (ditemukan lewat
  tes yang gagal di AkunController).
- Seeder memasang super_admin@platform untuk akun bawaan, idempoten.
- Hierarki level pindah ke `User::LEVEL_POWER` (satu sumber, CheckRole impor).
- 8 tes baru `tests/Feature/PeranScopeTest.php`: assignment mengangkat hak,
  assignment tenant lain TIDAK berlaku di sini, platform berlaku ke bawah,
  rt_admin berlaku di RW induknya tapi tidak naik jadi pengurus RW, fallback,
  pemilihan terkuat, seeder idempoten.
**File:** database/migrations/2026_08_15_000007_rebuild_roles_and_add_assignments.php,
app/Models/{User,Role,UserRoleAssignment}.php, app/Http/Middleware/CheckRole.php,
app/Services/TenantContext.php, app/helpers.php, database/seeders/DatabaseSeeder.php,
tests/Feature/{PeranScopeTest,HalamanUtamaTest}.php
**Catatan:** 104 tes lulus di SQLite DAN MariaDB 11.8.8; rollback E1 diuji
turun-naik di keduanya; backfill diuji di DB berisi data (petugas_rt '05'
tertaut ke RT05, tabel legacy utuh). Plafon tes hitung-query Dashboard
dinaikkan 20→21 SECARA EKSPLISIT dengan rincian komponen di komentarnya
(2 resolver B2 + 2 level efektif E1); penjaga strukturalnya (jumlah query
konstan saat RT bertambah) tetap lulus tanpa diubah. Insiden selama kerja:
grep berpola salah mematikan artisan via SIGPIPE di tengah migrasi scratch,
meninggalkan kolom separuh terpasang - pengingat bahwa DDL SQLite tidak
transaksional; scratch diperbaiki manual, bukan bug migrasinya.

## 2026-08-15 - Verifikasi MySQL: 96 tes lulus di MariaDB 11.8.8
**Agen:** claude | **Status:** selesai
**Kenapa:** Gerbang wajib sebelum fase multi-tenant boleh deploy: seluruh
verifikasi sebelumnya hanya SQLite, padahal produksi MySQL, dan
`whereJsonContains` di `transaksis.periode` belum pernah teruji di sana.
**Perubahan:** Tidak ada perubahan kode aplikasi. Lingkungan dev: driver
`pdo_mysql` dipasang, DB `paru_test` dibuat di MariaDB lokal.
**File:** .ai/TODO.md (catatan lingkungan + centang), .ai/AUDIT-MULTITENANT.md
**Catatan:** Hasil: 96 tes / 200 assertion lulus penuh di MariaDB 11.8.8;
40 migrasi naik bersih; 3 migrasi multi-tenant diuji turun-naik di MySQL.
Insiden yang terjadi dan dipulihkan: `dnf install php-mysqlnd` menarik paket
PHP 8.5.9 Fedora 44 yang MENIMPA modul milik binary custom PHP 8.4.23
(/usr/bin/php tak dimiliki paket mana pun) sehingga PDO mati total.
Dipulihkan dengan mencabut ketiga paket 8.5.9, mengembalikan php.ini dari
.rpmsave, dan mengekstrak modul dari RPM php 8.4.24 fc43 (API modul sama).
Pelajarannya tercatat di TODO: jangan `dnf install php-*` di mesin ini.

## 2026-08-15 - Phase C multi-tenant: kolom organization_id + cap otomatis
**Agen:** claude | **Status:** selesai (verifikasi MySQL menunggu akses root)
**Kenapa:** Lanjutan B2. Data tenant butuh kolom kepemilikan sebelum query bisa
di-scope (E2), dan baris BARU harus langsung tercap sejak sekarang - kalau
tidak, backfill hari ini langsung basi begitu ada insert pertama pasca deploy.
**Perubahan:**
- Migrasi `2026_08_15_000006`: `organization_id` (nullable + index eksplisit)
  di 12 tabel tenant, lalu backfill seluruh baris existing ke RW 10 dengan
  laporan jumlah per tabel. `anggotas` dan `iuran_*` sengaja tidak: scope
  diturunkan lewat `keluarga_id` (§20).
- Trait `MilikOrganisasi` di 12 model. Aturan cap saat create: ada context
  request → SELALU dari context, menimpa kiriman client (10 dari 12 model
  ber-`$guarded=[]`, jadi tanpa penimpaan organization_id bisa disuntik lewat
  form); tanpa context (konsol) → nilai eksplisit dihormati, kalau kosong dan
  tepat satu RW aktif → pakai itu, lebih dari satu → null (berhenti menebak).
- 8 tes baru `tests/Feature/KepemilikanOrganisasiTest.php`, termasuk tes
  reflektif yang gagal bila ada model tenant kehilangan trait.
**File:** database/migrations/2026_08_15_000006_add_organization_id_to_tenant_tables.php,
app/Models/Concerns/MilikOrganisasi.php, 12 model di app/Models/,
tests/Feature/KepemilikanOrganisasiTest.php
**Catatan:** 96 tes lulus (88 lama tak tersentuh). Backfill diuji di DB berisi
data: 3 keluargas + 1 users tertaut, nol baris tanpa org; rollback bersih.
Pint dibandingkan baseline HEAD: nol penyimpangan baru. Akun seeder tidak
tercap (DatabaseSeeder pakai WithoutModelEvents) dan itu disengaja: `jabnet`
calon akun platform, penetapan org-nya urusan E1. MySQL: MariaDB lokal aktif
tapi butuh dua perintah root dari pemilik mesin (tercatat di TODO).

## 2026-08-15 - Phase B2 multi-tenant: TenantContext + resolver hostname
**Agen:** claude | **Status:** selesai
**Kenapa:** Lanjutan B1. Aplikasi butuh satu tempat resmi yang tahu "request
ini milik tenant mana" sebelum fase scoping bisa dimulai, dan hostname harus
di-resolve sekali lewat middleware, bukan di-parsing tersebar (§13-§14).
**Perubahan:**
- `app/Services/TenantContext.php`: pemegang konteks per request (scoped di
  container), API: `organisasi()`, `rw()`, `desa()`, `platform()`, `hostname()`.
- `app/Http/Middleware/ResolveTenant.php`: lookup hostname di tabel `domains`;
  tak terdaftar / nonaktif / organisasinya nonaktif → 404. TANPA fallback
  diam-diam. Dipasang di grup `web` saja sehingga `/up` (health) bebas.
- Migrasi `2026_08_15_000005`: `localhost` + `127.0.0.1` didaftarkan resmi
  sebagai domain status `dev` menunjuk RW 10. Tanpa ini, aturan 404 mematahkan
  `artisan serve` dan seluruh suite tes.
- 9 tes baru `tests/Feature/TenantResolverTest.php` termasuk kasus
  `paru.jabnet.id.jahat.example` (suffix-spoofing) dan organisasi nonaktif.
**File:** app/Services/TenantContext.php, app/Http/Middleware/ResolveTenant.php,
database/migrations/2026_08_15_000005_register_dev_hostnames.php,
bootstrap/app.php, app/Providers/AppServiceProvider.php,
tests/Feature/TenantResolverTest.php
**Catatan:** Perilaku produksi tidak berubah untuk hostname sah: 79 tes lama
lulus tanpa disentuh (total 88). Smoke test HTTP dengan header Host sungguhan:
localhost 200, paru 200, legacy 200, host asing 404, `/up` dari host mana pun
200. Yang berubah secara sengaja: request dengan header Host asing yang dulu
tetap dilayani kini 404 - itu justru tujuan §14. Belum ada controller yang
MEMBACA context (itu Phase C/E); B2 hanya menyediakannya.

## 2026-08-15 - Phase B1 multi-tenant: tabel organizations + domains
**Agen:** claude | **Status:** selesai
**Kenapa:** Pemilik project menyetujui mulai implementasi dari B1 (langkah
pertama audit bagian 10): fondasi hierarki tenant yang additive murni, supaya
fase resolver dan scoping berdiri di atas tabel yang terjamin bentuknya.
**Perubahan:**
- Migrasi `2026_08_15_000004`: tabel `organizations` (self-referencing
  parent_id, nullOnDelete) dan `domains` (hostname unique), plus seed hierarki
  existing di dalam migrasi: Platform Jabnet > Desa Sukakarya > RW 10 > RT
  (diderivasi dari `keluargas.rt` union `users.rt`, dinormalisasi dua digit).
  Domain `paru.jabnet.id` (primary) dan `sukawarga10.jabnet.id` (legacy)
  keduanya menunjuk RW 10.
- Model `Organization` (relasi parent/children/domains, helper `leluhur()`)
  dan `Domain`. `$fillable` eksplisit sesuai kolom.
- 6 tes baru di `tests/Feature/OrganisasiTest.php` mengunci bentuk hierarki,
  pemetaan domain, keunikan hostname, dan perilaku nullOnDelete.
**File:** database/migrations/2026_08_15_000004_create_organizations_and_domains.php,
app/Models/Organization.php, app/Models/Domain.php, tests/Feature/OrganisasiTest.php
**Catatan:** Nol perubahan perilaku: belum ada controller/helper yang membaca
tabel ini, dan 73 tes lama lulus tanpa disentuh (total 79). Verifikasi:
rollback `--step=1` bersih; derivasi RT diuji dengan data '01'+'1'+'03' di
keluargas dan '05' di users menghasilkan tepat 3 RT (01, 03, 05); Pint pass.
Keputusan bentuk (penamaan Inggris snake_case untuk tabel infra, seed di
migrasi, desa.jabnet.id sengaja tidak dimasukkan) tercatat di DECISIONS.

## 2026-08-15 - Audit Phase A multi-tenant (discovery, tanpa perubahan kode)
**Agen:** claude | **Status:** selesai
**Kenapa:** Pemilik project menambahkan `AI_AGENT_MULTI_TENANT_ARCHITECTURE.md`
(visi platform multi-desa) yang §44-nya mewajibkan gap analysis sebelum satu
baris pun di-refactor. Diminta dikerjakan bertahap; ini tahap pertamanya.
**Perubahan:** Hanya dokumen. `.ai/AUDIT-MULTITENANT.md` baru berisi 10 bagian
sesuai format §44: arsitektur saat ini, inventori 25 tabel dengan kolom scope
aktualnya, alur otorisasi, 11 asumsi single-RW dengan bukti file:baris, asumsi
domain, perubahan skema & kode yang dibutuhkan, risiko keamanan & migrasi, dan
urutan fase. Rantai rujukan diperbarui (README, HANDOFF, TODO).
**File:** .ai/AUDIT-MULTITENANT.md, AI_AGENT_MULTI_TENANT_ARCHITECTURE.md
(commit terpisah), README.md, .ai/HANDOFF.md, .ai/TODO.md
**Catatan:** Temuan yang tidak terduga: tabel `roles` sudah ada (json
`permissions`, flag `rtFilter`) tapi nol rujukan di kode - kandidat pakai-ulang
atau drop untuk `user_role_assignments`, harus diputuskan sebelum B1. Temuan
baik: tidak ada logika hostname di mana pun, jadi resolver §14 bisa dibangun
tanpa membongkar apa pun; `keluargas` bahkan sudah punya kolom `rw`/`kelurahan`/
`kecamatan` (string hardcoded). Implementasi Phase B BELUM dimulai.

## 2026-08-15 - Alamat portal jadi pengaturan, dokumentasi ikut nama baru
**Agen:** claude | **Status:** selesai
**Kenapa:** Diminta memperbarui berkas `.md` lain dan menyiapkan pindah domain ke
`desa.jabnet.id`. Saat memeriksa domainnya, ketahuan link di pesan WhatsApp masih
menunjuk `sukawarga10.jabnet.id`, padahal produksi sudah di `paru.jabnet.id`.
Jadi ini bukan sekadar persiapan: ada link yang memang sudah salah.
**Perubahan:**
- Key keempat `alamat_portal` di `app_settings` (helper `alamatPortal()`),
  menggantikan domain yang ditulis tetap di `MpwaService` (2 tempat) dan
  `ProfilWargaController` (1 tempat).
- Divalidasi sebagai nama host: skema dan path ditolak, karena nilainya ditempel
  apa adanya ke pesan WhatsApp.
- README ditulis ulang, `AGENTS.md`/`DEPLOY.md`/`.ai/HANDOFF.md`/`.env.example`
  ikut nama dan domain baru. `DEPLOY.md` dapat bagian "Pindah domain" berisi
  urutan DNS dulu baru aplikasi.
**File:** app/helpers.php, app/Services/MpwaService.php,
app/Http/Controllers/{PengaturanController,ProfilWargaController}.php,
resources/views/admin/pengaturan.blade.php, tests/Feature/OtorisasiTest.php,
README.md, AGENTS.md, DEPLOY.md, .env.example, .ai/HANDOFF.md
**Catatan:** Dicek langsung, bukan diasumsikan: `paru.jabnet.id` resolve ke
103.194.47.165 dan menjawab 302; `sukawarga10.jabnet.id` resolve ke
103.194.46.164 (server BERBEDA, kemungkinan deployment lama) dan juga masih
hidup; `desa.jabnet.id` belum resolve sama sekali. Karena itu bawaannya
`paru.jabnet.id`, bukan `desa.jabnet.id`: alamat mati di pesan ke warga lebih
buruk daripada alamat lama. `composer test` 73 lulus.

## 2026-08-15 - Teks logo jadi "Portal Desa" (generik, bukan nama instansi)
**Agen:** claude | **Status:** selesai
**Kenapa:** Teks nama masih tergambar di dalam `logo-sukawarga.svg`, dan itu satu
satunya identitas yang tidak bisa diganti lewat Pengaturan karena bentuknya
gambar. Diisi nama generik supaya berkas logo yang sama tetap benar untuk project
turunan, sementara nama instansinya dibawa `namaAplikasi()` di h1 halaman login.
**Perubahan:** Satu elemen `<text>` di SVG. Tidak ada gambar yang di-generate:
teksnya diedit langsung sebagai vektor, jadi tajam di ukuran berapa pun dan
hurufnya persis. `alt` hero login jadi "Logo Portal Desa" karena alt menjelaskan
gambarnya, bukan nama yang bisa berubah. Sekalian `alt` kop surat yang masih
menyebut "RW 10 Sukakarya" diarahkan ke `$rw`/`$kel` dari Pengaturan.
**File:** public/logo-sukawarga.svg, resources/views/auth/login.blade.php,
resources/views/layanan/cetak_surat.blade.php
**Catatan:** Diperiksa: `og-image.png`, `icon-512.png`, dan `logo-sukawarga-icon.svg`
tidak memuat teks sama sekali, jadi tidak ada aset raster yang perlu dibuat ulang.
Halaman login dipotret di 360/768/1280px lewat headless Chrome: tidak ada yang
meluber, bertumpuk, atau terpotong. `composer test` 72 lulus.

## 2026-08-15 - Ganti nama jadi Kampung Paru, identitas ditarik ke Pengaturan
**Agen:** claude | **Status:** selesai
**Kenapa:** Diminta pemilik project. Nama lama tertulis tetap di 8 berkas, jadi
mengganti nama berarti mengedit kode di banyak tempat, dan project turunan untuk
kampung lain akan mengalami hal yang sama. Rename dipakai sekalian untuk
memindahkan identitas ke `app_settings` supaya berikutnya cukup lewat menu.
**Perubahan:**
- Tiga key baru di `app_settings`: `nama_aplikasi`, `tagline_aplikasi`,
  `lokasi_singkat`, dibaca lewat `identitasAplikasi()` di `app/helpers.php`.
  Nilai bawaannya ada di helper, jadi instalasi baru langsung jalan tanpa baris
  DB satu pun.
- Dibaca sekali per request dan diingat lewat container, bukan `static`.
  Layout memanggilnya beberapa kali per halaman; `static` akan bocor antar
  request di dalam satu proses tes, sedangkan container dibuat ulang tiap request.
- `MpwaService::FOOTER` (konstanta) jadi `MpwaService::footer()`, plus `kop()`
  dan `tandaTangan()`. Konstanta tidak bisa memanggil fungsi, sedangkan isinya
  sekarang datang dari Pengaturan. `MpwaController::DEFAULT_TEMPLATES` jadi
  `defaultTemplates()` dengan alasan yang sama.
- Halaman Pengaturan dapat blok "Identitas Aplikasi" di tab Info RW. `showTab()`
  kini bisa menampilkan lebih dari satu kartu per tab lewat `data-tab-panel`.
- Baris lokasi halaman login tidak lagi menyebut RW 10 Sukakarya.
- Em dash pada teks WhatsApp ikut hilang karena barisnya memang diganti.
**File:** app/helpers.php, app/Services/MpwaService.php,
app/Http/Controllers/{MpwaController,PengaturanController,ProfilWargaController}.php,
resources/views/{layouts/app,auth/login,admin/pengaturan,admin/mpwa}.blade.php,
public/{site.webmanifest,logo-sukawarga.svg,css/styles.css,css/mobile-fixes.css},
tests/Feature/HalamanUtamaTest.php
**Catatan:** Verifikasi: `composer test` 71 lulus (2 tes baru, salah satunya
memastikan tagline dari Pengaturan di-escape dan tidak dirender sebagai HTML),
plus smoke test HTTP halaman login. `pint --test` dibandingkan dengan versi HEAD
untuk 5 berkas lama yang disentuh: tidak ada penyimpangan gaya baru.
Tes hitung-query perlu `forgetInstance` karena container dipakai ulang antar
request di dalam satu tes, bukan karena ada kebocoran di produksi.
Yang SENGAJA tidak diubah: domain `sukawarga10.jabnet.id` di pesan WA (itu alamat
sungguhan, DNS di luar jangkauan kode), nama berkas aset, repo, `APP_NAME` di
`.env.example`, README/AGENTS/DEPLOY, dan alamat bawaan di `ExportImportController`
(itu data, bukan merek). Sesuai cakupan yang dipilih pemilik project.

## 2026-08-15 - PIN bawaan akun `admin` disamakan jadi 463696
**Agen:** claude | **Status:** selesai
**Kenapa:** Diminta pemilik project. Sebelumnya `admin` memakai `123456`, PIN yang
sama dengan kredensial lama di `public/js/auth.js` yang pernah bisa diunduh siapa
saja dari domain produksi, jadi menyamakannya dengan PIN yang dipilih pemilik
sekaligus mematikan tebakan yang paling gampang.
**Perubahan:** Satu nilai di `database/seeders/DatabaseSeeder.php`, plus baris
contoh di `DEPLOY.md` yang masih menyebut `admin / 123456`.
**File:** database/seeders/DatabaseSeeder.php, DEPLOY.md
**Catatan:** Ini HANYA berlaku untuk database yang belum punya akun `admin`.
Seeder sengaja create-if-missing, jadi di produksi (yang akunnya sudah ada) PIN
lama TIDAK berubah dan wajib diganti manual lewat Manajemen Akun - sudah
ditambahkan ke checklist deploy di TODO. Verifikasi yang dijalankan: seed ke DB
kosong lalu `Hash::check` (463696 cocok, 123456 tidak) dan `Auth::attempt`
berhasil; seed diulang dan benar melaporkan "sudah ada, dilewati"; `composer test`
69 lulus. Pint melaporkan `blank_line_before_statement` pada seeder, tapi itu
sudah ada sebelum perubahan ini (diperiksa terhadap versi HEAD) dan termasuk 43
berkas lama yang sengaja belum diformat ulang.

## 2026-08-15 - Merge main ke dev, lalu push kedua branch
**Agen:** claude | **Status:** selesai
**Kenapa:** `origin/main` ternyata sudah maju satu commit (`c56c41b`: rombakan
Dashboard jadi BI, perbaikan Manajemen Akun, pembersihan em-dash) yang belum ada
di lokal. Harus digabung sebelum pekerjaan penyelarasan standar bisa didorong.
**Perubahan:**
- Merge `main` ke `dev`, lalu `main` di-fast-forward ke hasilnya. Keduanya kini
  di `f53e138` dan sinkron dengan remote.
- Satu konflik di `resources/views/layanan/aduan.blade.php`, diselesaikan dengan
  mengambil KEDUA sisi: pembacaan `$user->namaLengkap` (kolom `nama` tidak ada
  di tabel `users`, jadi nilainya selalu null) digabung dengan pemisah titik
  tengah dari pembersihan em-dash.
- `userCan()` di `app/helpers.php` diarahkan ke `User::LEVEL_ADMIN`. Commit dari
  main memperkenalkan konstanta itu sebagai sumber kebenaran tunggal untuk level
  setara superadmin, sementara helper masih menulis ulang daftarnya.
- URL remote `origin` diubah dari HTTPS ke SSH, plus `core.sshCommand` untuk
  repo ini. Push HTTPS gagal karena tidak ada kredensial di mesin ini; kunci
  `~/.ssh/yoga-github-ssh` tersedia dan valid.
**File:** app/helpers.php, resources/views/layanan/aduan.blade.php (+ hasil merge)
**Catatan:** Beruntung `c56c41b` hanya menyentuh *view* Dashboard, bukan
`DashboardController`, sehingga refactor query tidak bertabrakan. `composer test`
69 lulus pada pohon hasil merge, termasuk render Dashboard BI baru dengan
controller yang sudah dioptimasi.

## 2026-08-15 - Penyelarasan project dengan standar pengembangan
**Agen:** claude | **Status:** selesai
**Kenapa:** Diminta membawa seluruh project memenuhi standar, bukan hanya menambal
tiga temuan terparah. Lingkungan lokal dipasang lebih dulu (`composer install`,
`.env`, SQLite) supaya setiap perbaikan bisa diverifikasi dengan dijalankan,
bukan diasumsikan.

**Keamanan & otorisasi**
- `userCan()` sekarang fail-closed. Level yang tidak dikenal ditolak, sebelumnya
  justru diberi akses penuh.
- Form Pengaturan memakai whitelist key + validasi tipe. Sebelumnya menulis field
  request apa pun ke `app_settings`, termasuk `role_permissions` yang mengatur
  otorisasi, dan rutenya terbuka untuk ketua_rw.
- Sisa aplikasi PWA lama dihapus dari `public/` (`index.html`, `login.html`,
  `js/*.js`, `ext-test.php`). `js/auth.js` memuat kredensial default dalam teks
  polos dan bisa diunduh siapa saja dari domain produksi.
- `robots.txt` menolak seluruh crawler; tidak ada konten publik yang perlu
  diindeks, sedangkan isinya data pribadi warga.
- Kepemilikan data warga diikat lewat kolom baru `users.keluarga_id`, bukan
  kecocokan nama. Migrasi menautkan yang tidak ambigu saja.

**Kebenaran data**
- `User::$fillable` dibersihkan dari kolom yang tidak ada (`nama`, `noHP`), dan
  pembacaan `$user->nama` yang selalu null diganti `namaLengkap`.
- `Umkm`/`Kegiatan` tidak lagi memakai `$request->all()` pada model `$guarded = []`.
- `resetData()` tidak lagi melapor gagal padahal berhasil, dan transaksi semu di
  sekeliling TRUNCATE dihapus beserta penjelasannya.
- Periode iuran disimpan terstruktur di kolom baru `transaksis.periode`. Rollback
  void tidak lagi menebak lewat teks keterangan; parsing lama tetap ada sebagai
  fallback untuk baris lama, dan untuk padaringan dibatasi ke segmen periode.
- `NotificationService` mendelegasikan pengiriman ke `MpwaService`. Sebelumnya
  keduanya punya endpoint, cara auth, dan normalisasi nomor yang berbeda.
- Zona waktu aplikasi jadi `Asia/Jakarta`. Dengan UTC, pembayaran sebelum pukul
  07.00 WIB tanggal 1 masih terhitung bulan sebelumnya.

**Performa**
- Query di dalam loop pada Dashboard dihapus. Terukur turun dari sekitar 45 ke 16
  query, dan yang lebih penting jumlahnya kini tetap saat jumlah RT bertambah.
- Badge pendaftaran pindah dari Blade ke view composer.
- Index ditambahkan untuk kolom filter/join/sort di 9 tabel.
- `Log Sistem` dan `Data Warga` dipaginasi (sebelumnya `limit(200)` diam-diam dan
  `get()` tanpa batas), memakai partial `partials/pagination`.

**Kebersihan**
- Toolchain Vite/Tailwind dihapus karena tidak dipakai sama sekali (lihat DECISIONS).
- `update_models.php` dihapus. Skrip itu bukan cuma mati: kalau dijalankan lagi ia
  menyuntikkan `$guarded = []` ke `Transaksi.php` dan membatalkan perbaikan
  mass assignment.
- `welcome.blade.php` dan method mati `setorIndex`/`sumbanganIndex` dihapus.
- README dan `.env.example` ditulis ulang untuk project ini.
- 69 tes dibuat dari nol (sebelumnya hanya `ExampleTest` bawaan) mencakup jalur
  uang, void, otorisasi per peran, kepemilikan, helper domain, dan render halaman.

**File:** lihat `git status` - 65 berkas berubah/ditambah/dihapus.
**Catatan:** Verifikasi yang dijalankan: `composer test` (69 lulus, 116 assertion),
Pint bersih untuk seluruh berkas baru, migrasi `up` dan `down` diuji, serta smoke
test HTTP sungguhan termasuk login `jabnet` end-to-end. Yang BELUM: cek UI di
360/768/1280px, dan uji di MySQL (lokal memakai SQLite). Pint masih melaporkan 43
berkas lama menyimpang gaya; sengaja tidak diformat ulang agar diff tetap
terbaca - dicatat sebagai pekerjaan tersendiri di TODO.

## 2026-08-15 - Perbaikan P0: mass assignment transaksi, otorisasi endpoint, akun bawaan
**Agen:** claude | **Status:** selesai (verifikasi runtime belum bisa dijalankan)
**Kenapa:** Tiga temuan paling mendesak dari studi hari ini: pencatatan uang gagal
diam-diam, endpoint pengubah data bisa dipanggil akun warga atas data orang lain,
dan belum ada akun full-access bawaan yang dijamin ada.
**Perubahan:**
- `Transaksi::$fillable` disamakan dengan kolom tabel `transaksis` yang sebenarnya.
  Sebelumnya menyebut 4 kolom yang tidak ada dan melewatkan `transaksi_id`/`kas`
  (NOT NULL) sehingga seluruh insert transaksi gagal.
- Void transaksi memakai `forceFill()->save()`. Kolom void sengaja tidak fillable,
  tapi `update()` juga tunduk pada `$fillable` - jadi void tidak pernah tersimpan.
- Middleware peran ditambahkan untuk surat (ubah/hapus → ketua_rw, TTD/tolak →
  petugas_rt), aduan status, umkm, kegiatan, dan `/search`.
- Cek kepemilikan surat untuk `show` & `cetak`: warga hanya bisa membuka miliknya.
- Kotak pencarian global disembunyikan untuk level warga (data seluruh warga).
- Seeder: akun `admin` dan `jabnet` (superadmin, PIN 463696) dibuat hanya bila
  belum ada. Diubah dari `updateOrCreate` yang mengembalikan PIN ke nilai bawaan
  setiap kali `db:seed` dijalankan di produksi.
**File:** app/Models/Transaksi.php, app/Http/Controllers/TransaksiController.php,
app/Http/Controllers/SuratController.php, routes/web.php,
resources/views/layouts/app.blade.php, database/seeders/DatabaseSeeder.php
**Catatan:** Verifikasi terbatas pada `php -l` (bersih) dan pencocokan manual
seluruh key `Transaksi::create()` terhadap `$fillable`. `composer test` dan
`artisan route:list` belum bisa dijalankan karena `vendor/` dan `.env` tidak ada
di working copy. Wajib dijalankan sebelum deploy. PIN 463696 tersimpan sebagai
teks polos di seeder yang masuk git - lihat catatan risiko di `.ai/TODO.md`.

## 2026-08-15 - Studi awal codebase + pembuatan AGENTS.md & folder .ai
**Agen:** claude | **Status:** selesai
**Kenapa:** Repo belum punya file aturan maupun state, jadi setiap agen baru mulai
dari nol dan berisiko mengulang atau merusak konvensi yang sudah ada.
**Perubahan:** Tambah `AGENTS.md` (stack, kosakata domain, konvensi, ranjau yang
diketahui) dan folder `.ai/` (PROGRESS, TODO, DECISIONS). Tidak ada kode aplikasi
yang diubah.
**File:** AGENTS.md, .ai/PROGRESS.md, .ai/TODO.md, .ai/DECISIONS.md
**Catatan:** Studi menemukan sejumlah temuan serius yang belum diperbaiki (mass
assignment `Transaksi`, endpoint tanpa otorisasi, sisa file PWA lama di `public/`).
Semua dicatat di `.ai/TODO.md` dan menunggu keputusan pemilik project. Verifikasi
runtime belum bisa dilakukan: `vendor/`, `.env`, dan file database tidak ada di
working copy, jadi temuan berbasis pembacaan kode + semantik Laravel, bukan
eksekusi. Satu-satunya verifikasi yang dijalankan: `php -l` untuk seluruh file
di `app/`, `database/`, `routes/`, `config/` - bersih, nol error.
