# PRD — Merchant Registration

## 1. Identitas

- **Product:** JualAntar
- **Module:** `app/Modules/Merchant`
- **Feature:** Merchant Registration
- **API Base:** `/api/v1/merchants/registration`
- **Scope:** MVP
- **Status:** Ready for Implementation

## 2. Tujuan

Merchant Registration menyediakan proses pendaftaran merchant secara bertahap (wizard), mulai dari membuat draft sampai merchant mengajukan pendaftaran untuk diproses oleh Merchant Approval.

Registration merupakan capability/use case di dalam **Merchant Module**, bukan module terpisah.

## 3. Prinsip Arsitektur

JualAntar menggunakan **Modular Monolith**.

Merchant Registration tetap berada di:

```text
app/Modules/Merchant
```

Registration bertindak sebagai application workflow/orchestrator dan menggunakan domain model Merchant yang sudah ada.

Module lain hanya diakses melalui Contract:

```text
Merchant
├── IdentityAccess → user identity
├── Service       → service/category
├── Geography     → wilayah
├── Storage       → object storage
├── Payout        → payout account
└── BankDirectory → melalui Payout
```

Tidak membuat tabel atau model duplikat seperti `merchant_registrations` yang menyimpan ulang seluruh data Merchant.

## 4. Scope

### In Scope

- Create merchant draft
- Resume existing draft
- Update registration
- Data usaha
- Identitas pemilik
- Legal entity
- Service
- Category
- Outlet
- Presigned upload
- Dokumen pendukung
- Payout account
- Review registration
- Final validation
- Submit registration
- Ownership authorization
- Transactional submit
- Feature/unit/architecture tests

### Out of Scope

- Merchant Approval
- KYC provider integration
- Catalog/Product
- Order
- Payment
- Settlement/disbursement
- Wallet
- Commission
- Driver registration

## 5. Status Workflow

Registration menggunakan `MerchantStatus` sebagai source of truth.

```text
draft → pending
```

Registration hanya menangani:

```text
draft → pending
```

Lifecycle berikutnya ditangani Merchant Approval/Merchant lifecycle:

```text
pending → active
pending → rejected
rejected → draft/pending
active → suspended
suspended → active
```

Submit tidak boleh dilakukan jika merchant bukan `draft`.

## 6. Alur Wizard

1. Pilih Jenis Usaha
2. Akun
3. Data Usaha
4. Pilih Layanan
5. Kategori Usaha
6. Lokasi Usaha
7. Dokumen Pendukung
8. Rekening Pencairan
9. Tinjau Data
10. Submit → `pending`

Akun user dikelola oleh IdentityAccess. Registration mengaitkan merchant dengan authenticated user.

## 7. Data Ownership

| Data              | Owner          |
| ----------------- | -------------- |
| User              | IdentityAccess |
| Merchant          | Merchant       |
| Merchant Identity | Merchant       |
| Legal Entity      | Merchant       |
| Merchant Category | Merchant       |
| Category master   | Service        |
| Outlet            | Merchant       |
| Document metadata | Merchant       |
| File              | Storage        |
| Payout Account    | Payout         |
| Bank              | BankDirectory  |
| Region            | Geography      |

## 8. API Design

Base resource:

```text
/api/v1/merchants/registration
```

### Create Draft

```http
POST /api/v1/merchants/registration
```

Response:

```json
{
    "data": {
        "merchant_id": "uuid",
        "status": "draft"
    }
}
```

Jika user sudah memiliki registration draft yang aktif, jangan membuat duplicate.

### Get Current Registration

```http
GET /api/v1/merchants/registration
```

Mengembalikan registration milik authenticated user.

### Update Merchant Data

```http
PATCH /api/v1/merchants/registration
```

Contoh:

```json
{
    "business_name": "Warung Borneo",
    "type": "individual",
    "description": "Warung makanan lokal"
}
```

Slug dibuat oleh Application layer dari `business_name`, bukan dikirim client.

## 9. Identity

```http
PUT /api/v1/merchants/registration/identity
```

Payload:

```json
{
    "id_type": "ktp",
    "id_number": "6171xxxxxxxxxxxx",
    "full_name": "Nama Pemilik",
    "birth_date": "2000-01-01"
}
```

Rules:

- satu merchant maksimal satu identity;
- `merchant_identities.merchant_id` unique;
- identity wajib sebelum submit.

## 10. Legal Entity

```http
PUT /api/v1/merchants/registration/legal-entity
```

Rules:

- `company` → legal entity wajib;
- `individual` → legal entity boleh null;
- geography divalidasi melalui Geography Contract;
- NIB/NPWP mengikuti constraint Merchant module.

## 11. Service

```http
PUT /api/v1/merchants/registration/service
```

```json
{
    "service_id": "uuid"
}
```

Validasi melalui `ServiceLookup`.

Service harus aktif.

## 12. Category

```http
PUT /api/v1/merchants/registration/categories
```

```json
{
    "category_ids": ["uuid-1", "uuid-2"]
}
```

Rules:

- minimal 1 category saat submit;
- maksimal 3 category;
- category harus aktif;
- category harus milik service yang dipilih;
- duplicate category ditolak.

## 13. Outlet

```http
POST /api/v1/merchants/registration/outlets
PATCH /api/v1/merchants/registration/outlets/{outlet}
DELETE /api/v1/merchants/registration/outlets/{outlet}
```

Rules:

- minimal 1 active outlet saat submit;
- geography divalidasi melalui Contract;
- `operating_hours` disimpan sebagai JSONB;
- status outlet `active/inactive`;
- `is_open` tidak disimpan.

## 14. Storage & Presigned Upload

File tidak dikirim sebagai bagian dari payload registration utama.

Registration meminta upload URL melalui Storage Contract.

```http
POST /api/v1/merchants/registration/uploads
```

Payload:

```json
{
    "purpose": "logo",
    "file_name": "logo.png",
    "mime_type": "image/png",
    "file_size": 102400
}
```

Backend menggunakan:

```text
ObjectStorage::temporaryUploadUrl()
```

Response:

```json
{
    "data": {
        "object_key": "merchant/{uuid}/logo.png",
        "upload_url": "https://..."
    }
}
```

Frontend meng-upload file langsung ke Cloudflare R2.

Registration tidak menerima binary file.

## 15. Logo

Logo disimpan sebagai object key:

```text
merchants/{merchant_id}/logo/{uuid}.png
```

Tidak menyimpan public URL permanen.

Untuk response yang membutuhkan akses gambar gunakan:

```text
ObjectStorage::temporaryUrl()
```

setelah authorization.

## 16. Documents

Dokumen **optional pada MVP**.

```http
POST /api/v1/merchants/registration/documents
```

Payload:

```json
{
    "document_type": "ktp",
    "object_key": "merchant/{uuid}/documents/ktp.jpg",
    "file_name": "ktp.jpg",
    "mime_type": "image/jpeg",
    "file_size": 245678
}
```

Metadata disimpan pada `merchant_documents`.

File fisik berada di Storage/R2.

Registration tidak menentukan dokumen wajib untuk approval. Kebijakan tersebut menjadi tanggung jawab Merchant Approval/Compliance.

## 17. Payout Account

```http
PUT /api/v1/merchants/registration/payout-account
```

Payload:

```json
{
    "bank_id": "bank-id",
    "account_number": "1234567890",
    "account_name": "Nama Pemilik"
}
```

Validasi bank menggunakan Contract Payout → BankDirectory.

Payout status independen dari Merchant:

```text
Merchant = pending
Payout   = pending
```

Keduanya valid.

## 18. Review

```http
GET /api/v1/merchants/registration/review
```

Response menampilkan:

- Merchant
- Identity
- Legal Entity
- Service
- Categories
- Outlets
- Documents
- Payout Account

Resolusi lintas module menggunakan Contract:

- ServiceLookup → service name
- GeographyLookup → region names
- BankLookup → bank name
- ObjectStorage → temporary URLs

Tidak membuat Eloquent relation lintas module hanya untuk resolusi data.

## 19. Submit

```http
POST /api/v1/merchants/registration/submit
```

Payload:

```json
{}
```

Final validation minimal:

```text
business_name       required
type                required
service_id          required
identity            required
category            1..3
active outlet       >= 1
payout account      required
documents           optional
legal_entity        required for company
```

Jika valid:

```text
Merchant.status: draft → pending
```

Gunakan database transaction.

## 20. Authorization

Semua endpoint membutuhkan:

```text
auth:sanctum
```

User hanya boleh mengakses merchant registration miliknya sendiri.

```text
merchant.user_id == authenticated_user.id
```

Admin/super admin tidak menggunakan self-registration untuk approval.

## 21. Idempotency

Submit harus aman terhadap double submit.

Jika status sudah `pending`, request submit ulang tidak boleh membuat merchant/submission baru.

Create draft juga harus mencegah duplicate active registration milik user.

## 22. Application Structure

```text
app/Modules/Merchant/
├── Application/
│   ├── Actions/
│   │   ├── RegisterMerchant.php
│   │   ├── UpdateMerchantRegistration.php
│   │   ├── SaveMerchantIdentity.php
│   │   ├── SaveLegalEntity.php
│   │   ├── SaveMerchantCategories.php
│   │   ├── CreateMerchantOutlet.php
│   │   ├── UpdateMerchantOutlet.php
│   │   ├── CreateRegistrationUpload.php
│   │   ├── AttachMerchantDocument.php
│   │   ├── SavePayoutAccount.php
│   │   └── SubmitMerchantRegistration.php
│   └── Concerns/
├── Contracts/
├── Domain/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   │   └── MerchantRegistration/
│   └── Resources/
├── Infrastructure/
├── Routes/
│   └── api.php
└── Tests/
    ├── Feature/
    └── Unit/
```

Sesuaikan nama file dengan convention sibling code yang sudah ada.

## 23. Module Boundaries

Merchant Registration boleh menggunakan capability dari:

```text
Service
Geography
Storage
Payout
IdentityAccess
```

melalui Contract.

Tidak boleh mengakses implementation/domain model module lain secara langsung untuk bypass boundary.

Same-module Eloquent relationships tetap diperbolehkan.

## 24. Error Handling

Ikuti standar project:

- Result Pattern
- `ApiResponse`
- RFC 9457 Problem Details
- error code terdaftar di `config/api.php`

Contoh:

```text
merchant_registration_not_found
merchant_registration_already_exists
invalid_registration_state
registration_incomplete
invalid_service
invalid_category
invalid_geography
invalid_payout_account
upload_invalid
```

## 25. Testing

### Feature

- create registration;
- get current registration;
- update draft;
- ownership authorization;
- identity;
- legal entity;
- service;
- category;
- outlet;
- document;
- payout;
- review;
- submit.

### Validation

- company tanpa legal entity;
- individual tanpa legal entity;
- category kosong;
- category > 3;
- category dari service berbeda;
- service tidak aktif;
- outlet kosong;
- tidak ada active outlet;
- payout tidak ada;
- bank tidak valid;
- geography tidak valid;
- invalid upload metadata;
- documents kosong tetap valid.

### Domain

- valid/invalid status transition;
- slug generation;
- slug collision;
- duplicate identity;
- duplicate merchant/category;
- submit ulang pada pending.

### Storage

Gunakan fake `ObjectStorage`. Tidak perlu R2 sungguhan untuk test suite.

## 26. Architecture Tests

Pastikan Merchant tetap mematuhi module boundaries:

```text
Merchant
├── Service     → Contract
├── Geography   → Contract
├── Storage     → Contract
├── Payout      → Contract
└── IdentityAccess → Contract/capability
```

Update `tests/Arch/*` hanya jika diperlukan.

## 27. Implementation Phases

1. Registration Foundation
2. Draft + Merchant Data
3. Identity + Legal Entity
4. Service + Category
5. Outlet + Geography
6. Storage + Presigned Upload
7. Documents
8. Payout
9. Review
10. Submit
11. Authorization + Architecture Tests
12. Quality / Readiness Review

## 28. Definition of Done

- [ ] User authenticated dapat membuat registration draft.
- [ ] Draft dapat disimpan dan dilanjutkan.
- [ ] Data disimpan oleh owner module masing-masing.
- [ ] Tidak ada tabel `merchant_registrations` duplikatif.
- [ ] Registration berada di `app/Modules/Merchant`.
- [ ] Presigned upload menggunakan Storage Contract.
- [ ] File dapat di-upload langsung ke R2.
- [ ] Dokumen optional.
- [ ] Payout account terintegrasi melalui Contract.
- [ ] Review menampilkan data lengkap.
- [ ] Cross-module data menggunakan Contract.
- [ ] Submit melakukan final validation.
- [ ] Submit mengubah `draft → pending`.
- [ ] Ownership authorization berjalan.
- [ ] Double submit aman.
- [ ] Result Pattern diterapkan.
- [ ] RFC 9457 diterapkan.
- [ ] Eloquent API Resource digunakan.
- [ ] Architecture tests lulus.
- [ ] Feature/unit tests lulus.
- [ ] Pint lulus.

## 29. Final Architectural Decisions

1. Merchant Registration berada di `Modules/Merchant`.
2. Tidak ada `MerchantRegistration` module.
3. Tidak ada tabel `merchant_registrations` yang menduplikasi Merchant.
4. `RegisterMerchant` merupakan Application Action.
5. `Merchant.status = draft` menjadi state registration.
6. Submit mengubah `draft → pending`.
7. Approval adalah capability berikutnya.
8. Presigned upload digunakan untuk file.
9. File disimpan pada private Cloudflare R2.
10. Merchant menyimpan `object_key`, bukan binary file.
11. Dokumen optional.
12. Payout Account tetap dimiliki Payout module.
13. Cross-module access melalui Contract.
14. Catalog/Product dan Driver berada di luar scope.
