# PRD — Merchant Operations API P0

**Status:** Draft / Implementation Specification  
**Project:** JualAntar API  
**Module:** Merchant Operations (capability inside Merchant module)  
**Scope:** RESTful API untuk operasional merchant pasca-approval — P0 only

---

## 1. Executive Summary

JualAntar API menggunakan Laravel Modular Monolith dengan business capability di `app/Modules/*` dan Shared Kernel di `app/Shared/*`.

Merchant module saat ini sudah dipisahkan menjadi capability seperti:

- Account
- Registration
- Approval
- Catalog
- Domain
- Database
- Routes
- Tests

Merchant Operations adalah capability **post-approval** untuk mengelola operasional merchant setelah registration dan approval selesai.

P0 mencakup:

1. Merchant Operational Status
2. Merchant Profile Management
3. Outlet Management
4. Outlet User & Role Management
5. Operating Hours Management
6. Service Area Management
7. Operational Availability

P1 seperti Temporary Closure, Operational Settings, dan Operational Audit History tidak diimplementasikan pada PRD ini.

---

## 2. Codebase Baseline

Repository:

`https://github.com/Rumahkodingku/jualantar-api`

Teknologi/pola yang sudah digunakan:

- Laravel 13
- PHP 8.3
- PostgreSQL
- Laravel Sanctum
- Spatie Permission
- Pest
- Modular Monolith
- `ApiResponse`
- Result pattern
- RFC 9457 Problem Details
- Scramble/OpenAPI

Merchant module:

```text
app/Modules/Merchant/
├── Application/
│   ├── Account/
│   ├── Approval/
│   ├── Common/
│   └── Registration/
├── Database/
├── Domain/
├── Http/
│   ├── Account/
│   ├── Approval/
│   ├── Catalog/
│   └── Registration/
├── Infrastructure/
├── Routes/
├── Tests/
└── MerchantServiceProvider.php
```

Merchant Operations harus tetap berada di `app/Modules/Merchant`; jangan membuat top-level `MerchantOperations` module.

---

## 3. Existing Merchant Domain

### Merchant

Model:

`App\Modules\Merchant\Domain\Models\Merchant`

Table:

`merchant.merchants`

Field penting:

- `user_id`
- `legal_entity_id`
- `service_id`
- `business_name`
- `slug`
- `description`
- `type`
- `logo`
- `status`

`MerchantStatus` saat ini:

```text
inactive
active
suspended
```

Transition:

```text
inactive  -> active
active    -> suspended
suspended -> active
```

`MerchantStatus` adalah **operational status**. Approval lifecycle tetap berada pada `MerchantApplicationStatus`.

### Merchant Outlet

Model:

`App\Modules\Merchant\Domain\Models\MerchantOutlet`

Table:

`merchant.merchant_outlets`

Existing fields:

- `merchant_id`
- `name`
- `phone`
- `email`
- `address`
- `province_id`
- `regency_id`
- `district_id`
- `village_id`
- `postal_code`
- `latitude`
- `longitude`
- `service_area_type`
- `service_radius_km`
- `operating_hours`
- `photos`
- `status`

Existing enums:

```text
OutletStatus:
- active
- inactive

OutletServiceAreaType:
- radius
- province
- regency
- district
- village
```

### Existing Registration Outlet API

Saat ini:

```http
POST   /api/v1/merchants/registration/outlets
PATCH  /api/v1/merchants/registration/outlets/{outlet}
DELETE /api/v1/merchants/registration/outlets/{outlet}
```

Endpoint tersebut tetap menjadi bagian Registration.

Merchant Operations harus menggunakan endpoint post-approval sendiri.

---

## 4. Product Goal

### Owner

Dapat:

- melihat/mengelola merchant operational status;
- update operational profile;
- list/create/update outlet;
- activate/deactivate outlet;
- assign employee;
- assign Outlet Manager/Outlet Staff;
- remove employee;
- change employee role;
- manage operating hours;
- manage service area;
- melihat availability.

### Outlet Manager

Hanya pada outlet yang ditugaskan:

- view outlet;
- update outlet sesuai permission;
- manage operating hours;
- manage service area;
- view availability;
- fitur employee management hanya jika permission diberikan.

### Outlet Staff

P0 hanya:

- melihat outlet yang ditugaskan;
- membaca operational information sesuai permission.

Tidak memiliki mutation konfigurasi Merchant Operations P0 secara default.

---

## 5. Scope

### P0.1 Merchant Operational Status

- get status;
- activate;
- suspend;
- reactivate;
- validate transition.

### P0.2 Merchant Profile Management

- get profile;
- update business name;
- update description;
- update logo;
- operational contact bila persistence final sudah ditentukan.

### P0.3 Outlet Management

- list;
- detail;
- create;
- update;
- activate;
- deactivate.

### P0.4 Outlet User & Role Management

- list;
- assign user;
- remove user;
- assign Outlet Manager;
- assign Outlet Staff;
- change role;
- outlet-level authorization.

### P0.5 Operating Hours

- get;
- update;
- per-day schedule;
- closed day;
- validation.

### P0.6 Service Area

- get;
- update;
- radius;
- province;
- regency;
- district;
- village;
- Geography validation.

### P0.7 Operational Availability

- get availability;
- derive `open|closed`;
- expose reason;
- evaluate merchant status;
- evaluate outlet status;
- evaluate operating hours.

### Out of Scope

- Temporary Closure
- Operational Settings
- Operational Audit History
- Catalog/Product
- Ordering
- Payment/Payout
- Dispatch/Driver
- Merchant Registration
- Merchant Approval
- push/email/SMS/WhatsApp
- realtime/WebSocket
- custom role builder

---

## 6. Recommended Structure

```text
app/Modules/Merchant/
├── Application/
│   └── Operations/
│       ├── Actions/
│       │   ├── ActivateMerchant.php
│       │   ├── SuspendMerchant.php
│       │   ├── ReactivateMerchant.php
│       │   ├── UpdateMerchantOperationalProfile.php
│       │   ├── CreateOutlet.php
│       │   ├── UpdateOutlet.php
│       │   ├── ActivateOutlet.php
│       │   ├── DeactivateOutlet.php
│       │   ├── AssignOutletUser.php
│       │   ├── RemoveOutletUser.php
│       │   ├── ChangeOutletUserRole.php
│       │   ├── UpdateOperatingHours.php
│       │   └── UpdateServiceArea.php
│       ├── Queries/
│       └── Services/
│           ├── MerchantOperationsAuthorization.php
│           └── OperationalAvailabilityResolver.php
│
├── Http/
│   └── Operations/
│       ├── MerchantOperationsController.php
│       ├── OutletOperationsController.php
│       ├── OutletUsersController.php
│       ├── OperatingHoursController.php
│       ├── ServiceAreaController.php
│       ├── Requests/
│       └── Resources/
│
└── Tests/
```

Jumlah class tidak harus persis seperti contoh. Boundary capability dan single-use-case action lebih penting.

---

## 7. Authorization Model

MVP menggunakan tiga role:

```text
owner
outlet_manager
outlet_staff
```

Role berbeda dari outlet assignment.

```text
Merchant
├── Owner
└── Employees
    ├── Outlet Manager
    └── Outlet Staff
```

**Critical rule:** outlet assignment adalah authorization boundary, bukan sekadar UI filtering.

User yang hanya assigned ke Outlet A tidak boleh mengakses Outlet B.

### IdentityAccess

Merchant tidak boleh mengimpor:

```text
App\Modules\IdentityAccess\Domain\*
```

Gunakan contract existing:

- `App\Modules\IdentityAccess\Contracts\Authorization`
- `App\Modules\IdentityAccess\Contracts\UserLookup`

---

## 8. Permission Set

Recommended permissions:

```text
merchant.operations.view
merchant.operations.status.update
merchant.operations.profile.update

merchant.operations.outlets.view
merchant.operations.outlets.create
merchant.operations.outlets.update
merchant.operations.outlets.status.update

merchant.operations.outlet_users.view
merchant.operations.outlet_users.assign
merchant.operations.outlet_users.remove
merchant.operations.outlet_users.role.update

merchant.operations.hours.view
merchant.operations.hours.update

merchant.operations.service_area.view
merchant.operations.service_area.update

merchant.operations.availability.view
```

Baseline:

| Permission               | Owner | Manager | Staff |
| ------------------------ | :---: | :-----: | :---: |
| operations.view          |   ✓   |    ✓    |   ✓   |
| status.update            |   ✓   |    —    |   —   |
| profile.update           |   ✓   |    —    |   —   |
| outlets.view             |   ✓   |    ✓    |   ✓   |
| outlets.create           |   ✓   |    —    |   —   |
| outlets.update           |   ✓   |   ✓\*   |   —   |
| outlets.status.update    |   ✓   |   ✓\*   |   —   |
| outlet_users.view        |   ✓   |   ✓\*   |   —   |
| outlet_users.assign      |   ✓   |   ✓\*   |   —   |
| outlet_users.remove      |   ✓   |   ✓\*   |   —   |
| outlet_users.role.update |   ✓   |   ✓\*   |   —   |
| hours.view               |   ✓   |    ✓    |   ✓   |
| hours.update             |   ✓   |    ✓    |   —   |
| service_area.view        |   ✓   |    ✓    |   ✓   |
| service_area.update      |   ✓   |    ✓    |   —   |
| availability.view        |   ✓   |    ✓    |   ✓   |

`*` tetap dibatasi outlet assignment.

---

## 9. API Standards

Base:

```text
/api/v1
```

Authentication:

```text
auth:sanctum
```

Gunakan infrastructure existing:

- `ApiResponse::success()`
- `ApiResponse::paginated()`
- `ApiResponse::fromResult()`
- Result pattern
- RFC 9457 Problem Details

Jangan membuat response envelope baru.

---

# 10. REST API Specification

## 10.1 Merchant Operations Summary

```http
GET /api/v1/merchant/operations
```

Permission:

`merchant.operations.view`

Response minimum:

```json
{
    "data": {
        "merchant": {
            "id": "uuid",
            "business_name": "Ayam Bakar Sederhana",
            "status": "active"
        },
        "operational": {
            "status": "active"
        }
    }
}
```

Digunakan sebagai bootstrap Merchant Operations.

---

## 10.2 Activate Merchant

```http
POST /api/v1/merchant/operations/activate
```

Permission:

`merchant.operations.status.update`

Transition:

```text
inactive -> active
```

---

## 10.3 Suspend Merchant

```http
POST /api/v1/merchant/operations/suspend
```

Permission:

`merchant.operations.status.update`

Optional payload:

```json
{
    "reason": "Alasan suspend"
}
```

Jangan membuat persistence `reason` baru tanpa keputusan domain.

---

## 10.4 Reactivate Merchant

```http
POST /api/v1/merchant/operations/reactivate
```

Permission:

`merchant.operations.status.update`

Transition:

```text
suspended -> active
```

Gunakan `MerchantStatus::canTransitionTo()`.

---

# 11. Merchant Profile

## GET

```http
GET /api/v1/merchant/operations/profile
```

## PATCH

```http
PATCH /api/v1/merchant/operations/profile
```

P0 fields:

- `business_name`
- `description`
- `logo`
- operational contact bila persistence final tersedia.

Current Merchant model sudah memiliki business name, description, dan logo.

Current schema belum memiliki dedicated operational phone/email/website.

**Implementation wajib menentukan source of truth terlebih dahulu; jangan mengarang persistence.**

Logo harus menggunakan Storage contract.

---

# 12. Outlet Management

## List

```http
GET /api/v1/merchant/operations/outlets
```

Query:

```text
search
status
page
per_page
```

Scope:

- Owner -> semua outlet merchant
- Manager -> assigned outlets
- Staff -> assigned outlets

## Detail

```http
GET /api/v1/merchant/operations/outlets/{outlet}
```

## Create

```http
POST /api/v1/merchant/operations/outlets
```

Owner only.

Payload minimum:

```json
{
    "name": "Outlet Putussibau",
    "phone": "+6281234567890",
    "email": "outlet@example.com",
    "address": "Jl. Diponegoro",
    "province_id": 61,
    "regency_id": 6106,
    "district_id": 610601,
    "village_id": 6106012001,
    "postal_code": "78711",
    "latitude": 0.8421,
    "longitude": 112.9321
}
```

`merchant_id` berasal dari server context, bukan client.

## Update

```http
PATCH /api/v1/merchant/operations/outlets/{outlet}
```

General fields:

- name
- phone
- email
- address
- province_id
- regency_id
- district_id
- village_id
- postal_code
- latitude
- longitude
- photos

Operating hours dan service area memiliki endpoint khusus.

## Activate

```http
POST /api/v1/merchant/operations/outlets/{outlet}/activate
```

## Deactivate

```http
POST /api/v1/merchant/operations/outlets/{outlet}/deactivate
```

Outlet status:

```text
active <-> inactive
```

Outlet status != merchant status != availability.

---

# 13. Outlet User & Role Management

## Persistence

Current schema belum memiliki persistence untuk employee/outlet assignment. Untuk P0, gunakan **satu tabel baru saja** karena seluruh kebutuhan employee management yang masuk scope P0 bersifat outlet-scoped.

Recommended table:

```text
merchant.merchant_outlet_users
```

Schema minimum:

```text
id
merchant_id
outlet_id
user_id
role
created_at
updated_at
```

Role yang didukung:

```text
outlet_manager
outlet_staff
```

### Mengapa hanya satu tabel?

Owner sudah memiliki relasi langsung pada:

```text
merchant.merchants.user_id
```

Sehingga owner tidak perlu dibuat sebagai employee assignment.

Assignment employee selalu memiliki outlet scope:

```text
Merchant
├── Outlet A
│   ├── User X -> outlet_manager
│   └── User Y -> outlet_staff
└── Outlet B
    └── User Z -> outlet_staff
```

Tidak diperlukan tabel global `merchant_user_assignments` pada P0.

### Cross-module user reference

`user_id` adalah reference ke IdentityAccess dan **tidak boleh memiliki physical FK** ke tabel user milik module IdentityAccess.

Internal FK yang diperbolehkan:

```text
merchant_id -> merchant.merchants.id
outlet_id  -> merchant.merchant_outlets.id
```

### Integrity rules

- `merchant_id` harus sama dengan `merchant_id` dari `outlet_id`.
- `user_id` harus diverifikasi melalui `IdentityAccess.Contracts.UserLookup`.
- Satu user tidak boleh memiliki duplicate assignment pada outlet yang sama.
- Role hanya boleh `outlet_manager` atau `outlet_staff`.
- Assignment ke owner tidak dilakukan melalui endpoint employee.
- Menghapus assignment tidak menghapus user IdentityAccess.

Recommended unique constraint:

```text
UNIQUE (outlet_id, user_id)
```

Recommended indexes:

```text
(outlet_id, user_id)
(merchant_id, user_id)
(merchant_id, outlet_id)
```

P0 tidak membutuhkan kolom `status`. Remove berarti menghapus assignment. Jika di masa depan dibutuhkan disabled-but-retained assignment atau historical audit, desain tersebut dapat ditambahkan sebagai bagian P1 tanpa memaksakan persistence pada P0.

## List

```http
GET /api/v1/merchant/operations/outlets/{outlet}/users
```

## Assign

```http
POST /api/v1/merchant/operations/outlets/{outlet}/users
```

Payload:

```json
{
    "user_id": "uuid",
    "role": "outlet_manager"
}
```

Allowed roles:

```text
outlet_manager
outlet_staff
```

Tidak dapat assign `owner`.

## Change Role

```http
PATCH /api/v1/merchant/operations/outlets/{outlet}/users/{user}
```

```json
{
    "role": "outlet_staff"
}
```

## Remove

```http
DELETE /api/v1/merchant/operations/outlets/{outlet}/users/{user}
```

Remove assignment, bukan delete IdentityAccess user.

---

# 14. Operating Hours

## GET

```http
GET /api/v1/merchant/operations/outlets/{outlet}/operating-hours
```

## PUT

```http
PUT /api/v1/merchant/operations/outlets/{outlet}/operating-hours
```

Payload:

```json
{
    "monday": {
        "is_open": true,
        "open": "08:00",
        "close": "22:00"
    },
    "tuesday": {
        "is_open": true,
        "open": "08:00",
        "close": "22:00"
    },
    "wednesday": {
        "is_open": true,
        "open": "08:00",
        "close": "22:00"
    },
    "thursday": {
        "is_open": true,
        "open": "08:00",
        "close": "23:00"
    },
    "friday": {
        "is_open": true,
        "open": "08:00",
        "close": "23:00"
    },
    "saturday": {
        "is_open": true,
        "open": "08:00",
        "close": "23:00"
    },
    "sunday": {
        "is_open": false
    }
}
```

Validation:

- `HH:mm`;
- `close > open`;
- closed day tidak membutuhkan time;
- unknown day key ditolak;
- overnight schedule tidak boleh diinterpretasikan diam-diam.

---

# 15. Service Area

Existing types:

```text
radius
province
regency
district
village
```

## GET

```http
GET /api/v1/merchant/operations/outlets/{outlet}/service-area
```

## PUT

```http
PUT /api/v1/merchant/operations/outlets/{outlet}/service-area
```

Radius:

```json
{
    "type": "radius",
    "radius_km": 5
}
```

Province:

```json
{
    "type": "province",
    "province_id": 61
}
```

Regency:

```json
{
    "type": "regency",
    "regency_id": 6106
}
```

District:

```json
{
    "type": "district",
    "district_id": 610601
}
```

Village:

```json
{
    "type": "village",
    "village_id": 6106012001
}
```

Geography validation wajib memakai:

`App\Modules\Geography\Contracts\GeographyLookup`

Jangan import Geography Domain Model.

Hierarchy harus valid:

```text
province
  ↓
regency
  ↓
district
  ↓
village
```

---

# 16. Operational Availability

Availability adalah **derived state**, bukan manual persisted state.

```text
Merchant Status
       ↓
Outlet Status
       ↓
Operating Hours
       ↓
Availability
```

State:

```text
open
closed
```

Recommended resolver:

`App\Modules\Merchant\Application\Operations\Services\OperationalAvailabilityResolver`

Response:

```json
{
    "data": {
        "status": "open",
        "reason": null,
        "merchant_status": "active",
        "outlet_status": "active",
        "schedule": {
            "open": "08:00",
            "close": "22:00"
        }
    }
}
```

Possible reasons:

```text
merchant_inactive
merchant_suspended
outlet_inactive
outside_operating_hours
scheduled_closed
```

## GET

```http
GET /api/v1/merchant/operations/outlets/{outlet}/availability
```

P0 tidak membuat endpoint manual untuk menyimpan `open`/`closed`.

Temporary Closure belum menjadi input availability pada P0.

---

# 17. Resources

Recommended:

```text
MerchantOperationalSummaryResource
MerchantOperationalStatusResource
MerchantOperationalProfileResource
MerchantOutletOperationsResource
OutletUserResource
OperatingHoursResource
ServiceAreaResource
OperationalAvailabilityResource
```

Cross-module data harus melalui DTO/contract.

---

# 18. Form Requests

Recommended:

```text
ActivateMerchantRequest.php
SuspendMerchantRequest.php
UpdateMerchantOperationalProfileRequest.php
StoreOperationalOutletRequest.php
UpdateOperationalOutletRequest.php
AssignOutletUserRequest.php
ChangeOutletUserRoleRequest.php
UpdateOperatingHoursRequest.php
UpdateServiceAreaRequest.php
```

Rules:

- enum validation;
- UUID validation;
- cross-field validation;
- reject unknown fields;
- ownership derived server-side.

---

# 19. Application Actions

Mutation use cases sebaiknya single-purpose:

```text
ActivateMerchant
SuspendMerchant
ReactivateMerchant

UpdateMerchantOperationalProfile

CreateOutlet
UpdateOutlet
ActivateOutlet
DeactivateOutlet

AssignOutletUser
RemoveOutletUser
ChangeOutletUserRole

UpdateOperatingHours
UpdateServiceArea
```

Controller harus tipis dan mendelegasikan business use case ke Action.

---

# 20. Authorization Service

Recommended:

`MerchantOperationsAuthorization`

Responsibilities:

- resolve merchant context;
- check merchant ownership;
- check outlet assignment;
- check capability;
- prevent cross-merchant access.

Service ini tidak boleh menjadi God Service dan tidak boleh menampung seluruh business logic.

---

# 21. Business Rules

### Merchant

1. Approval lifecycle tidak boleh diubah oleh Operations.
2. Status transition harus mengikuti `MerchantStatus::canTransitionTo()`.
3. Owner memiliki merchant scope penuh.
4. Manager/Staff tidak boleh memperoleh Owner privilege lewat assignment API.

### Outlet

1. Outlet harus berada dalam merchant scope.
2. Outlet status tidak mengubah merchant status.
3. Inactive outlet tidak available.
4. Cross-merchant access ditolak.

### User Assignment

1. Identity dimiliki IdentityAccess.
2. Assignment tidak menghapus identity.
3. Duplicate assignment harus dicegah database.
4. Owner tidak dapat di-downgrade melalui endpoint employee.
5. Hanya role `outlet_manager` dan `outlet_staff`.
6. Outlet scope selalu dicek server-side.

### Operating Hours

1. Schedule valid.
2. Closed day tidak memerlukan jam.
3. Invalid time range ditolak.
4. Overnight semantics tidak diasumsikan.

### Service Area

1. Radius membutuhkan `radius_km`.
2. Non-radius membutuhkan geographic ID sesuai type.
3. Geography hierarchy harus valid.
4. Field yang tidak relevan dengan selected type tidak boleh digunakan sebagai source of truth.

### Availability

1. Merchant inactive/suspended => closed.
2. Outlet inactive => closed.
3. Di luar operating hours => closed.
4. Dalam schedule dan seluruh prerequisite active => open.

---

# 22. Error Cases

Expected codes:

| Code                        | HTTP | Condition                          |
| --------------------------- | ---: | ---------------------------------- |
| merchant_not_found          |  404 | Merchant context tidak ditemukan   |
| outlet_not_found            |  404 | Outlet tidak ditemukan dalam scope |
| forbidden                   |  403 | Capability tidak dimiliki          |
| outlet_scope_forbidden      |  403 | Outlet bukan assignment scope      |
| invalid_status_transition   |  422 | Transition invalid                 |
| invalid_operating_hours     |  422 | Schedule invalid                   |
| invalid_service_area        |  422 | Area invalid                       |
| invalid_geography           |  422 | Geography invalid                  |
| duplicate_outlet_assignment |  409 | Duplicate assignment               |
| user_not_found              |  404 | Identity user tidak ditemukan      |

Gunakan mekanisme Result/Problem Details existing.

---

# 23. Database Migration Rules

Migration baru berada di:

`app/Modules/Merchant/Database/Migrations`

Schema:

`merchant`

Assignment table:

```text
merchant.merchant_outlet_users
```

Indexes:

```text
- outlet_id, user_id
- merchant_id, user_id
- merchant_id, outlet_id
```

Unique constraint:

```text
UNIQUE (outlet_id, user_id)
```

`merchant_id` dan `outlet_id` menggunakan internal FK ke tabel Merchant module.

`user_id` adalah cross-module reference dan **tidak boleh** memiliki physical FK ke `identity_access.users`.

Migration juga harus menjaga konsistensi bahwa assignment tidak boleh mencampurkan merchant dan outlet yang berbeda. Karena PostgreSQL tidak menggunakan cross-module user FK, validasi user dilakukan pada application/domain layer melalui IdentityAccess contract.

Reuse existing outlet fields untuk operating hours dan service area:

```text
operating_hours
service_area_type
service_radius_km
province_id
regency_id
district_id
village_id
```

Jangan membuat table baru hanya untuk memindahkan data yang sudah dimiliki `MerchantOutlet`.

---

# 24. Operational Contact Decision

Current Merchant model memiliki:

```text
business_name
description
logo
```

Current schema tidak memiliki dedicated:

```text
operational_phone
operational_email
website
```

Sebelum implementasi migration, pilih source of truth yang jelas.

Possible approach:

1. tambah kolom langsung pada Merchant;
2. dedicated merchant contact structure;
3. gunakan existing identity bila memang secara domain benar.

Untuk MVP, pilih struktur paling sederhana dengan ownership jelas di Merchant domain.

**Tidak boleh silently invent persistence berdasarkan kebutuhan UI.**

---

# 25. Routes

Tambahkan ke:

`app/Modules/Merchant/Routes/api.php`

Canonical routes:

```text
GET    /api/v1/merchant/operations
POST   /api/v1/merchant/operations/activate
POST   /api/v1/merchant/operations/suspend
POST   /api/v1/merchant/operations/reactivate

GET    /api/v1/merchant/operations/profile
PATCH  /api/v1/merchant/operations/profile

GET    /api/v1/merchant/operations/outlets
POST   /api/v1/merchant/operations/outlets
GET    /api/v1/merchant/operations/outlets/{outlet}
PATCH  /api/v1/merchant/operations/outlets/{outlet}
POST   /api/v1/merchant/operations/outlets/{outlet}/activate
POST   /api/v1/merchant/operations/outlets/{outlet}/deactivate

GET    /api/v1/merchant/operations/outlets/{outlet}/users
POST   /api/v1/merchant/operations/outlets/{outlet}/users
PATCH  /api/v1/merchant/operations/outlets/{outlet}/users/{user}
DELETE /api/v1/merchant/operations/outlets/{outlet}/users/{user}

GET    /api/v1/merchant/operations/outlets/{outlet}/operating-hours
PUT    /api/v1/merchant/operations/outlets/{outlet}/operating-hours

GET    /api/v1/merchant/operations/outlets/{outlet}/service-area
PUT    /api/v1/merchant/operations/outlets/{outlet}/service-area

GET    /api/v1/merchant/operations/outlets/{outlet}/availability
```

Gunakan UUID route constraint untuk UUID parameters, konsisten dengan existing Merchant routes.

---

# 26. Cross-Module Dependencies

Expected dependencies:

```text
Merchant Operations
├── IdentityAccess Contracts
│   ├── Authorization
│   └── UserLookup
│
├── Geography Contracts
│   └── GeographyLookup
│
└── Storage Contracts
    └── ObjectStorage
```

Tidak boleh:

```text
Merchant Operations -> IdentityAccess Domain Model
Merchant Operations -> Geography Domain Model
Merchant Operations -> Storage implementation
```

P0 tidak membutuhkan direct dependency ke Notifications.

---

# 27. Testing Strategy

Tests berada di:

`app/Modules/Merchant/Tests`

## Unit

Wajib:

- status transition;
- operating hours validation;
- service-area validation;
- availability resolver;
- authorization scope;
- role semantics.

## Feature

Recommended:

`app/Modules/Merchant/Tests/Feature/MerchantOperationsApiTest.php`

Minimum:

### Authentication

- guest -> 401;
- authenticated without permission -> 403.

### Merchant status

- owner activate;
- owner suspend;
- owner reactivate;
- invalid transition;
- manager denied.

### Profile

- owner update;
- unauthorized mutation denied.

### Outlet

- owner list;
- owner create;
- owner update;
- owner activate/deactivate;
- manager assigned outlet only;
- foreign outlet denied.

### User assignment

- assign manager;
- assign staff;
- change role;
- remove;
- duplicate protection;
- cannot elevate to owner;
- cross-merchant protection.

### Operating hours

- valid schedule;
- invalid range;
- closed day;
- malformed payload.

### Service area

- all five types;
- radius validation;
- Geography hierarchy validation.

### Availability

- active merchant + active outlet + inside schedule = open;
- inactive merchant = closed;
- suspended merchant = closed;
- inactive outlet = closed;
- outside hours = closed.

## Architecture

Verify Operations tidak mengimpor internal:

- IdentityAccess Domain;
- Geography Domain;
- Storage implementation;
- unrelated module internals.

---

# 28. API Documentation

Semua endpoint harus terdokumentasi melalui Scramble/OpenAPI yang sudah digunakan repository.

Document:

- auth requirement;
- permission;
- request;
- response;
- validation;
- business errors;
- status codes.

Jangan membuat dokumentasi API mechanism baru.

---

# 29. Implementation Order

### Phase 1 — Foundation

1. Finalize `merchant.merchant_outlet_users` assignment model.
2. Assignment migration and unique/index constraints.
3. Domain model and repository/query support.
4. Role/permission seed.
5. Authorization service and outlet-scope enforcement.

### Phase 2 — Merchant

1. Summary.
2. Status.
3. Profile.

### Phase 3 — Outlet

1. List.
2. Detail.
3. Create.
4. Update.
5. Activate/deactivate.

### Phase 4 — Employees

1. List.
2. Assign.
3. Change role.
4. Remove.
5. Outlet scope.

### Phase 5 — Operating Hours

1. Get.
2. Update.
3. Validation.

### Phase 6 — Service Area

1. Get.
2. Update.
3. Geography validation.

### Phase 7 — Availability

1. Resolver.
2. Endpoint.
3. Tests.

### Phase 8 — Hardening

1. Architecture tests.
2. OpenAPI review.
3. Authorization matrix tests.
4. Regression tests.

---

# 30. Compatibility Requirements

P0 tidak boleh merusak:

```text
GET /api/v1/merchants
GET /api/v1/merchants/{merchant}
*
/api/v1/merchants/registration/*
/api/v1/admin/merchant-approvals/*
```

Boundary tetap:

```text
Registration != Approval != Operations
```

Registration menangani onboarding.

Approval menangani keputusan platform.

Operations menangani merchant pasca-approval.

---

# 31. AI Agent Implementation Checklist

Bagian ini adalah checklist eksekusi. AI agent harus menggunakannya sebagai urutan kerja dan tidak menganggap semua keputusan sebagai izin untuk mengubah architectural boundary.

## A. Pre-implementation audit

- [ ] Re-read existing Merchant models, enums, migrations, routes, contracts, and tests.
- [ ] Reuse existing `Merchant`, `MerchantOutlet`, `MerchantStatus`, `OutletStatus`, and `OutletServiceAreaType`.
- [ ] Verify existing IdentityAccess `Authorization` and `UserLookup` contracts before writing adapters.
- [ ] Verify existing Geography `GeographyLookup` contract.
- [ ] Verify existing Storage `ObjectStorage` contract.
- [ ] Verify existing API response, Result, Problem Details, pagination, and validation conventions.
- [ ] Do not duplicate an existing capability.

## B. Database

- [ ] Add exactly one new P0 table: `merchant.merchant_outlet_users`.
- [ ] Columns: `id`, `merchant_id`, `outlet_id`, `user_id`, `role`, `created_at`, `updated_at`.
- [ ] Add internal FK `merchant_id -> merchant.merchants.id`.
- [ ] Add internal FK `outlet_id -> merchant.merchant_outlets.id`.
- [ ] Do not add FK from `user_id` to IdentityAccess.
- [ ] Add `UNIQUE (outlet_id, user_id)`.
- [ ] Add required query indexes.
- [ ] Do not create `merchant_user_assignments`.
- [ ] Do not create `merchant_outlet_user_assignments`.
- [ ] Do not create availability table.
- [ ] Do not create operating-hours table.
- [ ] Do not create service-area table.

## C. Domain/application

- [ ] Implement single-purpose Actions.
- [ ] Keep controllers thin.
- [ ] Enforce merchant scope server-side.
- [ ] Enforce outlet assignment server-side.
- [ ] Verify target users via `UserLookup`.
- [ ] Verify geography via `GeographyLookup`.
- [ ] Resolve logo via Storage contract.
- [ ] Keep availability derived.

## D. Authorization

- [ ] Seed/use the defined P0 permissions.
- [ ] Owner has merchant-wide scope.
- [ ] Manager is limited to assigned outlets.
- [ ] Staff is limited to assigned outlets and read-only by default.
- [ ] Never use UI visibility as authorization.
- [ ] Prevent cross-merchant outlet access.
- [ ] Prevent manager/staff from obtaining owner privilege through assignment.

## E. API

- [ ] Add only the canonical P0 routes in this PRD.
- [ ] Use `auth:sanctum`.
- [ ] Use existing response envelope.
- [ ] Use existing Problem Details error mechanism.
- [ ] Validate UUID route parameters consistently.
- [ ] Document endpoints with existing Scramble/OpenAPI mechanism.

## F. Tests

- [ ] Unit-test status transitions.
- [ ] Unit-test operating-hours validation.
- [ ] Unit-test service-area validation.
- [ ] Unit-test availability resolution.
- [ ] Feature-test authentication and permissions.
- [ ] Feature-test outlet scope and cross-merchant protection.
- [ ] Feature-test duplicate outlet-user assignment.
- [ ] Feature-test role changes and owner protection.
- [ ] Run architecture tests for cross-module dependency boundaries.
- [ ] Run regression tests for existing Registration, Approval, and Merchant APIs.

## G. Explicit non-goals for the agent

Do not implement:

- [ ] Temporary Closure.
- [ ] Operational Settings.
- [ ] Operational Audit History.
- [ ] Catalog/Product.
- [ ] Ordering.
- [ ] Payment/Payout.
- [ ] Dispatch/Driver.
- [ ] Notifications integration.
- [ ] Push/email/SMS/WhatsApp.
- [ ] WebSocket/realtime.
- [ ] Custom role builder.
- [ ] A second employee assignment table.
- [ ] A persisted availability table.

## H. Completion report

After implementation, the agent must report:

1. Files created.
2. Files modified.
3. Migration/table changes.
4. Routes added.
5. Permissions added/changed.
6. Cross-module contracts used.
7. Tests added.
8. Commands/tests executed and results.
9. Any deviation from this PRD and its reason.
10. Any unresolved domain decision, especially operational contact persistence.

---

# 32. Definition of Done

### Architecture

- [ ] Operations berada di Merchant module.
- [ ] Registration tidak tercampur.
- [ ] Approval tidak tercampur.
- [ ] Cross-module access melalui Contracts.
- [ ] Tidak ada Eloquent cross-module import.

### Merchant

- [ ] Summary.
- [ ] Activate.
- [ ] Suspend.
- [ ] Reactivate.
- [ ] Profile GET/PATCH.

### Outlet

- [ ] List.
- [ ] Detail.
- [ ] Create.
- [ ] Update.
- [ ] Activate/deactivate.
- [ ] Scope enforcement.

### Employee

- [ ] Assignment persistence.
- [ ] Outlet Manager.
- [ ] Outlet Staff.
- [ ] Assign/remove/change role.
- [ ] Owner protected.

### Operating Hours

- [ ] GET/PUT.
- [ ] Validation.

### Service Area

- [ ] GET/PUT.
- [ ] Radius/province/regency/district/village.
- [ ] Geography validation.

### Availability

- [ ] Resolver.
- [ ] open/closed.
- [ ] reason.
- [ ] no manual persisted availability.

### Quality

- [ ] Unit tests.
- [ ] Feature tests.
- [ ] Architecture tests.
- [ ] OpenAPI.
- [ ] Regression tests.

---

# 33. Final P0 API Surface

```text
MERCHANT
GET    /api/v1/merchant/operations
POST   /api/v1/merchant/operations/activate
POST   /api/v1/merchant/operations/suspend
POST   /api/v1/merchant/operations/reactivate

GET    /api/v1/merchant/operations/profile
PATCH  /api/v1/merchant/operations/profile

OUTLETS
GET    /api/v1/merchant/operations/outlets
POST   /api/v1/merchant/operations/outlets
GET    /api/v1/merchant/operations/outlets/{outlet}
PATCH  /api/v1/merchant/operations/outlets/{outlet}
POST   /api/v1/merchant/operations/outlets/{outlet}/activate
POST   /api/v1/merchant/operations/outlets/{outlet}/deactivate

OUTLET USERS
GET    /api/v1/merchant/operations/outlets/{outlet}/users
POST   /api/v1/merchant/operations/outlets/{outlet}/users
PATCH  /api/v1/merchant/operations/outlets/{outlet}/users/{user}
DELETE /api/v1/merchant/operations/outlets/{outlet}/users/{user}

OPERATING HOURS
GET    /api/v1/merchant/operations/outlets/{outlet}/operating-hours
PUT    /api/v1/merchant/operations/outlets/{outlet}/operating-hours

SERVICE AREA
GET    /api/v1/merchant/operations/outlets/{outlet}/service-area
PUT    /api/v1/merchant/operations/outlets/{outlet}/service-area

AVAILABILITY
GET    /api/v1/merchant/operations/outlets/{outlet}/availability
```

---

# 34. P1 Boundary

Setelah P0 stabil:

```text
P1
├── Temporary Closure
├── Operational Settings
└── Operational Audit History
```

Temporary Closure nantinya menjadi input tambahan ke `OperationalAvailabilityResolver`, bukan menggantikan konsep derived availability.

---

# 35. Final Database Decision

Untuk Merchant Operations P0, database design final adalah:

```text
Existing:
merchant.merchants
merchant.merchant_outlets

New:
merchant.merchant_outlet_users
```

Tidak ada tabel baru lain yang diwajibkan oleh scope P0.

| Capability                  | Persistence                                      |
| --------------------------- | ------------------------------------------------ |
| Merchant operational status | `merchant.merchants.status`                      |
| Merchant profile            | `merchant.merchants`                             |
| Outlet                      | `merchant.merchant_outlets`                      |
| Outlet status               | `merchant.merchant_outlets.status`               |
| Operating hours             | `merchant.merchant_outlets.operating_hours`      |
| Service area                | Existing fields pada `merchant.merchant_outlets` |
| Outlet employee assignment  | `merchant.merchant_outlet_users`                 |
| Availability                | Derived, tidak dipersist                         |

Operational contact masih merupakan domain decision. Agent tidak boleh membuat tabel/kolom baru untuk contact tanpa terlebih dahulu mengikuti keputusan source-of-truth pada Section 24.

---

# 36. Final Architectural Principles

```text
Registration != Approval != Operations

Merchant Status != Outlet Status != Availability

Role != Outlet Assignment

UI permission hiding != API authorization

Eloquent model != Cross-module contract
```

Merchant Operations P0 adalah post-approval capability di dalam `app/Modules/Merchant`, menggunakan existing Merchant/Outlet domain sebagai baseline, satu assignment persistence baru (`merchant.merchant_outlet_users`) untuk employee/outlet scope, public Contracts untuk IdentityAccess/Geography/Storage, serta pola API dan testing yang sudah digunakan JualAntar API.

Dokumen ini menjadi baseline implementasi **Merchant Operations API P0**.
