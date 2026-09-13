# PRD.md — Merchant & Payout Modules Foundation

**Project:** JualAntar API  
**Scope:** Merchant Module + Payout Module  
**Version:** 1.0  
**Status:** Draft  
**Target:** MVP  
**Next implementation after:** IdentityAccess, Storage, Service

---

# 1. Executive Summary

Dokumen ini mendefinisikan kebutuhan dan batasan untuk membangun **Merchant Module** dan **Payout Module** sebagai foundation sebelum implementasi **Merchant Registration**.

Urutan pembangunan:

```text
01. IdentityAccess ✅
        ↓
02. Storage ✅
        ↓
03. Service ✅
        ↓
04. Merchant       ← current
        ↓
05. Payout         ← current
        ↓
06. Merchant Registration
        ↓
07. Merchant Approval
        ↓
08. Driver
```

Merchant Module menjadi domain utama merchant, sedangkan Payout Module menjadi capability bersama yang dapat digunakan oleh Merchant sekarang dan Driver pada tahap berikutnya.

Tujuan tahap ini adalah membangun **domain foundation**, bukan membuat wizard Merchant Registration.

---

# 2. Codebase Context

Repository:

```text
https://github.com/Rumahkodingku/jualantar-api
```

Codebase merupakan pure JSON API berbasis Laravel 13 dan PHP 8.3, menggunakan PostgreSQL, Sanctum, Spatie Permission, Pest, dan Flysystem S3-compatible storage. Repository juga menetapkan convention berupa API response envelope, RFC 9457 Problem Details, Result Pattern, API version `/v1`, Eloquent API Resources, dan architecture tests. 

IdentityAccess, Storage, dan Service sudah tersedia dan bukan fokus implementasi ulang.

Implementasi Merchant dan Payout harus mengikuti convention module yang sudah ada. Jangan membuat pola arsitektur baru hanya untuk kedua module ini.

---

# 3. Business Context

JualAntar membutuhkan Merchant sebagai salah satu actor utama platform.

Merchant dapat:

- memiliki informasi bisnis;
- menggunakan salah satu layanan JualAntar;
- memiliki satu atau lebih kategori layanan;
- memiliki satu atau lebih outlet;
- menyimpan data identitas/pengendali usaha;
- menyimpan legal entity apabila relevan;
- menyimpan dokumen pendukung secara opsional;
- memiliki rekening pencairan.

Untuk MVP, dokumen merchant **bersifat opsional** karena JualAntar masih tahap awal dan perlu membangun kepercayaan UMKM lokal.

Merchant tetap dapat mendaftar tanpa mengunggah dokumen. Dokumen dapat ditambahkan kemudian melalui fitur pengelolaan merchant.

---

# 4. Goals

## 4.1 Merchant Module

Membangun foundation domain merchant yang:

- menyimpan merchant;
- menghubungkan merchant dengan user;
- menghubungkan merchant dengan service;
- menghubungkan merchant dengan category;
- menyimpan outlet;
- menyimpan identity information;
- menyimpan legal entity jika relevan;
- menyimpan dokumen merchant secara opsional;
- memiliki lifecycle status merchant;
- siap digunakan oleh Merchant Registration dan Merchant Approval.

## 4.2 Payout Module

Membangun capability payout yang:

- menyimpan rekening pencairan;
- mendukung merchant sebagai owner;
- dirancang agar dapat mendukung Driver di masa depan;
- memiliki lifecycle status sendiri;
- memiliki relasi ke BankDirectory;
- mendukung primary payout account;
- tidak mencampurkan payout state dengan merchant registration state.

---

# 5. Non Goals

Tahap ini tidak mencakup:

- Merchant Registration wizard;
- Merchant Approval workflow;
- Product Catalog;
- Product Category;
- Order;
- Checkout;
- Payment Gateway;
- Driver;
- Driver Registration;
- Commission;
- Payout transaction;
- Settlement;
- Withdrawal processing;
- Bank transfer execution;
- Notification workflow;
- KYC provider integration.

---

# 6. Architecture Principles

## 6.1 Single Responsibility

Merchant Module menangani merchant domain.

Payout Module menangani payout account domain.

Registration dan Approval akan menjadi workflow/application concern yang menggunakan foundation kedua module.

## 6.2 Dependency Direction

Dependency yang diharapkan:

```text
Merchant ───────→ Service
Merchant ───────→ IdentityAccess
Merchant ───────→ Storage
Merchant ───────→ Payout
Payout  ────────→ BankDirectory
```

Payout tidak boleh bergantung pada implementasi Merchant.

Secara konseptual:

```text
                 ┌───────────────┐
                 │ IdentityAccess│
                 └───────┬───────┘
                         │
                         ▼
                 ┌───────────────┐
                 │    Merchant   │
                 └───────┬───────┘
                         │
                         │ uses
                         ▼
                 ┌───────────────┐
                 │    Payout     │
                 └───────┬───────┘
                         │
                         ▼
                 ┌───────────────┐
                 │ BankDirectory │
                 └───────────────┘
```

Payout tetap reusable oleh Driver di masa depan.

---

# 7. Merchant Module

## 7.1 Ownership

Merchant Module memiliki ownership terhadap:

```text
merchants
merchant_identities
legal_entities
merchant_categories
merchant_outlets
merchant_documents
```

Service Module tetap memiliki:

```text
services
categories
```

Payout Module memiliki:

```text
payout_accounts
```

---

# 8. Merchant Data Model

## 8.1 `merchants`

| Field            | Type         | Nullable | Constraint           | Description                  |
| ---------------- | ------------ | -------: | -------------------- | ---------------------------- |
| id               | UUID         |       No | PK                   | Merchant ID                  |
| user_id          | UUID         |      Yes | FK users.id          | Account owner                |
| legal_entity_id  | UUID         |      Yes | FK legal_entities.id | Legal business entity        |
| service_id       | UUID         |       No | FK services.id       | Main service                 |
| business_name    | VARCHAR(100) |       No |                      | Merchant business name       |
| slug             | VARCHAR(100) |       No | UNIQUE               | Public/system identifier     |
| description      | TEXT         |      Yes |                      | Business description         |
| type             | VARCHAR(20)  |       No |                      | individual/company           |
| logo             | VARCHAR(255) |      Yes |                      | Logo object key/reference    |
| status           | VARCHAR(20)  |       No | DEFAULT draft        | Merchant lifecycle           |
| rejection_stage  | VARCHAR(50)  |      Yes |                      | Registration rejection point |
| rejection_reason | TEXT         |      Yes |                      | Rejection explanation        |
| reviewed_at      | TIMESTAMPTZ  |      Yes |                      | Approval review time         |
| reviewed_by      | UUID         |      Yes | FK users.id          | Reviewer                     |
| created_at       | TIMESTAMPTZ  |       No |                      | Creation time                |
| updated_at       | TIMESTAMPTZ  |       No |                      | Update time                  |

Merchant status MVP:

```text
draft
pending
active
suspended
rejected
```

Status merupakan **single source of truth untuk lifecycle registration/approval merchant**.

---

# 9. Merchant Status Rules

Flow utama:

```text
draft
  ↓
pending
  ↓
active
```

Rejection:

```text
pending
  ↓
rejected
  ↓
draft / pending
```

Operational suspension:

```text
active
  ↓
suspended
```

Status merchant tidak boleh digantikan oleh status dokumen, identity, legal entity, atau outlet.

---

# 10. Merchant Identity

## 10.1 `merchant_identities`

| Field       | Type         | Nullable | Constraint |
| ----------- | ------------ | -------: | ---------- |
| id          | UUID         |       No | PK         |
| merchant_id | UUID         |       No | FK         |
| id_type     | VARCHAR(20)  |       No |            |
| id_number   | VARCHAR(50)  |       No |            |
| full_name   | VARCHAR(100) |       No |            |
| birth_date  | DATE         |      Yes |            |
| created_at  | TIMESTAMPTZ  |       No |            |
| updated_at  | TIMESTAMPTZ  |       No |            |

Tujuan:

- menyimpan data identitas individu yang terkait dengan merchant;
- memisahkan identity dari profil merchant;
- memberi ruang untuk evolusi KYC di masa depan.

Untuk MVP tidak memiliki `verification_status`, `verified_at`, atau `verified_by`.

Approval tetap dikendalikan oleh `merchants.status`.

---

# 11. Legal Entity

## 11.1 `legal_entities`

Tabel digunakan apabila merchant memiliki badan usaha/legal entity.

| Field       | Type         | Nullable | Constraint |
| ----------- | ------------ | -------: | ---------- |
| id          | UUID         |       No | PK         |
| entity_type | VARCHAR(20)  |       No |            |
| name        | VARCHAR(150) |       No |            |
| nib         | VARCHAR(100) |       No | UNIQUE     |
| npwp        | VARCHAR(25)  |       No | UNIQUE     |
| address     | TEXT         |      Yes |            |
| province_id | BIGINT       |      Yes | FK         |
| regency_id  | BIGINT       |      Yes | FK         |
| district_id | BIGINT       |      Yes | FK         |
| village_id  | BIGINT       |      Yes | FK         |
| postal_code | VARCHAR(10)  |      Yes |            |
| created_at  | TIMESTAMPTZ  |       No |            |
| updated_at  | TIMESTAMPTZ  |       No |            |

Usage:

```text
Merchant type = individual
→ legal_entity_id dapat NULL

Merchant type = company
→ legal_entity_id dapat diisi
```

Tidak semua merchant membutuhkan `legal_entities`.

---

# 12. Merchant Categories

## 12.1 `merchant_categories`

| Field       | Type        | Nullable | Constraint       |
| ----------- | ----------- | -------: | ---------------- |
| id          | UUID        |       No | PK               |
| merchant_id | UUID        |       No | FK merchants.id  |
| category_id | UUID        |       No | FK categories.id |
| created_at  | TIMESTAMPTZ |       No |                  |
| updated_at  | TIMESTAMPTZ |       No |                  |

Unique constraint:

```text
UNIQUE(merchant_id, category_id)
```

Jangan membuat unique pada `merchant_id` atau `category_id` secara individual.

Satu merchant dapat memiliki beberapa kategori dan satu kategori dapat digunakan banyak merchant.

Batas jumlah kategori, bila ada, diterapkan pada application/domain layer.

---

# 13. Merchant Outlets

## 13.1 `merchant_outlets`

| Field             | Type          | Nullable | Constraint     |
| ----------------- | ------------- | -------: | -------------- |
| id                | UUID          |       No | PK             |
| merchant_id       | UUID          |       No | FK             |
| name              | VARCHAR(100)  |       No |                |
| phone             | VARCHAR(20)   |      Yes |                |
| email             | VARCHAR(100)  |      Yes |                |
| address           | TEXT          |       No |                |
| province_id       | BIGINT        |       No | FK             |
| regency_id        | BIGINT        |       No | FK             |
| district_id       | BIGINT        |       No | FK             |
| village_id        | BIGINT        |       No | FK             |
| postal_code       | VARCHAR(10)   |       No |                |
| latitude          | DECIMAL(10,8) |       No |                |
| longitude         | DECIMAL(11,8) |       No |                |
| service_area_type | VARCHAR(20)   |       No |                |
| service_radius_km | DECIMAL(5,2)  |      Yes |                |
| operating_hours   | JSONB         |      Yes |                |
| status            | VARCHAR(20)   |       No | DEFAULT active |
| created_at        | TIMESTAMPTZ   |       No |                |
| updated_at        | TIMESTAMPTZ   |       No |                |

Relationship:

```text
Merchant 1 ──────────── N MerchantOutlet
```

Outlet status MVP:

```text
active
inactive
```

`status` menunjukkan apakah outlet aktif pada platform.

Status buka/tutup saat ini dihitung dari `operating_hours`, bukan disimpan sebagai `is_open`.

---

# 14. Merchant Documents

## 14.1 `merchant_documents`

Dokumen merchant **OPSIONAL** untuk MVP.

| Field         | Type         | Nullable | Constraint |
| ------------- | ------------ | -------: | ---------- |
| id            | UUID         |       No | PK         |
| merchant_id   | UUID         |       No | FK         |
| document_type | VARCHAR(50)  |       No |            |
| file_name     | VARCHAR(255) |       No |            |
| object_key    | VARCHAR(500) |       No |            |
| mime_type     | VARCHAR(100) |       No |            |
| file_size     | BIGINT       |       No |            |
| created_at    | TIMESTAMPTZ  |       No |            |
| updated_at    | TIMESTAMPTZ  |       No |            |

Relationship:

```text
Merchant 1 ──────────── 0..N MerchantDocuments
```

Artinya merchant valid tanpa memiliki document.

Registration tidak boleh gagal hanya karena:

```text
merchant_documents = 0
```

Dokumen dapat ditambahkan kemudian.

Dokumen menggunakan Storage Module dan object storage private. Database menyimpan metadata dan `object_key`, bukan public URL permanen.

---

# 15. Payout Module

## 15.1 Purpose

Payout Module menangani **rekening pencairan**, bukan proses transfer uang.

MVP fokus pada:

- payout account;
- bank reference;
- primary account;
- account status;
- verification metadata.

Settlement, withdrawal, dan bank transfer execution berada di luar scope.

---

# 16. Payout Data Model

## 16.1 `payout_accounts`

| Field            | Type         | Nullable | Constraint        | Description           |
| ---------------- | ------------ | -------: | ----------------- | --------------------- |
| id               | UUID         |       No | PK                | Payout account ID     |
| owner_type       | VARCHAR(20)  |       No | merchant / driver | Owner type            |
| owner_id         | UUID         |       No |                   | Owner UUID            |
| bank_id          | UUID         |       No | FK banks.id       | Bank                  |
| account_number   | VARCHAR(50)  |       No |                   | Bank account number   |
| account_name     | VARCHAR(150) |       No |                   | Account holder        |
| is_primary       | BOOLEAN      |       No | DEFAULT true      | Primary account       |
| status           | VARCHAR(20)  |       No | DEFAULT pending   | Account lifecycle     |
| rejection_reason | TEXT         |      Yes |                   | Rejection explanation |
| verified_at      | TIMESTAMPTZ  |      Yes |                   | Verification time     |
| verified_by      | UUID         |      Yes | FK users.id       | Verifier              |
| created_at       | TIMESTAMPTZ  |       No |                   | Creation time         |
| updated_at       | TIMESTAMPTZ  |       No |                   | Update time           |

---

# 17. Payout Owner Model

Payout menggunakan polymorphic ownership secara konseptual:

```text
owner_type
owner_id
```

MVP:

```text
owner_type = merchant
```

Future:

```text
owner_type = driver
```

Tujuannya supaya Driver tidak membutuhkan tabel payout terpisah.

Contoh:

```text
payout_accounts
│
├── owner_type = merchant
│   owner_id = merchant UUID
│
└── owner_type = driver
    owner_id = driver UUID
```

Payout Module tidak boleh bergantung pada `Merchant` atau `Driver` model hanya untuk menentukan owner.

---

# 18. Payout Status

MVP:

```text
pending
active
rejected
```

Flow:

```text
pending
   ↓
active
```

atau:

```text
pending
   ↓
rejected
```

Payout status berdiri sendiri.

Contoh valid:

```text
merchant.status = active
payout_account.status = pending
```

Business rule withdrawal/settlement nantinya dapat mensyaratkan payout account active.

---

# 19. Primary Payout Account

Satu owner maksimal memiliki satu primary payout account.

Business rule:

```text
owner_type + owner_id
        ↓
maksimal 1 is_primary = true
```

PostgreSQL partial unique index direkomendasikan:

```text
UNIQUE(owner_type, owner_id)
WHERE is_primary = true
```

Apabila project memilih enforcement application-side, tetap pertimbangkan protection di database untuk mencegah race condition.

---

# 20. Bank Reference

Payout menggunakan:

```text
BankDirectory
```

sebagai master bank.

Dependency:

```text
Payout → BankDirectory
```

Jangan membuat master bank baru di Payout Module.

`bank_id` tidak unique karena bank yang sama dapat digunakan oleh banyak payout account.

---

# 21. Relationship Overview

```text
users
   │
   │ 1
   ▼
merchants
   │
   ├─────────────── N merchant_categories N ───── categories
   │
   ├─────────────── N merchant_outlets
   │
   ├────────────── 0..N merchant_documents
   │
   ├────────────── 0..1 merchant_identity
   │
   ├────────────── 0..1 legal_entity
   │
   ├────────────── 0..N payout_accounts
   │
   └────────────── 1 service
```

Future:

```text
driver 1 ───────────── N payout_accounts
```

---

# 22. Merchant Module Structure

Mengikuti struktur Modular Monolith project.

```text
Modules/
└── Merchant/
    ├── Application/
    │   ├── Actions/
    │   └── Concerns/
    ├── Contracts/
    ├── Database/
    │   ├── Factories/
    │   ├── Migrations/
    │   └── Seeders/
    ├── Domain/
    │   ├── Exceptions/
    │   └── Models/
    │       ├── Merchant.php
    │       ├── MerchantIdentity.php
    │       ├── LegalEntity.php
    │       ├── MerchantCategory.php
    │       ├── MerchantOutlet.php
    │       └── MerchantDocument.php
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   └── Resources/
    ├── Infrastructure/
    │   └── Repositories/
    ├── Routes/
    │   └── api.php
    ├── Tests/
    │   ├── Feature/
    │   └── Unit/
    └── ServiceProvider.php
```

Final structure harus mengikuti actual convention module existing.

---

# 23. Payout Module Structure

```text
Modules/
└── Payout/
    ├── Application/
    │   ├── Actions/
    │   └── Concerns/
    ├── Contracts/
    ├── Database/
    │   ├── Factories/
    │   ├── Migrations/
    │   └── Seeders/
    ├── Domain/
    │   ├── Exceptions/
    │   └── Models/
    │       └── PayoutAccount.php
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   └── Resources/
    ├── Infrastructure/
    │   └── Repositories/
    ├── Routes/
    │   └── api.php
    ├── Tests/
    │   ├── Feature/
    │   └── Unit/
    └── ServiceProvider.php
```

Payout tidak boleh mengimpor Merchant model hanya untuk menyelesaikan ownership.

---

# 24. API Scope

Pada tahap foundation, API tidak perlu mencakup seluruh Merchant Registration.

Mutation workflow Registration dan Approval ditunda.

## Merchant Foundation

```text
GET /v1/admin/merchants
GET /v1/admin/merchants/{merchant}
```

Endpoint final disesuaikan dengan kebutuhan dan convention project.

## Payout Foundation

```text
GET /v1/admin/payout-accounts
GET /v1/admin/payout-accounts/{payoutAccount}
```

Create/update payout account yang berkaitan langsung dengan onboarding dapat diimplementasikan bersama Merchant Registration.

Tujuannya mencegah workflow duplikat sebelum Registration tersedia.

---

# 25. Storage Integration

Merchant menggunakan Storage melalui contract untuk:

```text
merchant.logo
merchant_documents
```

Jangan mengakses implementation storage langsung dari Domain.

Recommended object keys:

```text
merchants/{merchant_uuid}/logo/{asset_uuid}
merchants/{merchant_uuid}/documents/{document_uuid}
```

Original filename bukan object key.

---

# 26. API Architecture

Gunakan:

```text
Route
  ↓
Controller
  ↓
Application Service/Action
  ↓
Domain
  ↓
Infrastructure
```

Controller tidak berisi business logic.

Response menggunakan `ApiResponse`.

Error menggunakan RFC 9457 Problem Details.

Expected business failures mengikuti Result Pattern project.

---

# 27. Model Conventions

Model mengikuti project convention Laravel 13:

```php
#[Fillable([...])]
#[Hidden([...])]
```

dan:

```php
protected function casts(): array
```

Bukan `$fillable` / `$hidden` properties.

---

# 28. Validation Rules

## Merchant

```text
service_id       required|exists
business_name    required|string|max:100
slug             required|string|max:100|unique
type             required|string
description      nullable|string
logo             nullable
```

## Merchant Category

```text
merchant_id  required|exists
category_id  required|exists
```

Duplicate relationship harus ditolak.

## Merchant Outlet

Minimal:

```text
merchant_id
name
address
province_id
regency_id
district_id
village_id
postal_code
latitude
longitude
service_area_type
```

## Merchant Document

Keseluruhan document record optional terhadap Merchant.

Ketika file ditambahkan, metadata berikut wajib:

```text
merchant_id
document_type
file_name
object_key
mime_type
file_size
```

## Payout Account

```text
owner_type
owner_id
bank_id
account_number
account_name
is_primary
```

---

# 29. Authorization

Authorization menggunakan IdentityAccess/Spatie Permission.

Contoh permission:

```text
merchants.view
merchants.update

payout_accounts.view
payout_accounts.create
payout_accounts.update
payout_accounts.activate
payout_accounts.reject
```

Final permission naming harus mengikuti convention permission existing.

---

# 30. Testing Strategy

## Merchant

Minimal test:

- merchant dapat dibuat;
- merchant mereferensikan service valid;
- duplicate slug ditolak;
- merchant category relationship bekerja;
- duplicate merchant/category ditolak;
- merchant dapat memiliki multiple outlets;
- outlet dapat aktif/inactive;
- merchant dapat dibuat tanpa documents;
- merchant dapat memiliki multiple documents;
- legal entity optional;
- merchant status transition mengikuti rules;
- rejection metadata tersimpan;
- authorization bekerja.

## Payout

Minimal test:

- payout account dapat dibuat;
- payout account mereferensikan bank valid;
- merchant owner dapat digunakan;
- default status pending;
- payout dapat menjadi active;
- payout dapat menjadi rejected;
- rejection reason tersimpan;
- primary account uniqueness enforced;
- bank yang sama dapat digunakan oleh banyak payout account;
- payout status tidak mengubah merchant status secara otomatis;
- authorization bekerja.

---

# 31. Architecture Tests

Pastikan:

```text
Merchant → Service
Merchant → Storage Contract
Payout → BankDirectory
```

Tidak boleh:

```text
Service → Merchant
Payout → Merchant implementation
Merchant → Driver
Merchant → Catalog
Payout → Driver implementation
```

Driver belum menjadi dependency pada tahap ini.

---

# 32. Migration Order

Karena terdapat foreign key dependency:

```text
legal_entities
        ↓
merchants
```

atau migration order lain yang setara harus memastikan target table tersedia sebelum FK dibuat.

Hindari circular relationship:

```text
merchants ↔ legal_entities
```

Gunakan:

```text
merchants.legal_entity_id → legal_entities.id
```

Foreign key tambahan dari `legal_entities` ke `merchants` tidak diperlukan untuk MVP.

---

# 33. Soft Delete

Soft delete tidak wajib untuk seluruh table pada MVP.

Lifecycle utama menggunakan status:

```text
Merchant.status
Outlet.status
PayoutAccount.status
```

Documents tidak membutuhkan approval lifecycle.

Soft delete dapat ditambahkan kemudian bila audit/history membutuhkan.

---

# 34. Merchant Documents Optional Policy

Keputusan bisnis:

```text
Merchant Registration
        │
        ├── merchant data        REQUIRED
        ├── service              REQUIRED
        ├── category             REQUIRED
        ├── outlet               REQUIRED
        ├── payout account       Business Rule
        └── documents            OPTIONAL
```

Dokumen tidak menjadi syarat teknis pembuatan Merchant pada MVP.

Merchant Approval nantinya dapat menentukan dokumen tertentu wajib untuk kasus tertentu tanpa mengubah seluruh foundation menjadi document-required.

---

# 35. Registration Compatibility

Foundation harus mendukung future flow:

```text
Merchant Registration
        │
        ├── Create Merchant
        ├── Attach Service
        ├── Attach Categories
        ├── Create Outlet
        ├── Attach Identity / Legal Entity
        ├── Upload Documents (optional)
        └── Create Payout Account
                        │
                        ▼
                    pending
```

Kemudian:

```text
Merchant Approval
        │
        ├── approve → active
        │
        └── reject  → rejected
```

---

# 36. Implementation Phases

## Phase 1 — Merchant Foundation

Implement:

```text
Merchant ServiceProvider
Migrations
Models
Relationships
Factories
Seeders
Domain rules
```

## Phase 2 — Merchant Read Foundation

Implement minimal management/read API yang dibutuhkan.

## Phase 3 — Payout Foundation

Implement:

```text
Payout ServiceProvider
Migration
PayoutAccount model
Bank reference
Factory
Status rules
Primary account rule
```

## Phase 4 — Authorization & API

Implement management endpoints yang memang diperlukan.

## Phase 5 — Tests

Implement:

```text
Feature tests
Domain tests
Authorization tests
Architecture tests
```

## Phase 6 — Readiness Review

Pastikan Merchant dan Payout siap digunakan oleh Merchant Registration tanpa temporary table atau duplicate business logic.

---

# 37. Definition of Done — Merchant

- [ ] Merchant module tersedia.
- [ ] `merchants` migration selesai.
- [ ] `merchant_identities` migration selesai.
- [ ] `legal_entities` migration selesai.
- [ ] `merchant_categories` migration selesai.
- [ ] `merchant_outlets` migration selesai.
- [ ] `merchant_documents` migration selesai.
- [ ] Merchant documents optional.
- [ ] Merchant model selesai.
- [ ] Relationships selesai.
- [ ] Service relationship selesai.
- [ ] Category relationship selesai.
- [ ] Outlet relationship selesai.
- [ ] Document relationship selesai.
- [ ] Identity relationship selesai.
- [ ] Legal entity relationship selesai.
- [ ] Merchant status rules tersedia.
- [ ] Storage contract digunakan untuk logo/documents.
- [ ] Factories tersedia.
- [ ] Seeders tersedia.
- [ ] Feature tests lulus.
- [ ] Architecture tests lulus.

---

# 38. Definition of Done — Payout

- [ ] Payout module tersedia.
- [ ] `payout_accounts` migration selesai.
- [ ] Bank reference selesai.
- [ ] PayoutAccount model selesai.
- [ ] Owner abstraction selesai.
- [ ] Merchant owner dapat digunakan.
- [ ] Status lifecycle selesai.
- [ ] Primary payout account rule selesai.
- [ ] Rejection metadata selesai.
- [ ] Factories tersedia.
- [ ] Authorization tersedia.
- [ ] Feature tests lulus.
- [ ] Architecture tests lulus.
- [ ] Tidak ada dependency Payout → Merchant.

---

# 39. Final Architecture

Setelah tahap ini:

```text
IdentityAccess ✅
       │
       ├──────────────┐
       ▼              │
Storage ✅            │
       │              │
       ▼              ▼
Service ✅         Merchant
                      │
                      ├──── Merchant Identity
                      ├──── Legal Entity
                      ├──── Merchant Category
                      ├──── Merchant Outlet
                      ├──── Merchant Documents
                      │
                      ▼
                    Payout
                      │
                      ▼
                 BankDirectory
```

Future:

```text
                     Payout
                     ▲
                     │
             ┌───────┴───────┐
             │               │
          Merchant         Driver
```

---

# 40. Roadmap After Completion

```text
01. IdentityAccess ✅
        ↓
02. Storage ✅
        ↓
03. Service ✅
        ↓
04. Merchant ✅
        ↓
05. Payout ✅
        ↓
06. Merchant Registration ← NEXT
        ↓
07. Merchant Approval
        ↓
08. Driver
```

Merchant Registration tidak boleh membuat ulang:

- merchant table;
- service/category master;
- storage integration;
- payout account table;
- bank master.

Semua foundation tersebut harus dikonsumsi dari module yang sudah tersedia.

---

# 41. Success Criteria

Tahap Merchant + Payout berhasil apabila Merchant Registration nantinya dapat melakukan:

```text
User
 ↓
Create Merchant
 ↓
Select Service
 ↓
Select Categories
 ↓
Create Outlet
 ↓
Provide Identity / Legal Entity when applicable
 ↓
Upload Documents (optional)
 ↓
Create Payout Account
 ↓
Submit Merchant
 ↓
Merchant.status = pending
```

tanpa membuat persistence layer baru di luar Merchant dan Payout Module.
