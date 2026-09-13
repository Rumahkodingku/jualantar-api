# PRD.md — Service Module

**Project:** JualAntar API  
**Module:** Service  
**Version:** 1.0  
**Status:** Draft  
**Priority:** High  
**Target:** MVP

---

## 1. Overview

Service Module adalah master/reference module yang mengelola jenis layanan JualAntar beserta kategori layanan.

Module ini menjadi sumber referensi bagi module lain, terutama Merchant dan Merchant Registration.

### Scope MVP

Service Module bertanggung jawab terhadap:

1. Master layanan (`services`)
2. Master kategori layanan (`categories`)
3. Relasi Service → Category
4. Public/read API untuk kebutuhan aplikasi
5. Admin management API untuk mengelola master data

Service Module tidak bertanggung jawab terhadap Merchant, Product Catalog, Order, Driver, Pricing, Delivery, Promotion, atau Merchant Category Selection.

---

## 2. Problem Statement

JualAntar memiliki beberapa jenis layanan seperti JAfood, JAmart, JAride, dan Jastip. Setiap layanan dapat memiliki beberapa kategori.

Tanpa master data terpusat, module lain berisiko menyimpan service/category secara hardcoded, mengalami duplikasi data, dan sulit menambah atau menonaktifkan layanan.

Service Module menjadi single source of truth untuk master service dan service category.

---

## 3. Goals

### Primary Goals

- Menyimpan master service.
- Menyimpan category berdasarkan service.
- Mengaktifkan/nonaktifkan service.
- Mengaktifkan/nonaktifkan category.
- Menyediakan daftar service aktif.
- Menyediakan category aktif berdasarkan service.
- Menjadi reference bagi Merchant Registration.

### Secondary Goals

Fondasi harus memungkinkan penambahan service/category baru dan perubahan metadata tanpa coupling dengan Merchant, Driver, Catalog, atau Order.

---

## 4. Non Goals

Tidak termasuk MVP:

- Product Catalog
- Product Category
- Merchant Category
- Merchant Service Subscription
- Service Pricing
- Delivery Fee
- Commission
- Promotion
- Order
- Driver Assignment
- Service Area
- Operating Hours
- Merchant-specific service configuration

---

## 5. Domain Concept

### Service

Service adalah jenis layanan utama platform.

Contoh:

- JAfood
- JAmart
- JAride
- Jastip

### Category

Category adalah klasifikasi di bawah sebuah Service.

Contoh:

```text
JAfood
├── Makanan
├── Minuman
├── Snack
└── Dessert
```

Relationship:

```text
Service 1 ──────────── * Category
```

---

## 6. Data Model

### 6.1 `services`

| Field       | Type         | Nullable | Constraint   | Description          |
| ----------- | ------------ | -------: | ------------ | -------------------- |
| id          | UUID         |       No | PK           | Service identifier   |
| name        | VARCHAR(100) |       No |              | Service name         |
| slug        | VARCHAR(100) |       No | UNIQUE       | System identifier    |
| description | TEXT         |       No |              | Service description  |
| icon        | VARCHAR(100) |       No |              | Icon identifier      |
| is_active   | BOOLEAN      |       No | DEFAULT true | Service availability |
| created_at  | TIMESTAMPTZ  |       No |              | Creation timestamp   |
| updated_at  | TIMESTAMPTZ  |       No |              | Update timestamp     |

### 6.2 `categories`

| Field       | Type         | Nullable | Constraint     | Description           |
| ----------- | ------------ | -------: | -------------- | --------------------- |
| id          | UUID         |       No | PK             | Category identifier   |
| service_id  | UUID         |       No | FK services.id | Parent service        |
| name        | VARCHAR(100) |       No |                | Category name         |
| slug        | VARCHAR(100) |       No | UNIQUE         | System identifier     |
| description | TEXT         |       No |                | Category description  |
| icon        | VARCHAR(100) |       No |                | Icon identifier       |
| is_active   | BOOLEAN      |       No | DEFAULT true   | Category availability |
| created_at  | TIMESTAMPTZ  |       No |                | Creation timestamp    |
| updated_at  | TIMESTAMPTZ  |       No |                | Update timestamp      |

Required indexes:

```text
services.slug UNIQUE
categories.slug UNIQUE
categories.service_id INDEX
```

---

## 7. Data Rules

### Service

- `name`, `slug`, `description`, dan `icon` wajib.
- `slug` harus unique.
- Service baru default aktif.
- Service inactive tidak ditampilkan pada public API.
- Menonaktifkan service tidak otomatis menghapus category.

### Category

- `service_id` wajib.
- Category harus memiliki parent Service.
- `name`, `slug`, `description`, dan `icon` wajib.
- `slug` harus unique.
- Category baru default aktif.
- Category inactive tidak ditampilkan pada public API.
- Category dari service inactive tidak ditampilkan pada public API.

---

## 8. Slug Rules

Slug:

- lowercase;
- menggunakan hyphen;
- tidak menggunakan whitespace;
- unique;
- tidak menggunakan karakter khusus yang tidak diperlukan.

Contoh:

```text
JAfood → jafood
Kebutuhan Rumah → kebutuhan-rumah
```

---

## 9. API Standards

API harus mengikuti convention JualAntar:

- API version `/v1`;
- Controller tipis;
- Business/use-case logic pada Application layer;
- Domain logic pada Domain layer;
- Validation menggunakan Form Request;
- Response menggunakan API Resource;
- Response mengikuti API response envelope project;
- Error mengikuti RFC 9457 Problem Details;
- Authorization dilakukan server-side.

---

# 10. Public API

Public API digunakan oleh frontend/mobile dan module seperti Merchant Registration.

## 10.1 List Active Services

```http
GET /v1/services
```

Hanya mengembalikan:

```text
is_active = true
```

Contoh response:

```json
{
    "data": [
        {
            "id": "uuid",
            "name": "JAfood",
            "slug": "jafood",
            "description": "Layanan makanan dan minuman",
            "icon": "utensils"
        }
    ]
}
```

## 10.2 Get Active Service

```http
GET /v1/services/{service}
```

Service inactive tidak dianggap tersedia melalui public API.

## 10.3 List Active Categories

```http
GET /v1/services/{service}/categories
```

Category hanya ditampilkan apabila:

```text
service.is_active = true
AND
category.is_active = true
```

---

# 11. Admin API

Admin API membutuhkan authentication dan permission yang sesuai.

Recommended permissions:

```text
services.view
services.create
services.update
services.activate
services.deactivate

categories.view
categories.create
categories.update
categories.activate
categories.deactivate
```

## Service Management

```http
GET    /v1/admin/services
POST   /v1/admin/services
GET    /v1/admin/services/{service}
PUT    /v1/admin/services/{service}
POST   /v1/admin/services/{service}/activate
POST   /v1/admin/services/{service}/deactivate
```

Admin list mendukung:

- pagination;
- search;
- filter status;
- sorting.

## Category Management

```http
GET    /v1/admin/categories
GET    /v1/admin/categories/{category}
POST   /v1/admin/services/{service}/categories
PUT    /v1/admin/categories/{category}
POST   /v1/admin/categories/{category}/activate
POST   /v1/admin/categories/{category}/deactivate
```

Admin category list mendukung:

- pagination;
- search;
- filter service;
- filter status;
- sorting.

---

# 12. Delete Strategy

Deactivation menjadi strategi utama untuk data yang sudah digunakan module lain.

```text
Active
  ↓
Deactivate
  ↓
Inactive
```

Hard delete hanya digunakan apabila data belum memiliki dependency atau memang diperlukan untuk maintenance.

Tidak boleh menghapus service/category secara sembarangan jika sudah direferensikan oleh module lain.

---

# 13. Module Boundary

Service Module hanya memiliki ownership terhadap:

```text
services
categories
```

Tidak memiliki:

```text
merchants
merchant_categories
products
product_categories
orders
drivers
payout_accounts
```

---

# 14. Relationship With Merchant

Merchant Registration membutuhkan Service sebagai reference.

Flow:

```text
Service Module
      │
      │ master/reference
      ▼
Merchant Registration
      │
      ▼
Merchant Module
```

Merchant dapat memilih:

```text
service_id
```

dan beberapa:

```text
category_id
```

Namun relasi pilihan merchant disimpan oleh Merchant Module melalui:

```text
merchant_categories
```

Service Module tidak mengetahui Merchant.

Dependency harus satu arah:

```text
Merchant → Service
```

bukan:

```text
Service ↔ Merchant
```

---

# 15. Service Category vs Product Category

Category pada Service Module adalah **service/business category**.

Contoh:

```text
JAfood
├── Makanan
├── Minuman
└── Snack
```

Product Category nantinya merupakan bagian dari Catalog Module.

Contoh:

```text
Makanan
├── Nasi
├── Mie
├── Ayam
└── Seafood
```

Keduanya tidak boleh digabung.

Future structure:

```text
Service Module
├── services
└── categories

Merchant Module
├── merchants
└── merchant_categories

Catalog Module
├── products
└── product_categories
```

---

# 16. Module Structure

Service Module harus mengikuti struktur Modular Monolith yang sudah digunakan project.

Recommended:

```text
Modules/
└── Service/
    ├── Application/
    │   ├── Actions/
    │   │   ├── CreateService.php
    │   │   ├── UpdateService.php
    │   │   ├── ActivateService.php
    │   │   ├── DeactivateService.php
    │   │   ├── CreateCategory.php
    │   │   ├── UpdateCategory.php
    │   │   ├── ActivateCategory.php
    │   │   └── DeactivateCategory.php
    │   └── Concerns/
    ├── Contracts/
    ├── Database/
    │   ├── Factories/
    │   ├── Migrations/
    │   └── Seeders/
    ├── Domain/
    │   ├── Exceptions/
    │   └── Models/
    │       ├── Service.php
    │       └── Category.php
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

Implementasi final harus mengikuti convention module existing di repository dan tidak membuat abstraction yang tidak diperlukan.

---

# 17. Application Layer

Application layer menangani use case:

```text
CreateService
UpdateService
ActivateService
DeactivateService

CreateCategory
UpdateCategory
ActivateCategory
DeactivateCategory
```

Application layer tidak boleh mengetahui detail HTTP.

---

# 18. Domain Layer

Domain memiliki:

```text
Service
Category
```

Relationships:

```text
Service::categories()
Category::service()
```

Invariant utama:

```text
Category harus selalu memiliki Service.
```

---

# 19. Infrastructure Layer

Infrastructure menangani persistence implementation apabila abstraction repository memang digunakan oleh convention project.

Jangan membuat repository abstraction hanya demi formalitas.

Gunakan pola yang konsisten dengan module reference yang sudah ada.

---

# 20. Validation

## Service

```text
name: string|max:100|required
slug: string|max:100|required|unique
description: string|required
icon: string|max:100|required
```

## Category

```text
service_id: required|exists
name: string|max:100|required
slug: string|max:100|required|unique
description: string|required
icon: string|max:100|required
```

---

# 21. Authorization

Public:

```text
GET /v1/services
GET /v1/services/{service}
GET /v1/services/{service}/categories
```

Admin management:

```text
authentication
+
appropriate permission
```

Frontend tidak boleh menjadi satu-satunya lapisan authorization.

---

# 22. Error Handling

Gunakan RFC 9457 Problem Details.

Contoh:

```text
404 Not Found
Service tidak ditemukan.

404 Not Found
Category tidak ditemukan.

422 Unprocessable Entity
Slug sudah digunakan.

401 Unauthorized
Authentication diperlukan.

403 Forbidden
User tidak memiliki permission.
```

---

# 23. Seeder

Initial MVP service dapat mencakup:

```text
JAfood
JAmart
JAride
Jastip
```

Contoh category:

```text
JAfood
├── Makanan
├── Minuman
├── Snack
└── Dessert

JAmart
├── Sembako
├── Minuman
├── Kebutuhan Rumah
└── Personal Care
```

Kategori JAride/Jastip hanya dibuat jika memang dibutuhkan oleh business requirement.

Jangan membuat data dummy yang tidak memiliki fungsi.

Seeder harus idempotent dan aman dijalankan kembali sesuai convention project.

---

# 24. Testing Requirements

## Service

- Create service.
- Update service.
- Duplicate slug ditolak.
- Activate service.
- Deactivate service.
- Public API hanya menampilkan active service.
- Inactive service tidak tersedia melalui public API.

## Category

- Create category.
- Category wajib memiliki service.
- Update category.
- Duplicate slug ditolak.
- Activate category.
- Deactivate category.
- Public API hanya menampilkan active category.
- Category dari inactive service tidak ditampilkan.

## Authorization

- Unauthenticated request ke admin API ditolak.
- User tanpa permission ditolak.
- User dengan permission dapat melakukan operation sesuai permission.

## Architecture

- Controller tidak berisi business logic.
- Dependency direction tetap benar.
- Service tidak bergantung pada Merchant, Driver, Payout, Catalog, atau Order.

---

# 25. Performance

Public endpoint harus ringan karena digunakan oleh frontend/mobile.

Requirements:

- Hindari N+1 query.
- Gunakan index pada foreign key.
- Gunakan unique index pada slug.
- Gunakan pagination pada admin list.
- Hindari query yang tidak diperlukan.

---

# 26. Security

Admin API harus:

- membutuhkan authentication;
- membutuhkan authorization;
- melakukan server-side validation;
- tidak mempercayai input frontend;
- membatasi response field sesuai kebutuhan.

Public API tidak boleh mengekspos data internal yang tidak diperlukan.

---

# 27. Observability

Audit log khusus tidak wajib untuk MVP.

Operation penting dapat menggunakan standard application logging apabila mekanisme tersebut sudah tersedia:

```text
Create Service
Update Service
Deactivate Service

Create Category
Update Category
Deactivate Category
```

Audit trail khusus dapat ditambahkan kemudian.

---

# 28. Definition of Done

## Database

- [ ] `services` migration tersedia.
- [ ] `categories` migration tersedia.
- [ ] FK `categories.service_id`.
- [ ] Unique index `services.slug`.
- [ ] Unique index `categories.slug`.
- [ ] Index `categories.service_id`.

## Domain

- [ ] Service model.
- [ ] Category model.
- [ ] Service → categories relationship.
- [ ] Category → service relationship.

## Application

- [ ] Create Service.
- [ ] Update Service.
- [ ] Activate Service.
- [ ] Deactivate Service.
- [ ] Create Category.
- [ ] Update Category.
- [ ] Activate Category.
- [ ] Deactivate Category.

## HTTP

- [ ] Public service API.
- [ ] Public category API.
- [ ] Admin service API.
- [ ] Admin category API.
- [ ] Form Requests.
- [ ] API Resources.
- [ ] API response mengikuti standard project.

## Authorization

- [ ] Permission service tersedia.
- [ ] Permission category tersedia.
- [ ] Unauthorized request ditolak.
- [ ] Forbidden request ditolak.

## Seeder

- [ ] Initial service seeder.
- [ ] Initial category seeder.

## Testing

- [ ] Feature tests.
- [ ] Validation tests.
- [ ] Authorization tests.
- [ ] Public API tests.
- [ ] Admin API tests.
- [ ] Architecture tests.

---

# 29. Implementation Phases

## Phase 1 — Foundation

```text
ServiceProvider
Migrations
Models
Relationships
Factories
Seeders
```

## Phase 2 — Public API

```text
GET /v1/services
GET /v1/services/{service}
GET /v1/services/{service}/categories
```

## Phase 3 — Admin Management

```text
Service CRUD
Category CRUD
Activate/Deactivate
Authorization
```

## Phase 4 — Testing

```text
Feature Tests
Authorization Tests
Validation Tests
Architecture Tests
```

---

# 30. MVP API Summary

## Public

```text
GET /v1/services
GET /v1/services/{service}
GET /v1/services/{service}/categories
```

## Admin

```text
GET  /v1/admin/services
POST /v1/admin/services
GET  /v1/admin/services/{service}
PUT  /v1/admin/services/{service}
POST /v1/admin/services/{service}/activate
POST /v1/admin/services/{service}/deactivate

GET  /v1/admin/categories
GET  /v1/admin/categories/{category}
POST /v1/admin/services/{service}/categories
PUT  /v1/admin/categories/{category}
POST /v1/admin/categories/{category}/activate
POST /v1/admin/categories/{category}/deactivate
```

---

# 31. Future Extension

Dapat ditambahkan kemudian:

- Service ordering.
- Category ordering.
- Service metadata.
- Service image.
- Service localization.
- Category localization.
- Service-specific configuration.
- Service availability.

Fitur tersebut bukan bagian MVP.

---

# 32. Roadmap Dependency

Roadmap implementasi JualAntar yang digunakan:

```text
01. IdentityAccess
        ↓
02. Storage
        ↓
03. Service
        ↓
04. Merchant
        ↓
05. Merchant Registration
        ↓
06. Merchant Approval
        ↓
07. Payout
        ↓
08. Driver
```

Driver sengaja ditempatkan setelah Payout.

Alasannya adalah Payout merupakan capability bersama yang nantinya dapat digunakan oleh Merchant maupun Driver. Dengan membangun Payout lebih dahulu, implementasi Driver nantinya dapat langsung menggunakan payout account abstraction yang sudah tersedia tanpa membuat ulang mekanisme pencairan.

Driver tidak menjadi dependency Service Module dan tidak perlu dibuat pada tahap ini.

---

# 33. Final Architecture Decision

Service Module adalah master/reference module.

Ownership:

```text
Service Module
├── services
└── categories
```

Dependency:

```text
Service
   ↑
   │ referenced by
   │
Merchant
   │
   └── Merchant Registration
```

Service Module tidak mengetahui Merchant.

Merchant Module dapat mengetahui Service.

```text
Merchant → Service
```

bukan:

```text
Service ↔ Merchant
```

---

# 34. Success Criteria

Service Module dianggap berhasil apabila Merchant Registration dapat melakukan:

```text
GET /v1/services
        ↓
User memilih JAfood
        ↓
GET /v1/services/{service}/categories
        ↓
User memilih:
- Makanan
- Minuman
- Snack
        ↓
Merchant Registration menyimpan:
service_id
+
category_id
```

tanpa hardcoded service/category pada Merchant Registration.

---

# 35. Next Step

Setelah Service Module selesai dan seluruh test lulus:

```text
Service
   ↓
Merchant Foundation
   ↓
Merchant Registration
   ↓
Merchant Approval
   ↓
Payout
   ↓
Driver
```

Product Catalog tetap berada di luar scope sampai Merchant foundation dan registration workflow stabil.
