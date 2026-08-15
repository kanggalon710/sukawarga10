# Audit Gap Analysis - Single-RW menuju Multi-Tenant

Tanggal: 2026-08-15. Basis: commit `08db953`, 73 tes hijau.
Ini keluaran Phase A (Discovery) yang diminta §44
`AI_AGENT_MULTI_TENANT_ARCHITECTURE.md`. **Tidak ada kode yang diubah.**
Semua klaim di bawah punya bukti `file:baris`; kalau kode sudah bergeser,
verifikasi ulang sebelum dipakai.

---

## 1. Arsitektur saat ini

Satu aplikasi Laravel 12, Blade server-rendered, satu guard (`web`, session,
provider `users`), login username + PIN (kolom `pin`, `getAuthPassword()`
mengembalikannya). Otorisasi dua lapis: middleware `CheckRole` (hierarki angka)
untuk rute, cek kepemilikan manual di controller untuk resource warga.
Tanpa Policy, tanpa Form Request (konvensi tercatat di `AGENTS.md`).
Controller gemuk: total 3.828 baris di 11 controller + services.

Konfigurasi runtime dari `app_settings` (key-value global) dibaca lewat helper:
`userCan()`, `garisKemiskinan()`, `identitasAplikasi()` (nama/tagline/lokasi/
alamat portal, di-cache per request lewat container), tarif di controller,
kredensial MPWA di `MpwaService`.

## 2. Tabel milik tenant

25 tabel. Klasifikasi per kolom scope yang benar-benar ada di migrasi:

| Tabel | Scope yang ada | Catatan |
|---|---|---|
| `keluargas` | `rt`, `rw`, `kelurahan`, `kecamatan` (semuanya **string**, default `'10'`/`'Sukakarya'`/`'Tarogong Kidul'`) | Proto-scope terbaik yang ada, tapi string bebas, bukan FK |
| `anggotas` | `keluarga_id` | Scope diturunkan lewat KK, aman |
| `iuran_sampahs`, `iuran_padaringans` | `keluarga_id` | Diturunkan lewat KK |
| `users` | `level`, `rt` (string), `keluarga_id` | Tidak ada dimensi organisasi |
| `transaksis` | `refKeluargaId` (nullable), `operator` (string nama) | Entri kas umum tidak tertaut KK sama sekali |
| `setor_sampahs`, `pendaftarans`, `umkms` | `rt` (string) | |
| `aduans` | `rt`, `user_id` | |
| `surats` | `user_id`, `rt_target` | Tidak ada org scope |
| `kegiatans`, `pengeluarans`, `sumbangans` | **tidak ada sama sekali** | Seluruh baris implisit milik RW 10 |
| `app_settings` | tidak ada; `key` **UNIQUE** | Blocker skema untuk setting per-tenant (§16) |
| `audit_logs` | `operator` (string) | Tanpa `organization_id`, `user_agent`, `before/after` (§22) |
| `roles` | - | **Tabel mati**: ada `permissions` json + flag `rtFilter`, nol rujukan di `app/`/`routes/`/views. Sisa era PWA lama |

Tabel framework (`sessions`, `cache*`, `jobs*`, `password_reset_tokens`) boleh
tetap global.

## 3. Alur otorisasi saat ini

```text
request → auth (guard web)
        → CheckRole (app/Http/Middleware/CheckRole.php:21-41)
          hierarki: superadmin/admin=5 > ketua_rw=4 > bendahara=3 > petugas_rt=2 > warga=1
          lolos bila level user ada di daftar ATAU power >= power tertinggi yang diminta
        → cek kepemilikan di controller (surat: user_id, profil: users.keluarga_id)
        → userCan() HANYA untuk tampil/sembunyi menu (bukan pengamanan)
```

Gap terhadap target §8-§11:

- Satu user = satu `level` global. Tidak ada assignment `(user, role, organization)`,
  jadi "rw_admin di RW 01 dan RW 03 sekaligus" mustahil diekspresikan.
- `role_permissions` di `app_settings` adalah satu JSON global; di multi-tenant,
  desa yang bisa mengubahnya mengubah otorisasi SEMUA tenant.
- Permission belum granular (§11): `userCan('surat')` adalah kunci menu, bukan
  `letter.approve` vs `letter.create`.
- Positif: prinsip §12 (server-side mandatory) SUDAH ditegakkan dan dikunci
  `tests/Feature/OtorisasiTest.php`. Yang perlu diganti mekanismenya, bukan disiplinnya.

## 4. Asumsi single-RW (inventori, dengan bukti)

Diurutkan dari yang paling mahal diperbaiki:

1. **"Seluruh database = satu RW".** Semua query membaca tabel utuh:
   `LaporanController.php:60` (`Anggota::all()`), seluruh `DashboardController`
   (by design pasca refactor: 16 query konstan, semuanya tanpa scope),
   `WebAuthController.php:16-38` (halaman login publik menghitung statistik
   seluruh DB). Di multi-tenant, setiap query ini adalah kandidat tenant leakage.
2. **RW hardcoded sebagai data:** `KeluargaController.php:60` dan
   `PendaftaranController.php:49` menulis `'rw' => '10'`; default skema
   `keluargas` juga `'10'`.
3. **Penomoran surat:** `SuratController.php:51-52`:
   `max('nomorUrut')+1` global per tahun, format `%03d/KODE/RW10/tahun` dengan
   `RW10` literal. Di multi-tenant: nomor bentrok antar RW dan label salah.
4. **Notifikasi menyapu semua user:** `NotificationService.php:40` (`notifyPengurus`
   = semua user level pengurus), `:92` (admin), `MpwaController::broadcast`
   (loop semua KK). Multi-tenant tanpa scope = broadcast lintas desa.
5. **`CheckRole` buta organisasi:** `petugas_rt` RT 01 lolos middleware untuk
   aksi atas data RT 05; satu-satunya pembatas RT adalah filter opsional dari
   request (`KeluargaController.php:14`), bukan penegakan.
6. **Setting ambient:** semua pembaca `app_settings` (8 key + 4 key identitas)
   tanpa dimensi org; cache `identitas.aplikasi` di-key global per request,
   harus jadi per-organization.
7. **Kredensial & template MPWA global** (`MpwaService::apiKey/sender/baseUrl`,
   `mpwa_templates`): satu nomor WA pengirim untuk semua tenant.
8. **Operasi destruktif tanpa scope:** `PengaturanController::resetData`
   men-TRUNCATE 14 tabel utuh; `removeDuplicates` mencocokkan `nama+rt` global.
   Di multi-tenant keduanya senjata pemusnah lintas desa.
9. **Uniqueness global:** `users.username` unique global (dua desa tidak bisa
   sama-sama punya `ketua01`); `forgotCredentials` (`WebAuthController.php:183-190`)
   mencari user via nomor WA lintas seluruh platform.
10. **View composer** badge pendaftaran: `Pendaftaran::where('status','pending')->count()`
    global (`AppServiceProvider`).
11. **Seeder** membuat `admin` (tenant) dan `jabnet` (platform) sebagai level
    global yang setara; belum ada pemisahan §24.

## 5. Asumsi domain / routing

Bersih, dan itu kabar baik: **tidak ada logika hostname di mana pun** di `app/`
maupun `routes/`. Rute flat tanpa prefix tenant. `SESSION_DOMAIN=null`.
`alamat_portal` dan `APP_URL` bernilai tunggal.

Artinya resolver §14 bisa dibangun dari nol tanpa membongkar apa pun, dengan
satu keharusan §34: hostname legacy (`paru.jabnet.id`, dan warisan
`sukawarga10.jabnet.id` yang masih hidup di 103.194.46.164) harus resolve ke
tenant RW 10, bukan 404.

## 6. Perubahan skema yang dibutuhkan (target, belum final)

Additive dulu, destructive belakangan. Nama tabel final belum dikunci (§43).

1. `organizations` (id, parent_id, type platform/desa/rw/rt, name, code, slug,
   status) + seed hierarki existing: Platform → Desa Sukakarya → RW 10 → RT 01..n
   (daftar RT diambil dari `SELECT DISTINCT rt FROM keluargas`).
2. `domains` (organization_id, hostname, is_primary, status) + baris untuk
   `paru.jabnet.id` (dan alias legacy).
3. `user_role_assignments` (user_id, role_id, organization_id). Tabel `roles`
   yang mati bisa dipakai ulang ATAU di-drop dan dibuat bersih; putuskan di
   Phase B, jangan diam-diam (`.ai/DECISIONS.md`).
4. Kolom `organization_id` (level RW cukup, per §20 jangan FK redundan) pada:
   `keluargas`, `surats`, `kegiatans`, `pengeluarans`, `sumbangans`,
   `transaksis`, `setor_sampahs`, `pendaftarans`, `umkms`, `aduans`,
   `audit_logs`, `users` (org "rumah", di samping assignment).
   `anggotas` dan `iuran_*` TIDAK perlu: scope diturunkan via `keluarga_id`.
5. `app_settings`: `unique(key)` → `unique(organization_id, key)` dengan
   `organization_id` nullable = platform default (mendukung inheritance §17).
6. Konversi `rt` string → rujukan org: **backfill paling berisiko**, karena
   string bebas (`'01'` vs `'1'` vs kosong). Harus ada laporan baris ambigu,
   pola yang sama dengan backfill `users.keluarga_id` yang sudah pernah sukses.
7. Unique constraint per-org: `surats(organization_id, tahun, nomorUrut)`.
   `username` tetap unique global untuk fase awal (keputusan eksplisit, bukan default).

## 7. Perubahan kode yang dibutuhkan

| Komponen | Perubahan | Ukuran |
|---|---|---|
| TenantContext service + middleware resolver | baru (§13-§14); fallback: hostname tak dikenal → 404, kecuali daftar legacy → RW 10 | sedang |
| `CheckRole` | ganti total: role+scope lookup, bukan hierarki angka | kecil tapi menjalar ke semua rute |
| `app/helpers.php` | `userCan`, `getMenuPermissions`, `garisKemiskinan`, `identitasAplikasi` (+cache per org) jadi org-aware | sedang |
| 11 controller (3.828 baris) | setiap query resource tenant diberi scope; audit per-query | **terbesar** |
| `MpwaService` / `NotificationService` | kredensial, template, footer, sasaran notifikasi per org | sedang |
| `SuratController` | penomoran per org, format nomor dari `organizations.code` | kecil |
| `PengaturanController::resetData` | scope per org atau khusus platform | kecil, kritis |
| Seeder | pisahkan platform staff vs tenant admin (§24) | kecil |
| `WebAuthController::showLoginForm` | statistik publik per tenant hasil resolve hostname | kecil |
| Tes | + tenant isolation suite (§37): cross-RW read/update/delete, domain tak dikenal, IDOR, isolasi setting | wajib sebelum production |

## 8. Risiko keamanan

1. **IDOR pasca-multi-tenant (§21).** Lookup sekarang memakai ID bisnis acak
   (`kk_<uniqid>`, `TRX-...`) - sulit ditebak, tapi ketidaktebakan bukan
   otorisasi. Begitu ada dua tenant, `Surat::where('surat_id', $id)` tanpa
   scope adalah kebocoran lintas desa.
2. **`role_permissions` per-tenant salah scope = eskalasi hak lintas platform.**
   Whitelist `PengaturanController` yang ada sudah benar arahnya; jangan dilonggarkan.
3. **`forgotCredentials` enumerasi lintas tenant** via nomor WA (kirim PIN baru).
4. **Broadcast MPWA** tanpa scope = spam lintas desa dari satu tenant admin.
5. **`resetData`/`removeDuplicates`** lintas tenant (lihat §4 butir 8).
6. **Audit log belum memenuhi §22**: tanpa org, `before/after`, `user_agent`;
   belum mencatat `view_sensitive_data`/`export`; impersonation belum ada.
7. Warisan yang sudah tercatat: PIN `463696` publik di riwayat git (perlakukan
   bocor), akun legacy di DB produksi, `sukawarga10.jabnet.id` masih hidup.

## 9. Risiko migrasi

1. **Produksi hidup, uang sungguhan.** Semua migrasi harus additive + backfill
   terverifikasi baris-per-baris; backup wajib (sudah ada di checklist deploy).
2. **Backfill `rt` string → org** adalah titik terlemah (data bebas ketik).
   Gagal-tertutup untuk yang ambigu, seperti preseden `users.keluarga_id`.
3. **Entri kas umum di `transaksis`** (refKeluargaId null) tidak bisa
   diturunkan dari KK; org-nya harus di-backfill sebagai "RW 10" eksplisit.
4. **`app_settings` ganti unique index** di MySQL dengan baris hidup: perlu
   urutan add-column → backfill → drop index → add composite index, diuji di
   MySQL (seluruh verifikasi repo ini baru SQLite; `whereJsonContains` juga
   belum teruji MySQL, sudah ada di TODO).
5. **TLS/wildcard (§7):** pola `sukakarya.desa.jabnet.id` butuh sertifikat yang
   benar-benar mencakupnya; jangan asumsikan wildcard satu level cukup untuk
   pola nested. Berlaku juga di cPanel target deploy (§28).
6. **Tanpa queue worker** (keputusan tercatat): §26-§27 menuntut queue saat
   tenant bertambah; pemasangan worker jadi prasyarat Phase G/H, bukan pilihan.
7. **Koeksistensi domain (§34):** `paru.jabnet.id` wajib tetap jalan selama dan
   sesudah migrasi; pesan WA lama masih memuat alamat lama.

## 10. Urutan implementasi yang direkomendasikan

Pemetaan Phase B-H dokumen ke langkah konkret repo ini, tiap langkah kecil,
hijau, dan bisa dirilis sendiri:

1. **B1 - SELESAI 2026-08-15** (migrasi `2026_08_15_000004`, tes
   `OrganisasiTest`): tabel `organizations` + `domains` + seed hierarki
   existing. Aplikasi belum membacanya. Catatan koreksi: prasyarat "keputusan
   tabel `roles`" yang disebut di bawah ternyata baru relevan di E1, ditunda
   eksplisit (lihat DECISIONS); prasyarat MySQL tetap berlaku untuk fase C/F.
2. **B2 - SELESAI 2026-08-15** (`TenantContext` scoped + `ResolveTenant` di
   grup web, migrasi `2026_08_15_000005`, tes `TenantResolverTest`): hostname
   terdaftar → context terisi, tak terdaftar → 404 tanpa fallback, `/up` bebas.
   Catatan: `localhost`/`127.0.0.1` didaftarkan resmi sebagai domain `dev`,
   bukan dikecualikan di kode.
3. **C:** kolom `organization_id` + backfill (semua baris = RW 10) + laporan
   verifikasi. Masih additive.
4. **E1:** `user_role_assignments` + backfill dari `users.level`, `CheckRole`
   membaca assignment dengan fallback ke level lama selama transisi.
5. **E2:** scoping query per controller, satu controller per PR, masing-masing
   dengan tes isolasi §37 (`RW A tidak bisa baca/ubah/hapus milik RW B`).
   Mulai dari jalur uang (`TransaksiController`) karena taruhannya tertinggi.
6. **F:** `app_settings` per org + inheritance platform→desa→RW + feature flags.
   `identitasAplikasi()` sudah menyiapkan bentuk API-nya.
7. **D:** domain tenant kedua yang sesungguhnya (desa/RW baru) baru dibuka di
   sini, SETELAH isolasi teruji, bukan sebelumnya.
8. **G/H:** UI manajemen tenant, impersonation (§23, dengan audit), queue
   worker + antrian WA, monitoring.

Prasyarat sebelum B1 dimulai (dari TODO existing): uji suite di MySQL, dan
keputusan soal tabel `roles` mati (pakai ulang atau drop).

---

*Dokumen ini snapshot. Setelah Phase B dimulai, perbarui bagian yang basi atau
tandai selesai; jangan biarkan audit usang menyaru sebagai peta yang benar.*
