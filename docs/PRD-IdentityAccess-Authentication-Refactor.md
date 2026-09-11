# PRD — IdentityAccess & Authentication Refactor

**Project:** JualAntar API  
**Document Type:** Product Requirements Document / Technical Refactoring Plan  
**Status:** Draft — Ready for Planning  
**Primary Goal:** Memperbaiki boundary module authentication agar penambahan role dan modul baru tidak membuat logic authentication tersebar atau saling bergantung.

---

## 1. Latar Belakang

Codebase JualAntar API saat ini sudah menggunakan pendekatan modular dengan module `IdentityAccess` dan `Customer`. Implementasi registrasi customer sudah dipisahkan ke `Customer`, tetapi sebagian logic authentication masih berada di dalam module `Customer`.

Saat ini terdapat pola seperti:

- `Customer/Application/Actions/Login.php` melakukan query langsung ke `IdentityAccess/User`.
- `Customer/Application/Actions/Login.php` menangani `Hash::check`, email verification check, dan pembuatan Sanctum token.
- `Customer/Application/Actions/VerifyEmail.php` melakukan verifikasi terhadap `User` secara langsung.
- `Customer/Http/Controllers/AuthenticationController.php` menangani register, login, verify email, dan resend verification.
- `RegisterCustomer.php` memang merupakan business use case customer, tetapi authentication primitive masih tercampur dengan customer flow.

Struktur tersebut masih dapat berjalan untuk satu role, tetapi akan menimbulkan masalah ketika JualAntar menambahkan:

- Customer
- Driver
- Merchant
- Super Admin
- Role internal/admin lain di masa depan

Masalah utama bukan sekadar lokasi file, tetapi **ownership terhadap authentication**. Login tidak seharusnya menjadi fitur milik Customer karena Customer hanyalah salah satu jenis user.

PRD ini mendefinisikan refactor agar `IdentityAccess` menjadi single owner untuk identity, authentication, authorization primitives, dan user access lifecycle.

---

## 2. Tujuan

### 2.1 Tujuan Utama

Membangun boundary module yang stabil sehingga fitur baru seperti Driver, Merchant, dan Super Admin dapat menggunakan authentication yang sama tanpa membuat duplicate login implementation.

### 2.2 Tujuan Spesifik

1. Memindahkan authentication lifecycle dari `Customer` ke `IdentityAccess`.
2. Mempertahankan `RegisterCustomer` di `Customer` sebagai business-specific orchestration.
3. Menjadikan `IdentityAccess` sebagai single source of truth untuk `User`.
4. Menjaga Sanctum sebagai mekanisme token authentication.
5. Memastikan role bukan bagian dari identity model/domain-specific module.
6. Menyediakan endpoint authentication global yang dapat dipakai seluruh role.
7. Memastikan business module tidak perlu melakukan `Hash::check`, `createToken`, atau query authentication credential secara langsung.
8. Menambahkan test coverage untuk mencegah boundary kembali rusak.
9. Menyiapkan struktur yang aman untuk implementasi RBAC dan role-role baru.

---

## 3. Non-Goals

PRD ini **tidak** bertujuan untuk:

- Membuat module `Auth` baru.
- Mengganti Sanctum dengan JWT.
- Mengimplementasikan Driver module secara penuh.
- Mengimplementasikan Merchant module secara penuh.
- Mengubah seluruh domain/business logic JualAntar.
- Mendesain ulang database business modules yang tidak terkait authentication.
- Menambahkan KYC, OTP, social login, SSO, atau MFA.
- Mengubah kontrak API yang tidak berhubungan dengan authentication kecuali endpoint authentication yang perlu dinormalisasi.

---

## 4. Prinsip Arsitektur

### 4.1 IdentityAccess adalah pemilik authentication

`IdentityAccess` bertanggung jawab atas:

- User identity.
- Credential authentication.
- Password verification.
- Authentication token.
- Email verification lifecycle.
- Logout/revocation.
- Current authenticated user.
- Roles dan permissions primitives.
- User access rules.

### 4.2 Business Module adalah pemilik business profile

Module seperti `Customer`, `Driver`, dan `Merchant` bertanggung jawab atas profil dan business behavior masing-masing.

Contoh:

- `Customer` → customer profile dan customer business actions.
- `Driver` → driver profile, vehicle, availability, delivery workflow.
- `Merchant` → merchant profile, store, catalog, orders.
- `IdentityAccess` → siapa user tersebut dan bagaimana user tersebut login/diotorisasi.

### 4.3 Dependency direction

Dependency harus satu arah:

```text
Customer ───────┐
Driver ─────────┤
Merchant ───────┼──> IdentityAccess
Admin Features ─┘
```

Tidak boleh:

```text
IdentityAccess ──> Customer
IdentityAccess ──> Driver
IdentityAccess ──> Merchant
```

`IdentityAccess` tidak boleh mengetahui business module tertentu hanya untuk menyelesaikan authentication umum.

### 4.4 Tidak membuat Auth Module terpisah

Untuk scope saat ini, module `Auth` terpisah tidak diperlukan.

Authentication merupakan bagian dari identity/access boundary sehingga tetap berada di `IdentityAccess`.

---

## 5. Current State

Struktur yang perlu diperbaiki saat ini secara konseptual:

```text
Customer
├── Application
│   └── Actions
│       ├── Login.php              <-- salah boundary
│       ├── RegisterCustomer.php   <-- tetap di Customer
│       └── VerifyEmail.php        <-- salah boundary
├── Http
│   └── Controllers
│       └── AuthenticationController.php  <-- terlalu broad
└── ...

IdentityAccess
├── Domain
│   └── Models
│       └── User.php
└── ...
```

Masalah utama:

1. Customer menjadi pemilik login.
2. Authentication logic bergantung pada business role.
3. Role baru berpotensi membutuhkan login action baru.
4. Hash/token logic dapat tersebar ke banyak module.
5. Verification logic dapat diduplikasi.
6. Controller authentication menjadi campuran antara identity dan customer registration.

---

## 6. Target Architecture

Target akhir:

```text
app/Modules/
├── IdentityAccess/
│   ├── Application/
│   │   ├── Actions/
│   │   │   ├── Login.php
│   │   │   ├── Logout.php
│   │   │   ├── VerifyEmail.php
│   │   │   └── ResendVerificationEmail.php
│   │   └── DTOs/
│   ├── Domain/
│   │   ├── Models/
│   │   │   └── User.php
│   │   └── ...
│   ├── Http/
│   │   └── Controllers/
│   │       └── AuthenticationController.php
│   ├── Routes/
│   │   └── api.php
│   ├── Database/
│   ├── Tests/
│   └── IdentityAccessServiceProvider.php
│
├── Customer/
│   ├── Application/
│   │   └── Actions/
│   │       └── RegisterCustomer.php
│   ├── Domain/
│   │   └── Models/
│   │       └── Customer.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── CustomerController.php
│   ├── Routes/
│   │   └── api.php
│   └── Tests/
│
├── Driver/
│   └── ... future
│
└── Merchant/
    └── ... future
```

---

## 7. Module Responsibility Matrix

| Responsibility        |                  IdentityAccess | Customer | Driver | Merchant |                Admin Feature |
| --------------------- | ------------------------------: | -------: | -----: | -------: | ---------------------------: |
| User identity         |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Password verification |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Sanctum token         |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Login                 |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Logout                |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Email verification    |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Resend verification   |                              ✅ |       ❌ |     ❌ |       ❌ |                           ❌ |
| Role assignment       |        ✅ / controlled use case |       ❌ |     ❌ |       ❌ | ✅ through access management |
| Permission checking   |                              ✅ |       ❌ |     ❌ |       ❌ |                           ✅ |
| Customer registration | orchestrated via User primitive |       ✅ |     ❌ |       ❌ |                           ❌ |
| Customer profile      |                              ❌ |       ✅ |     ❌ |       ❌ |                           ❌ |
| Driver profile        |                              ❌ |       ❌ |     ✅ |       ❌ |                           ❌ |
| Merchant profile      |                              ❌ |       ❌ |     ❌ |       ✅ |                           ❌ |

---

## 8. Functional Requirements

### FR-01 — Global Login

System MUST menyediakan authentication endpoint yang tidak bergantung pada role bisnis.

Target endpoint:

```http
POST /api/v1/auth/login
```

Request:

```json
{
    "email": "user@example.com",
    "password": "password"
}
```

Login MUST:

1. Validate credentials.
2. Load user melalui `IdentityAccess`.
3. Reject invalid credential.
4. Check email verification requirement sesuai kebijakan sistem.
5. Create Sanctum token.
6. Return authenticated user information yang aman.
7. Return role information jika dibutuhkan client.
8. Tidak memiliki hard-coded logic seperti `if role == customer`.

---

### FR-02 — Login Tidak Role-Specific

`Login.php` tidak boleh menerima customer-specific concern seperti:

```php
Customer::query()
CustomerRepository
CustomerProfile
```

Login hanya mengetahui `User` dan access state.

Contoh flow:

```text
Request
  ↓
AuthenticationController
  ↓
Login Action
  ↓
IdentityAccess User
  ↓
Credential verification
  ↓
Email verification check
  ↓
Sanctum token
  ↓
Response
```

Tidak boleh:

```text
Login Customer
Login Driver
Login Merchant
Login Admin
```

Satu authentication mechanism harus dipakai bersama.

---

### FR-03 — Logout

`IdentityAccess` harus menyediakan logout action.

Target endpoint:

```http
POST /api/v1/auth/logout
```

Logout harus revoke token/session yang sedang digunakan sesuai strategi Sanctum yang dipakai project.

Business module tidak boleh menghapus token secara manual.

---

### FR-04 — Email Verification

Email verification harus dipindahkan ke `IdentityAccess`.

Target endpoint secara konseptual:

```http
GET /api/v1/auth/email/verify/{id}/{hash}
POST /api/v1/auth/email/verification-notification
```

Implementasi final harus mengikuti mekanisme verification yang konsisten dengan Laravel dan project API.

`Customer` tidak boleh memiliki verification primitive sendiri.

---

### FR-05 — Resend Verification Email

`IdentityAccess` harus memiliki action khusus untuk resend verification email.

Action harus:

1. Resolve authenticated/target user sesuai kontrak endpoint.
2. Memeriksa apakah email sudah verified.
3. Mengirim notification jika masih belum verified.
4. Tidak melakukan query terhadap `Customer`.

---

### FR-06 — Customer Registration Tetap di Customer

`RegisterCustomer` tetap berada di `Customer` karena proses tersebut merupakan business-specific registration flow.

Flow:

```text
POST /customer/register
        ↓
RegisterCustomer
        ↓
Create IdentityAccess User
        ↓
Assign customer role
        ↓
Create Customer profile
        ↓
Send verification notification
```

Namun `RegisterCustomer` tidak boleh mengambil alih tanggung jawab:

- password authentication
- token generation
- login
- logout
- email verification algorithm

---

### FR-07 — Role sebagai Access Attribute

Role tidak boleh digunakan sebagai dasar ownership module authentication.

Role yang direncanakan:

```text
customer
driver
merchant
super-admin
```

Login tetap satu.

Setelah login, access layer menentukan role/permission.

---

### FR-08 — Multi-role Future Compatibility

Design harus mendukung kemungkinan satu user mempunyai lebih dari satu role di masa depan tanpa mengubah login mechanism.

Contoh:

```text
User #1
├── customer
└── merchant
```

atau:

```text
User #2
├── driver
└── merchant
```

PRD tidak mewajibkan multi-role UI/business flow sekarang, tetapi architecture tidak boleh mencegahnya.

---

## 9. API Design

### 9.1 Authentication Route Group

Gunakan namespace route global:

```text
/api/v1/auth/*
```

Recommended:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/email/verify/{id}/{hash}
POST /api/v1/auth/email/verification-notification
GET  /api/v1/auth/me
```

`/me` opsional tetapi direkomendasikan untuk memusatkan retrieval authenticated identity.

### 9.2 Business Module Routes

Customer:

```text
/api/v1/customers/*
```

Driver:

```text
/api/v1/drivers/*
```

Merchant:

```text
/api/v1/merchants/*
```

Admin:

```text
/api/v1/admin/*
```

Authentication tidak boleh nested di route business module.

---

## 10. Application Layer Contract

Gunakan action/use-case yang jelas dan kecil.

### Login

```text
Login
Input:
- email
- password

Output:
- user
- token
- roles
```

### Logout

```text
Logout
Input:
- authenticated user/token

Output:
- success
```

### VerifyEmail

```text
VerifyEmail
Input:
- verification request data

Output:
- verified status
```

### ResendVerificationEmail

```text
ResendVerificationEmail
Input:
- target authenticated user / validated identity

Output:
- success
```

Action harus tidak bergantung kepada HTTP Request secara berlebihan jika tidak diperlukan. Validation/request mapping sebaiknya tetap pada HTTP layer.

---

## 11. User Model Requirements

`IdentityAccess/Domain/Models/User.php` menjadi canonical user model.

User model harus menjadi tempat untuk capability umum identity seperti:

- email
- password
- email_verified_at
- Sanctum token relation
- role relation
- notification

Business-specific data seperti:

- customer address
- driver license
- merchant store data

tidak boleh dimasukkan ke `User` hanya agar login lebih mudah.

### Mandatory Audit

Sebelum implementasi, audit `User` terhadap:

- fillable/attribute definitions.
- casts.
- `MustVerifyEmail` contract jika digunakan.
- notification methods.
- Sanctum trait.
- Spatie `HasRoles`.
- `guard_name`.
- hidden attributes.
- phone field compatibility.

Khususnya, audit seluruh flow registration karena `RegisterCustomer` saat ini terlihat mencoba mengisi field `phone` pada `User`, sementara konfigurasi fillable harus dipastikan memang mengizinkan field tersebut.

---

## 12. Email Verification Requirements

Implementasi existing `VerifyEmail` harus direview dan tidak boleh sekadar dipindahkan file.

Checklist:

1. Gunakan kontrak/mekanisme verification yang sesuai dengan Laravel project.
2. Validasi signature/hash sesuai mekanisme framework.
3. Pastikan user benar-benar ada.
4. Idempotent ketika user sudah verified.
5. Jangan expose credential/user data sensitif.
6. Return consistent Problem Details/API response.
7. Test signed/invalid/expired verification scenario sesuai mekanisme yang dipilih.

---

## 13. Authorization & RBAC Boundary

Authentication dan authorization harus dipisahkan secara konseptual.

### Authentication

Menjawab:

> "Siapa user ini?"

Owned by:

```text
IdentityAccess
```

### Authorization

Menjawab:

> "Apa yang boleh dilakukan user ini?"

Owned centrally by:

```text
IdentityAccess / Access Control
```

Business modules hanya meminta authorization result dan tidak membuat sistem role sendiri.

Contoh:

```text
POST /api/v1/admin/users
        ↓
auth:sanctum
        ↓
permission:users.create
        ↓
Admin/User Management use case
```

---

## 14. Dependency Rules

Tambahkan architecture rule/test untuk memastikan:

### Allowed

```text
Customer      -> IdentityAccess
Driver        -> IdentityAccess
Merchant      -> IdentityAccess
Admin Feature -> IdentityAccess
```

### Forbidden

```text
IdentityAccess -> Customer
IdentityAccess -> Driver
IdentityAccess -> Merchant
```

### Forbidden business auth implementation

Business modules tidak boleh mengandung:

```php
Hash::check(...)
$user->createToken(...)
$user->tokens()->delete(...)
Auth::attempt(...)
```

kecuali melalui abstraction/use case yang memang dimiliki `IdentityAccess`.

---

## 15. Refactoring Plan — Step by Step

> Implementasi harus dilakukan setelah Plan/Architecture review. Jangan langsung memindahkan file tanpa audit dependency.

### Step 0 — Baseline

1. Checkout branch khusus refactor.
2. Jalankan seluruh test suite.
3. Catat baseline pass/fail.
4. Catat route authentication yang sedang tersedia.
5. Catat dependency dari Customer authentication terhadap `IdentityAccess`.

### Step 1 — Audit IdentityAccess

Review:

- `User.php`
- IdentityAccess service provider
- Sanctum configuration
- Spatie configuration
- current role/permission middleware
- notifications
- user migration
- route loading

Hasil harus mendokumentasikan contract yang akan dipakai oleh module lain.

### Step 2 — Audit Customer Authentication

Review semua file:

- `Customer/Application/Actions/Login.php`
- `Customer/Application/Actions/VerifyEmail.php`
- `Customer/Application/Actions/RegisterCustomer.php`
- `Customer/Http/Controllers/AuthenticationController.php`
- Customer routes
- Customer tests

Identifikasi logic yang merupakan:

- identity concern
- authentication concern
- customer business concern

### Step 3 — Definisikan Target Contracts

Tetapkan sebelum coding:

```text
IdentityAccess Login contract
IdentityAccess Logout contract
IdentityAccess Email Verification contract
IdentityAccess Resend Verification contract
```

Tetapkan input/output dan error behavior.

### Step 4 — Pindahkan Login

Buat:

```text
IdentityAccess/Application/Actions/Login.php
```

Login baru harus:

- query User melalui IdentityAccess.
- verify password.
- check verification policy.
- create Sanctum token.
- return role information bila diperlukan.

Hapus business dependency dari login.

### Step 5 — Pindahkan Email Verification

Buat:

```text
IdentityAccess/Application/Actions/VerifyEmail.php
IdentityAccess/Application/Actions/ResendVerificationEmail.php
```

Review existing algorithm dan align dengan Laravel verification mechanism.

### Step 6 — Pindahkan Authentication Controller

Buat:

```text
IdentityAccess/Http/Controllers/AuthenticationController.php
```

Controller hanya menangani authentication endpoint.

Customer controller tidak lagi menjadi owner endpoint login/verify/logout.

### Step 7 — Normalisasi Routes

Tambahkan global auth routes:

```text
/api/v1/auth/login
/api/v1/auth/logout
/api/v1/auth/me
/api/v1/auth/email/verify/...
/api/v1/auth/email/verification-notification
```

Customer register tetap pada route Customer.

### Step 8 — Refactor RegisterCustomer

`RegisterCustomer` harus:

1. membuat User melalui identity user creation mechanism yang sudah tersedia/ditetapkan.
2. assign role `customer`.
3. membuat Customer profile.
4. commit transaction.
5. mengirim verification notification sesuai boundary yang telah ditetapkan.

Jangan memasukkan login/token generation ke register.

### Step 9 — Remove Old Customer Auth

Setelah tests telah berpindah:

- delete `Customer/Application/Actions/Login.php`.
- delete `Customer/Application/Actions/VerifyEmail.php`.
- remove authentication methods dari Customer controller.
- remove duplicate routes.

Tidak boleh meninggalkan alias/duplicate implementation tanpa alasan kompatibilitas yang jelas.

### Step 10 — API Compatibility Review

Periksa semua frontend/client call yang memakai endpoint lama.

Jika endpoint lama dipertahankan sementara, gunakan compatibility layer yang tipis dan terencana.

Jangan mempertahankan duplicate authentication implementation.

### Step 11 — Tests

Tambahkan/ubah tests:

#### IdentityAccess tests

- valid login.
- invalid password.
- unknown email.
- unverified email.
- verified email.
- token created.
- logout.
- email verification valid.
- invalid verification.
- duplicate verification.
- resend verification.

#### Customer tests

- registration creates User + Customer.
- customer role assigned.
- verification notification triggered.
- customer registration does not create token unless explicitly defined by business requirement.

#### Architecture tests

- Customer may depend on IdentityAccess.
- IdentityAccess must not depend on Customer.
- no business module has direct credential/token implementation.

### Step 12 — Quality Gates

Jalankan:

```bash
php artisan test
php artisan route:list
vendor/bin/pint --test
```

Gunakan command lain yang sudah menjadi standard repository bila terdapat static analysis/architecture test tambahan.

---

## 16. Error Handling Requirements

Semua authentication error harus mengikuti API error standard yang sudah digunakan project.

Gunakan `application/problem+json` untuk API error sesuai centralized error handling yang telah tersedia.

Minimal error cases:

| Case                      |                                           HTTP |
| ------------------------- | ---------------------------------------------: |
| Invalid credentials       |                                            401 |
| Unauthenticated           |                                            401 |
| Forbidden                 |                                            403 |
| Validation error          |                                            422 |
| Invalid verification link |                   400/422 sesuai error catalog |
| Already verified          | 409 atau response idempotent sesuai API policy |

Jangan mencampur format error khusus Customer dan IdentityAccess.

---

## 17. Security Requirements

1. Jangan return password.
2. Jangan log password.
3. Jangan log raw access token.
4. Hindari token/email/phone masuk log jika tidak diperlukan.
5. Login error tidak boleh membocorkan apakah email tertentu terdaftar bila security policy project mengharuskan generic credential error.
6. Gunakan Sanctum token sesuai mechanism yang sudah dipilih project.
7. Role/permission output harus berasal dari authoritative access layer.
8. Email verification link harus divalidasi dengan mechanism aman.
9. Jangan membuat custom crypto/hash mechanism tanpa kebutuhan.

---

## 18. Observability Requirements

Authentication harus tetap terintegrasi dengan observability foundation yang sudah ada.

Minimal:

- request ID / trace ID tetap tersedia.
- authentication success/failure dapat dibedakan di log tanpa menyimpan credential.
- exception menggunakan centralized Problem Details.
- metric authentication dapat ditambahkan kemudian tanpa mengubah business module.

Suggested log context:

```text
trace_id
request_id
user_id (when safely available)
action=login
result=success|failure
```

Jangan log:

```text
password
access_token
verification_secret
```

---

## 19. Data & Transaction Requirements

Customer registration harus mempertahankan atomicity:

```text
BEGIN
  create User
  assign customer role
  create Customer profile
COMMIT
```

Jika salah satu gagal, transaction rollback.

Email dispatch idealnya terjadi setelah transaction berhasil commit sehingga notification tidak dikirim untuk data yang akhirnya rollback.

---

## 20. Response Contract

Login response yang direkomendasikan:

```json
{
    "data": {
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "roles": ["customer"]
        },
        "token": "..."
    }
}
```

Field final harus disesuaikan dengan response standard project yang telah ada.

Token tidak boleh muncul di log atau error response.

---

## 21. Backward Compatibility Strategy

Sebelum menghapus endpoint lama, audit seluruh consumer:

- customer frontend.
- admin frontend.
- future mobile/PWA client.
- test fixtures.
- documentation/OpenAPI.

Strategi preferensi:

```text
New canonical endpoint
POST /api/v1/auth/login
```

Endpoint lama hanya dipertahankan sementara bila benar-benar dibutuhkan untuk migrasi client.

Jika compatibility route dibuat, route tersebut harus hanya mendelegasikan ke IdentityAccess login use case, bukan membuat login implementation kedua.

---

## 22. Future Role Expansion

Setelah refactor ini selesai, role baru seharusnya dapat ditambahkan tanpa membuat authentication flow baru.

### Customer

```text
Customer Registration
   ↓
IdentityAccess User
   ↓
role=customer
```

### Driver

```text
Driver Registration/Approval
   ↓
IdentityAccess User
   ↓
role=driver
```

### Merchant

```text
Merchant Registration/Approval
   ↓
IdentityAccess User
   ↓
role=merchant
```

### Super Admin

```text
IdentityAccess User
   ↓
role=super-admin
```

Semua menggunakan:

```text
POST /api/v1/auth/login
```

Tanpa:

```text
/login/customer
/login/driver
/login/merchant
/login/admin
```

---

## 23. Definition of Done

Refactor dianggap selesai jika seluruh kondisi berikut terpenuhi:

### Architecture

- [ ] `IdentityAccess` menjadi owner authentication.
- [ ] Tidak ada authentication action di `Customer`.
- [ ] Tidak ada dependency `IdentityAccess -> Customer`.
- [ ] Customer tetap memiliki `RegisterCustomer`.
- [ ] Tidak ada Auth module terpisah.

### Authentication

- [ ] Global login tersedia.
- [ ] Logout tersedia.
- [ ] Email verification berada di IdentityAccess.
- [ ] Resend verification berada di IdentityAccess.
- [ ] Sanctum token dibuat hanya melalui IdentityAccess authentication flow.

### RBAC

- [ ] Role dapat dipakai Customer/Driver/Merchant/Admin.
- [ ] Login tidak melakukan branching berdasarkan business role.
- [ ] Authorization tetap dapat memakai Spatie.

### Code Quality

- [ ] Tests seluruhnya pass.
- [ ] Architecture tests ditambahkan.
- [ ] Duplicate authentication logic dihapus.
- [ ] Pint pass.
- [ ] Route list sudah sesuai.
- [ ] API documentation diperbarui.

### Security

- [ ] Password tidak pernah masuk log.
- [ ] Token tidak pernah masuk log.
- [ ] Verification secret tidak pernah masuk log.
- [ ] Error response mengikuti Problem Details.

---

## 24. Acceptance Criteria

### AC-01 — Customer Login

**Given** customer mempunyai credential valid dan email verified  
**When** `POST /api/v1/auth/login`  
**Then** authentication berhasil dan Sanctum token diterbitkan.

### AC-02 — Driver Login

**Given** driver mempunyai credential valid dan email verified  
**When** `POST /api/v1/auth/login`  
**Then** authentication berhasil tanpa memerlukan endpoint login khusus driver.

### AC-03 — Merchant Login

**Given** merchant mempunyai credential valid dan email verified  
**When** `POST /api/v1/auth/login`  
**Then** authentication berhasil tanpa login implementation khusus merchant.

### AC-04 — Super Admin Login

**Given** super admin mempunyai credential valid  
**When** `POST /api/v1/auth/login`  
**Then** authentication berhasil melalui endpoint yang sama.

### AC-05 — Business Boundary

**Given** module Customer dihapus dari dependency runtime IdentityAccess  
**When** authentication dijalankan  
**Then** IdentityAccess tetap dapat login.

### AC-06 — Registration Boundary

**Given** customer register  
**When** registration dijalankan  
**Then** User + customer role + Customer profile dibuat secara transactionally consistent.

### AC-07 — Verification Boundary

**Given** user menerima verification link  
**When** link diproses  
**Then** verification dilakukan oleh IdentityAccess dan tidak membutuhkan Customer module.

### AC-08 — Architecture Regression

**Given** developer menambahkan `Hash::check` atau `createToken` ke business module  
**When** architecture/static test dijalankan  
**Then** test gagal dan dependency violation terdeteksi.

---

## 25. Recommended Implementation Order

Urutan pengerjaan yang disarankan:

```text
1. Baseline + audit
2. IdentityAccess contract
3. Login
4. Logout
5. Email verification
6. Resend verification
7. Authentication Controller
8. Global routes
9. Refactor RegisterCustomer
10. Remove Customer authentication
11. Tests
12. Architecture tests
13. API documentation
14. Final review
```

Jangan mengerjakan Driver atau Merchant authentication sebelum langkah-langkah di atas selesai.

---

## 26. Expected End State

Setelah refactor, architecture authentication JualAntar harus mengikuti model berikut:

```text
                         ┌─────────────────────┐
                         │    IdentityAccess   │
                         │                     │
                         │ User                │
                         │ Authentication      │
                         │ Sanctum             │
                         │ Email Verification │
                         │ Roles               │
                         │ Permissions         │
                         └──────────┬──────────┘
                                    │
                  ┌─────────────────┼─────────────────┐
                  │                 │                 │
                  ▼                 ▼                 ▼
             Customer           Driver           Merchant
             profile            profile          profile
                  │                 │                 │
                  └─────────────────┼─────────────────┘
                                    ▼
                              Business Features
```

Konsekuensi yang diharapkan:

- satu login mechanism.
- satu user identity model.
- satu authentication boundary.
- satu authorization foundation.
- business module tetap independen terhadap role lain.
- penambahan role baru tidak memaksa refactor authentication.

---

## 27. Important Implementation Notes

1. **Jangan langsung memindahkan file secara mekanis.** Pastikan dependency dan responsibility diperbaiki terlebih dahulu.
2. **Jangan membuat `Auth` module baru pada fase ini.** `IdentityAccess` sudah merupakan boundary yang tepat.
3. **Jangan menaruh profil customer/driver/merchant di User model hanya untuk mempermudah query login.**
4. **Jangan membuat login action terpisah per role.**
5. **Jangan membuat role-specific token creation.**
6. **Gunakan satu authentication endpoint canonical.**
7. **Pastikan existing Spatie RBAC tetap compatible dengan Sanctum guard yang dipakai project.**
8. **Audit field `phone` pada User dan registration flow sebelum refactor dilanjutkan.**
9. **Periksa apakah Laravel email verification contract sudah diterapkan secara benar pada User model sebelum mempertahankan custom verification implementation.**
10. **Setiap future business module wajib mengikuti dependency direction yang sama.**

---

## 28. Deliverables

Output implementasi PRD ini harus mencakup:

- Refactored `IdentityAccess` authentication actions.
- Refactored authentication controller dan routes.
- Refactored `Customer` registration flow.
- Removed duplicate customer authentication implementation.
- Unit/feature tests.
- Architecture tests.
- Updated API documentation.
- Short architecture note yang menjelaskan ownership `IdentityAccess` vs business modules.

---

## 29. Final Architecture Rule

> **IdentityAccess answers “Who are you?” and “Are you allowed?”; business modules answer “What can you do in your business context?”**

Rule ini menjadi prinsip utama untuk pengembangan JualAntar API berikutnya.
