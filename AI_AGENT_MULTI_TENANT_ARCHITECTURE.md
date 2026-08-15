# AI Agent Context — Multi-Tenant Desa Platform

## Status Dokumen

**Status: PLANNING ONLY**

Dokumen ini adalah konteks arsitektur dan development guideline untuk mengembangkan project **Kampung Paru / Portal Desa** dari single-RW menjadi platform **multi-tenant / multi-desa**.

> **PENTING:** Saat membaca dokumen ini, AI Agent **jangan langsung melakukan refactor besar atau migrasi database** hanya karena arsitektur target sudah dijelaskan. Untuk tahap planning, analisis codebase terlebih dahulu, identifikasi dependency, risiko, dan migration path. Implementasi harus dilakukan bertahap dan production-safe.

---

# 1. Konteks Project Saat Ini

Project saat ini adalah aplikasi informasi dan billing komunitas warga yang sudah berjalan secara production.

Current production context:

- Laravel 12
- PHP 8.2+
- Blade server-rendered
- Tidak menggunakan SPA
- Frontend CSS berada di `public/css/`
- Database production: MySQL
- Database lokal: SQLite
- Authentication saat ini: username + PIN
- Production saat ini: `paru.jabnet.id`
- Scope saat ini: RW 10, Kelurahan Sukakarya, Kecamatan Tarogong Kidul, Garut
- Aplikasi menangani data warga, KK, anggota keluarga, iuran, buku kas, surat, aduan, UMKM, kegiatan, laporan, dan notifikasi WhatsApp.

Project documentation yang harus dibaca sebelum perubahan:

- `AGENTS.md`
- `.ai/HANDOFF.md`
- `.ai/PROGRESS.md`
- `.ai/TODO.md`
- `.ai/DECISIONS.md`
- `DEPLOY.md`

Jika file-file tersebut tersedia, **gunakan sebagai source of truth sebelum melakukan perubahan kode**.

---

# 2. Visi Arsitektur

Project ini akan berkembang menjadi satu platform yang bisa dipakai banyak desa dan banyak RW.

Model target:

```text
Platform
│
├── Desa A
│   ├── RW 01
│   │   ├── RT 01
│   │   ├── RT 02
│   │   └── ...
│   ├── RW 02
│   └── ...
│
├── Desa B
│   ├── RW 01
│   └── ...
│
└── Desa C
```

Aplikasi tetap menggunakan **satu codebase Laravel**.

Target awal:

```text
ONE LARAVEL APPLICATION
ONE MYSQL DATABASE
MULTI TENANT
SHARED SCHEMA
```

Jangan membuat satu source code / satu application instance per desa kecuali ada alasan arsitektural yang sangat kuat dan sudah dibahas terlebih dahulu.

---

# 3. Prinsip Multi-Tenancy

## 3.1 Shared Schema sebagai Default

Untuk tahap awal, semua tenant menggunakan database yang sama dengan tenant identifier / organizational scope.

Contoh:

```text
users
organizations
roles
permissions
households
family_members
payments
letters
complaints
...
```

Data tenant harus mempunyai relasi scope yang jelas.

Jangan mengandalkan hostname saja sebagai source of truth.

Hostname hanya digunakan untuk **resolve tenant/context**, setelah itu authorization tetap harus memeriksa data scope dari database.

---

# 4. Hierarchy / Organizational Scope

Target hierarchy:

```text
PLATFORM
  ↓
DESA
  ↓
RW
  ↓
RT
  ↓
HOUSEHOLD / KK
  ↓
FAMILY MEMBER
```

Household / KK bukan tenant baru. KK adalah resource/data ownership dalam hierarchy.

---

# 5. Organization Model yang Direkomendasikan

Untuk fleksibilitas, gunakan konsep organization hierarchy.

Contoh konseptual:

```text
organizations
-------------
id
parent_id
type
name
code
slug
status
created_at
updated_at
```

Contoh data:

```text
1   null    platform    Arkanova/JABNET
2   1       desa        Sukakarya
3   2       rw          RW 01
4   2       rw          RW 02
5   3       rt          RT 01
6   3       rt          RT 02
```

**Catatan:** Ini adalah target architecture, bukan perintah bahwa schema ini harus langsung dibuat. Sebelum implementasi, inspect schema existing dan pilih migration path yang paling aman.

---

# 6. Domain / Subdomain Architecture

Target domain masih dapat berubah antara JABNET dan ARKANOVA.

Preferred future branding/domain example:

```text
desa.arkanova.id
sukakarya.desa.arkanova.id
rw-01-sukakarya.desa.arkanova.id
```

Alternative tetap dimungkinkan:

```text
desa.jabnet.id
sukakarya.desa.jabnet.id
rw-01-sukakarya.desa.jabnet.id
```

## Domain rule

Domain/hostname digunakan untuk mencari organisasi/context.

Contoh:

```text
rw-01-sukakarya.desa.arkanova.id
        ↓
domain resolver
        ↓
organization = RW 01 Sukakarya
        ↓
parent organization = Desa Sukakarya
```

Jangan membuat permission hanya berdasarkan hostname.

---

# 7. TLS / Wildcard Domain Consideration

Jangan mengasumsikan satu wildcard certificate untuk satu level akan otomatis mencakup arbitrary nested subdomains.

Karena itu, domain pattern harus dirancang dengan mempertimbangkan certificate coverage.

Preferred simple structure:

```text
sukakarya.desa.arkanova.id
rw-01-sukakarya.desa.arkanova.id
```

bukan secara default:

```text
rw-01.sukakarya.desa.arkanova.id
```

kecuali deployment/TLS memang dirancang untuk pola tersebut.

---

# 8. Role Architecture

Jangan membuat role unik untuk setiap desa atau RW.

Jangan membuat:

```text
admin_sukakarya
admin_tarogong
ketua_rw01_sukakarya
```

Gunakan generic roles + assignment scope.

Contoh role:

### Platform

```text
super_admin
platform_finance
platform_operations
platform_cs
platform_support
```

### Desa

```text
desa_admin
desa_finance
desa_operator
desa_viewer
```

### RW

```text
rw_admin
rw_finance
rw_operator
rw_viewer
```

### RT

```text
rt_admin
rt_operator
```

### Warga

```text
warga
```

---

# 9. Role + Scope, Bukan Role Saja

Authorization harus memodelkan:

```text
USER
 ↓
ROLE
 ↓
PERMISSION
 ↓
SCOPE / ORGANIZATION
 ↓
RESOURCE
```

Contoh assignment konseptual:

```text
user: Budi
role: rw_admin
organization: RW 01 Sukakarya
```

atau:

```text
user: Ani
role: desa_admin
organization: Desa Sukakarya
```

atau:

```text
user: CS JABNET
role: platform_support
organization: Platform
```

---

# 10. Recommended Assignment Table

Konsep yang disarankan:

```text
user_role_assignments
---------------------
user_id
role_id
organization_id
```

Dengan ini role yang sama dapat digunakan lintas tenant.

Contoh:

```text
rw_admin → RW 01 Sukakarya
rw_admin → RW 03 Sukakarya
rw_admin → RW 02 Desa B
```

---

# 11. Permission Architecture

Permission harus granular dan tidak hanya bergantung pada role.

Contoh:

```text
household.view
household.create
household.update
household.delete

family_member.view
family_member.update

letter.create
letter.approve
letter.reject

complaint.create
complaint.manage

payment.view
payment.create
payment.update
payment.delete

report.view
report.export
```

Role hanya menjadi kumpulan permission.

---

# 12. Server-Side Authorization adalah Mandatory

Jangan mengandalkan:

```text
hidden menu
frontend condition
Blade @if
JavaScript
```

sebagai security boundary.

Semua operation harus divalidasi di server menggunakan middleware, Gate, Policy, service authorization, atau kombinasi yang jelas.

Contoh risiko yang harus dihindari:

```php
Household::findOrFail($id);
```

Jika request datang dari RW 01, query harus tetap dibatasi ke scope RW 01.

Contoh konseptual:

```php
Household::query()
    ->where('rw_id', $currentRw->id)
    ->findOrFail($id);
```

Atau gunakan Policy/scoped query yang menghasilkan security equivalent.

---

# 13. Tenant Context

Aplikasi membutuhkan context object/service yang mengetahui context request saat ini.

Contoh konseptual:

```text
CurrentTenant / CurrentOrganization / PlatformContext
```

Minimal context dapat mengetahui:

```text
current hostname
current organization
current desa
current rw
current rt
current user
current role assignments
current permissions
```

Jangan menyebarkan parsing hostname ke seluruh controller.

Hostname harus di-resolve satu kali melalui dedicated middleware/service.

---

# 14. Domain Resolver

Target flow:

```text
HTTP Request
   ↓
Hostname Resolver
   ↓
Find domain record
   ↓
Find organization
   ↓
Build current context
   ↓
Authenticate user
   ↓
Load role assignment
   ↓
Authorize action
   ↓
Query scoped resource
   ↓
Controller / View
```

Bila domain tidak terdaftar:

```text
404 / tenant not found
```

Jangan fallback diam-diam ke tenant lain.

---

# 15. Domain Table

Recommended future concept:

```text
domains
-------
id
organization_id
hostname
type
is_primary
status
created_at
updated_at
```

Dengan ini satu organization bisa memiliki lebih dari satu domain.

Contoh:

```text
sukakarya.desa.arkanova.id
portal.sukakarya.desa.arkanova.id
custom-domain.example.id
```

Jangan hard-code domain tenant di controller.

---

# 16. App Settings Harus Tenant-Aware

Current project menggunakan `app_settings` untuk branding dan informasi aplikasi.

Saat ini key yang ada antara lain:

```text
nama_aplikasi
tagline_aplikasi
lokasi_singkat
alamat_portal
```

Untuk multi-tenant, setting harus mempunyai scope.

Contoh konseptual:

```text
app_settings
------------
id
organization_id
key
value
```

Dengan demikian:

```text
Platform setting
Desa setting
RW setting
```

dapat dibedakan.

---

# 17. Branding Hierarchy

Configuration dapat memakai inheritance:

```text
Platform Defaults
      ↓
Desa Settings
      ↓
RW Settings
```

Contoh:

```text
logo
nama aplikasi
alamat
nomor WhatsApp
rekening
timezone
currency
feature flags
```

Jika child tidak mempunyai override, gunakan value dari parent.

---

# 18. Feature Flags

Karena tidak semua desa akan memakai modul yang sama, pertimbangkan feature flags per organization.

Contoh:

```text
billing = true
waste_fee = true
padaringan = false
umkm = true
complaints = true
```

Jangan membuat fork source code per desa hanya karena fitur berbeda.

---

# 19. Data Model untuk Household

Household / KK harus mempunyai scope yang jelas.

Contoh konseptual:

```text
households
----------
id
desa_id
rw_id
rt_id
no_kk
address
status
```

Family member:

```text
family_members
--------------
id
household_id
nik
name
relationship
...
```

Household adalah resource milik organization hierarchy, bukan tenant baru.

---

# 20. Resource Scoping

Semua resource harus dipikirkan:

```text
Siapa owner-nya?
Siapa boleh membaca?
Siapa boleh membuat?
Siapa boleh mengubah?
Siapa boleh menghapus?
Siapa boleh export?
```

Contoh Complaint:

```text
complaints
----------
id
desa_id
rw_id
rt_id
household_id
created_by
status
...
```

Tidak semua tabel wajib memiliki semua level ID jika relationship dapat diturunkan secara aman. Jangan menambah foreign key redundan tanpa alasan.

---

# 21. Query Safety Rule

AI Agent wajib selalu mempertimbangkan tenant leakage.

Setiap query terhadap tenant data harus menjawab:

```text
Tenant/org scope dari query ini apa?
Apakah user bisa mengakses record dari organization lain?
Apakah ID langsung dapat digunakan untuk IDOR?
```

Jangan melakukan:

```php
Model::all();
Model::find($id);
```

untuk resource tenant jika query tersebut dapat melewati scope.

Gunakan scoped queries, policies, model scopes, service layer, atau kombinasi yang konsisten.

---

# 22. Audit Log

Karena aplikasi menyimpan data pribadi dan data keuangan, audit log adalah requirement penting.

Minimal audit event:

```text
login
logout
view_sensitive_data
create
update
delete
export
approve
reject
impersonation_start
impersonation_end
```

Recommended fields:

```text
audit_logs
----------
id
user_id
action
organization_id
resource_type
resource_id
ip_address
user_agent
before

after
created_at
```

Audit design harus mempertimbangkan privacy dan storage growth.

---

# 23. Impersonation untuk Support

Platform support dapat memiliki fitur impersonation untuk membantu user.

Contoh:

```text
JABNET Support
   ↓
Impersonate
   ↓
Ketua RW 01
```

Impersonation harus:

- dibatasi permission
- tercatat di audit log
- memiliki indikator yang jelas di UI
- tidak menghapus identitas operator asli dari audit trail

Jangan membuat impersonation sebagai silent login.

---

# 24. Platform Staff Tidak Sama dengan Desa User

User internal JABNET/ARKANOVA tidak perlu dipaksa berada di bawah desa.

Hierarchy:

```text
PLATFORM STAFF
├── Super Admin
├── Finance
├── Operations
├── CS
└── Support
```

Mereka dapat diberi scope platform atau scope tenant tertentu bila dibutuhkan.

Contoh:

```text
platform_support → Platform
platform_support → Desa Sukakarya
```

---

# 25. Authentication Direction

Current app menggunakan username + PIN.

Jangan menghapus/merusak mekanisme existing secara mendadak.

Target jangka panjang dapat membedakan authentication strength berdasarkan user type.

Contoh:

```text
Warga:
username + PIN

RW/Desa Admin:
username/password + optional OTP/2FA

Platform Staff:
email/password + 2FA
```

Tetapi ini adalah planning direction, bukan instruksi langsung untuk mengganti auth sekarang.

---

# 26. Queue / Background Jobs

Ketika tenant bertambah, pekerjaan seperti WhatsApp notification, bulk notification, report generation, dan import harus dipindahkan ke queue bila memang synchronous execution menjadi bottleneck.

Contoh:

```text
PaymentCreated
    ↓
Queue Job
    ↓
Notification Provider
```

Jangan menjalankan bulk WhatsApp send dalam satu HTTP request jika jumlah recipient dapat besar.

---

# 27. Scheduler

Tenant-aware scheduled jobs harus dapat memproses tenant satu per satu atau dalam batch yang aman.

Contoh:

```text
Daily Billing Job
    ↓
Load active organizations
    ↓
Process tenant batch
    ↓
Queue tenant work
```

Jangan membuat satu cron entry per desa.

---

# 28. Deployment Target

Target saat ini:

```text
cPanel server milik operator/platform
```

Satu Laravel application sebaiknya menangani banyak tenant.

Jangan membuat application instance terpisah per tenant tanpa alasan kuat.

Potential deployment structure:

```text
/home/<account>/apps/desa-platform
```

dengan public document root diarahkan ke:

```text
.../desa-platform/public
```

Actual deployment harus mengikuti kondisi cPanel server dan dokumentasi deployment project.

---

# 29. Scaling Strategy

### Phase 1

```text
Laravel
MySQL
Blade
cPanel
shared schema
```

Target: beberapa sampai puluhan desa.

### Phase 2

Tambahkan bila dibutuhkan:

```text
Redis
Queue workers
Scheduler improvements
Audit logging
Tenant resolver
Indexes
Monitoring
```

### Phase 3

Untuk skala lebih besar:

```text
Database optimization
Read-heavy optimization
Object storage
Dedicated queue workers
Centralized logs
Monitoring/alerting
```

### Phase 4

Baru pertimbangkan bila benar-benar dibutuhkan:

```text
multiple Laravel instances
load balancer
read replica
separate database workloads
```

Microservices bukan target default.

---

# 30. Database-per-Tenant Policy

Default architecture: **shared database/shared schema**.

Jangan langsung membuat database per desa.

Database per tenant hanya boleh dipertimbangkan bila ada kebutuhan kuat seperti:

- regulatory isolation
- customer-specific backup/restore
- extremely large tenant
- independent operational boundary
- contractual requirement

Jika kebutuhan seperti itu muncul, design harus dibuat sebagai extension dari tenant abstraction, bukan dengan hard-coded special cases.

---

# 31. Performance Requirements

Multi-tenant scale lebih banyak ditentukan oleh database/query design daripada framework.

Pastikan query umum mempunyai index yang sesuai.

Contoh konseptual:

```text
INDEX(desa_id)
INDEX(rw_id)
INDEX(rt_id)
INDEX(no_kk)
INDEX(desa_id, rw_id)
INDEX(desa_id, rw_id, rt_id)
```

Index aktual harus ditentukan dari query nyata dan execution plan, bukan ditambahkan secara membabi buta.

---

# 32. Security Requirements

AI Agent wajib menganggap data berikut sebagai sensitive:

```text
NIK
No KK
alamat
nomor HP
data keluarga
data keuangan
```

Security rules:

- Server-side authorization mandatory.
- Jangan expose sensitive fields jika tidak diperlukan.
- Jangan menulis sensitive data ke log biasa.
- Jangan commit CSV data warga ke Git.
- Audit sensitive actions.
- Validate file uploads.
- Protect export endpoints.
- Gunakan least privilege.
- Jangan menambahkan debug endpoint di production.

---

# 33. Git / Repository Safety

AI Agent tidak boleh:

- menghapus production data
- menjalankan destructive migration tanpa approval
- mengubah credential production
- meng-commit `.env`
- meng-commit data warga
- menghapus existing feature hanya karena architecture target berbeda
- melakukan mass rename/migration tanpa memahami dependency

Sebelum perubahan besar:

```text
1. Inspect
2. Explain impact
3. Create migration path
4. Implement incrementally
5. Test
6. Document
```

---

# 34. Backward Compatibility

Aplikasi production saat ini harus tetap bekerja.

Existing:

```text
paru.jabnet.id
```

harus dianggap sebagai legacy/current tenant/domain sampai migration dilakukan.

Jangan langsung menghapus domain lama hanya karena target domain baru adalah:

```text
desa.arkanova.id
```

Migration harus mendukung coexistence jika memungkinkan.

---

# 35. Existing Single-RW Application → Multi-Tenant Migration

Migration konseptual:

```text
CURRENT

Application
└── RW 10
    ├── users
    ├── households
    ├── members
    ├── billing
    └── settings

TARGET

Platform
└── Desa Sukakarya
    └── RW 10
        ├── users
        ├── households
        ├── members
        ├── billing
        └── settings
```

Data existing harus dipetakan ke organization/tenant yang sesuai.

Jangan melakukan migration data sebelum mapping ini jelas.

---

# 36. Recommended Development Order

Ketika nanti implementasi dimulai, urutan yang dianjurkan:

## Phase A — Discovery

- Inspect migrations.
- Inspect models.
- Inspect auth implementation.
- Inspect role middleware.
- Inspect policies.
- Inspect route structure.
- Inspect settings system.
- Inspect controllers/services.
- Identify every table containing tenant-owned data.
- Identify all queries that currently assume single RW.

## Phase B — Architecture Foundation

- Add organization hierarchy.
- Add domain mapping concept.
- Add role assignment with organization scope.
- Add current tenant/context service.
- Add authorization strategy.

## Phase C — Data Migration

- Map current RW 10 data into the organization hierarchy.
- Add required foreign keys / scope fields.
- Backfill data.
- Verify row ownership.

## Phase D — Routing

- Add hostname/domain resolution.
- Keep current domain working.
- Add future tenant domains.

## Phase E — Authorization

- Convert role checks to role + scope.
- Add Policies/scoped queries.
- Add tenant leakage tests.

## Phase F — Settings / Branding

- Tenant-aware settings.
- Branding inheritance.
- Feature flags.

## Phase G — Platform Operations

- Tenant management UI.
- User assignment UI.
- Audit logs.
- Impersonation.
- Reporting.

## Phase H — Scale

- Queue.
- Redis if required.
- Monitoring.
- DB optimization.
- Background processing.

---

# 37. Mandatory Tests for Multi-Tenancy

Before declaring multi-tenant support production-ready, tests should cover at minimum:

### Tenant isolation

```text
RW 01 cannot read RW 02 household
RW 01 cannot update RW 02 household
RW 01 cannot delete RW 02 household
Desa A cannot access Desa B data
```

### Role scope

```text
desa_admin can manage RW inside own desa
rw_admin can manage users/resources in own RW
warga can only access own household
platform staff can access according to platform permission
```

### Domain isolation

```text
unknown hostname → rejected
valid tenant hostname → correct organization
```

### IDOR prevention

```text
/resource/123
```

must not bypass organizational policy simply because an attacker guesses another record ID.

### Settings isolation

Changing Desa A branding must not alter Desa B branding.

### Audit

Sensitive operations must create expected audit entries.

---

# 38. Testing Strategy

Gunakan automated tests untuk security-critical tenancy rules.

Minimal:

```text
Feature Tests
Policy Tests
Authorization Tests
Domain Resolution Tests
Tenant Isolation Tests
```

Jangan hanya test happy path.

Test terutama:

```text
user A attempts resource owned by tenant B
```

---

# 39. AI Agent Working Rules

Saat mengerjakan project ini, AI Agent harus:

1. Membaca `AGENTS.md` sebelum mengubah code.
2. Membaca handoff/decision docs yang tersedia.
3. Memahami existing implementation sebelum refactor.
4. Memprioritaskan backward compatibility.
5. Menganggap tenant isolation sebagai security boundary.
6. Menggunakan server-side authorization.
7. Menghindari hard-coded tenant/domain.
8. Menghindari role name yang mengandung nama desa/RW.
9. Menghindari duplicate application instance per tenant.
10. Membuat perubahan kecil dan dapat diverifikasi.
11. Menambahkan automated tests untuk perubahan security/authorization.
12. Tidak melakukan destructive data migration tanpa instruksi eksplisit.
13. Tidak memindahkan production architecture ke microservices tanpa kebutuhan nyata.
14. Tidak mengganti framework hanya untuk menyelesaikan masalah tenancy.
15. Tidak menyimpulkan schema final sebelum membaca migration/model/code existing.

---

# 40. Anti-Patterns

Jangan membuat pola berikut:

```text
if ($user->role === 'rw01_admin')
```

atau:

```text
if ($hostname === 'rw-01-sukakarya...')
```

atau:

```text
DB_CONNECTION = desa_sukakarya
```

untuk setiap tenant secara hard-coded.

Jangan:

```text
copy source code → tenant baru
```

Jangan:

```text
hide button = security
```

Jangan:

```text
Model::find($id)
```

pada resource yang seharusnya scoped jika Policy/scoped query belum memastikan isolation.

---

# 41. Architectural Decision Summary

| Area | Target Decision |
|---|---|
| Framework | Laravel 12 untuk existing project |
| Frontend | Blade/server-rendered tetap |
| Tenancy | Multi-tenant |
| DB awal | Shared MySQL/shared schema |
| Organization | Platform → Desa → RW → RT |
| Household | Resource/ownership, bukan tenant |
| Authorization | Role + Permission + Scope |
| Domain | DB-backed domain mapping |
| Settings | Tenant-aware |
| Deployment | Single Laravel app di cPanel |
| Queue | Tambahkan saat workload membutuhkannya |
| Redis | Tambahkan saat workload membutuhkannya |
| Microservices | Tidak menjadi default |
| Audit | Wajib untuk sensitive/admin actions |
| Impersonation | Planned feature untuk support |
| Feature flags | Per organization |
| Custom domain | Dimungkinkan di fase berikutnya |

---

# 42. Target Mental Model untuk AI Agent

Selalu pikirkan sistem ini sebagai:

```text
                    PLATFORM
                       │
            ┌──────────┴──────────┐
            │                     │
          DESA A                DESA B
            │                     │
       ┌────┴────┐           ┌────┴────┐
      RW 01     RW 02       RW 01     RW 02
        │
       RT
        │
       KK
        │
     FAMILY
```

Dan authorization sebagai:

```text
WHO?
 ↓
ROLE
 ↓
WHAT?
 ↓
PERMISSION
 ↓
WHERE?
 ↓
ORGANIZATION/SCOPE
 ↓
WHICH RESOURCE?
```

Jika sebuah perubahan desain tidak bisa menjawab **WHO + WHAT + WHERE + RESOURCE**, desain tersebut belum siap diimplementasikan.

---

# 43. Planning Boundary

Dokumen ini **tidak mengunci** detail implementasi berikut:

- nama tabel final
- nama model final
- package authorization final
- package tenancy final
- struktur folder final
- Redis sebagai requirement wajib
- database per tenant
- pola domain final
- authentication final

Semua hal tersebut harus diputuskan setelah inspeksi codebase dan deployment environment.

---

# 44. First Task for AI Agent When Development Starts

Instruksi pertama yang aman:

> "Jangan melakukan perubahan kode. Audit codebase terlebih dahulu untuk memetakan current architecture terhadap target multi-tenant architecture dalam dokumen ini. Identifikasi model, migration, middleware, policies, routes, settings, auth, queries, dan data ownership yang saat ini masih single-RW. Buat laporan gap analysis, dependency map, migration risks, dan rekomendasi urutan implementasi."

Output audit yang diharapkan:

```text
1. Current Architecture
2. Tenant-Owned Tables
3. Current Authorization Flow
4. Single-RW Assumptions
5. Domain/Routing Assumptions
6. Required Schema Changes
7. Required Code Changes
8. Security Risks
9. Migration Risks
10. Recommended Implementation Phases
```

**Jangan menulis migration atau refactor besar sebelum audit ini selesai.**

---

# 45. Final Principle

Project ini harus berkembang dari:

```text
single RW application
```

menjadi:

```text
one platform
many desa
many RW
many RT
many households
many users
```

tanpa menggandakan codebase dan tanpa mengorbankan tenant isolation.

Prioritas arsitektur:

```text
SECURITY
→ DATA ISOLATION
→ BACKWARD COMPATIBILITY
→ MAINTAINABILITY
→ PERFORMANCE
→ SCALE
```

Jangan mengejar scale dengan complexity sebelum workload benar-benar membutuhkannya.
