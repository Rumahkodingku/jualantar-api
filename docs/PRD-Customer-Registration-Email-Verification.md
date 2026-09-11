# PRD — Customer Registration & Email Verification

## 1. Metadata

- **Product:** JualAntar
- **Feature:** Customer Registration & Email Verification
- **Module:** `IdentityAccess` + Customer domain
- **Repository:** `https://github.com/Rumahkodingku/jualantar-api`
- **Target:** MVP
- **Development Email Provider:** Mailpit
- **Authentication:** Laravel Sanctum
- **Authorization:** Spatie Laravel Permission
- **Database:** PostgreSQL
- **Architecture:** Modular Monolith
- **Status:** Ready for implementation planning

---

## 2. Purpose

Membangun flow registrasi akun **Customer** yang aman, atomik, dan konsisten dengan arsitektur codebase JualAntar saat ini.

Flow utama:

```text
Customer
   ↓
POST /api/v1/auth/register/customer
   ↓
Validate input
   ↓
DB Transaction
   ├── Create User
   ├── Create Customer Profile
   └── Assign "customer" role
   ↓
Commit
   ↓
Dispatch email verification
   ↓
201 Created
   ↓
Customer membuka email di Mailpit
   ↓
Klik verification link
   ↓
Email verified
   ↓
Customer dapat Login
```

### Prinsip utama

1. `users` adalah sumber data identity/authentication.
2. `customers` adalah customer profile/domain data.
3. Role `customer` ditentukan server, bukan client.
4. Pembuatan `users` dan `customers` harus atomic.
5. Email verification menggunakan mekanisme Laravel sebanyak mungkin.
6. Development menggunakan Mailpit.
7. Registration tidak otomatis membuat access token.
8. Login normal mensyaratkan email terverifikasi jika kebijakan auth JualAntar menetapkannya.

---

## 3. Current-State Audit

Berdasarkan repository `Rumahkodingku/jualantar-api` pada branch `main`, codebase sudah memiliki modular structure di bawah `app/Modules`, termasuk modul `IdentityAccess` dengan layer `Application`, `Contracts`, `Database`, `Domain`, `Http`, `Infrastructure`, `Routes`, dan `Tests`.

Model `User` saat ini berada di `IdentityAccess/Domain/Models/User.php`, menggunakan Sanctum, Spatie `HasRoles`, `Notifiable`, dan `HasApiTokens`; `email_verified_at` di-cast sebagai datetime dan password menggunakan Laravel `hashed` cast.

Migration user saat ini berada pada schema `identity_access.users` dan sudah memiliki `id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, dan `updated_at`.

Route module saat ini menggunakan prefix `/api/v1`, dan endpoint `GET /user` sudah dilindungi `auth:sanctum`.

`IdentityAccessServiceProvider` sudah memuat migrations/routes module dan mengintegrasikan Spatie authorization. Role assignment yang sudah ada menggunakan guard `sanctum`.

### Kesimpulan audit

Fondasi authentication/RBAC sudah tersedia, tetapi flow **customer registration + email verification** belum menjadi capability lengkap. Implementasi harus ditambahkan mengikuti boundary module yang sudah ada, bukan membuat auth controller global baru di luar `IdentityAccess`.

---

## 4. Existing Database Contract

## 4.1 `identity_access.users`

Current migration:

```text
id
name
email
email_verified_at
password
remember_token
created_at
updated_at
```

## 4.2 `customers`

Kontrak domain dari desain saat ini:

```text
id UUID PK
user_id UUID UNIQUE FK → users.id

username VARCHAR(150) NOT NULL UNIQUE
full_name VARCHAR(150) NOT NULL

date_of_birth DATE NULL
gender ENUM(...) NULL
profile_image TEXT NULL
bio TEXT NULL

created_at TIMESTAMP WITH TIMEZONE NOT NULL
updated_at TIMESTAMP WITH TIMEZONE NOT NULL
```

### Schema cleanup sebelum implementasi

- Pastikan `profile_image` tidak terduplikasi.
- Pastikan `customers.user_id` memiliki FK ke `identity_access.users.id`.
- Pastikan tipe PK/FK kompatibel.
- Pastikan relasi `User 1 : 1 Customer` ditegakkan database.

### Registration scope

Jangan memaksa customer mengisi `date_of_birth`, `gender`, `profile_image`, atau `bio` ketika register kecuali requirement produk berubah.

---

## 5. Registration Input

Endpoint:

```http
POST /api/v1/auth/register/customer
```

Request:

```json
{
    "email": "customer@example.com",
    "phone": "081234567890",
    "username": "thomas",
    "full_name": "Thomas Alberto",
    "password": "StrongPassword123!",
    "password_confirmation": "StrongPassword123!"
}
```

## Required

- `email`
- `phone`
- `username`
- `full_name`
- `password`
- `password_confirmation`

## Server-controlled

- Role selalu `customer`.
- `email_verified_at` selalu `NULL` saat registration.
- `status` tidak boleh dikirim client.
- `user_id` tidak boleh dikirim client.
- `email_verified_at` tidak boleh dikirim client.

### `users.name` vs `customers.full_name`

Migration `users` saat ini memiliki `name`, sedangkan domain `customers` memiliki `full_name`.

Untuk MVP gunakan keputusan:

```text
users.name       = customers.full_name
customers.full_name = full_name request
```

Dengan ini tidak perlu mengubah contract `users` sebelum registration dibuat.

---

## 6. Validation Rules

Buat `RegisterCustomerRequest`.

Recommended rules:

```text
email:
required
email
max:100
unique:identity_access.users,email

phone:
required
string
max:30
unique:identity_access.users,phone

username:
required
string
min:3
max:150
alpha_dash
unique:customers,username

full_name:
required
string
max:150

password:
required
confirmed
string
security policy sesuai standard aplikasi
```

### Security rules

Jangan menerima field:

```text
role
status
email_verified_at
remember_token
user_id
```

### Phone normalization

Tetapkan satu format canonical untuk persistence, validation, login, dan future OTP.

Contoh:

```text
081234567890
→ +6281234567890
```

---

## 7. Application Layer

Buat:

```text
app/Modules/IdentityAccess/Application/Actions/RegisterCustomer.php
```

Tanggung jawab:

1. menerima validated data.
2. melakukan normalisasi yang diperlukan.
3. membuka transaction.
4. create `User`.
5. assign role `customer`.
6. create `Customer`.
7. commit.
8. dispatch verification notification setelah commit.
9. return `Result` sesuai pola application layer saat ini.
10. tidak membuat access token.

### Transaction boundary

```php
DB::transaction(function () {
    $user = User::create([...]);
    $user->assignRole('customer');
    Customer::create([...]);
});
```

Jika salah satu operasi persistence gagal, seluruh unit registration harus rollback.

### Email transaction rule

Jangan mengirim email di tengah transaction database.

Gunakan:

```text
DB transaction
  ↓
commit
  ↓
dispatch notification/job
```

Jika memungkinkan, gunakan after-commit dispatch atau event/listener agar delivery email tidak memengaruhi atomicity database.

---

## 8. Role Assignment

Gunakan Spatie:

```php
$user->assignRole('customer');
```

Jangan menggunakan kolom `role` pada `users` untuk menggantikan RBAC yang sudah berjalan.

Jangan menerima role dari frontend.

Invariant:

```text
POST /auth/register/customer
        ↓
role = customer
```

Guard harus kompatibel dengan codebase:

```text
sanctum
```

---

## 9. Customer Profile Creation

Setelah user dibuat, buat satu customer profile:

```text
customers.user_id = users.id
```

Persist minimal:

```text
user_id
username
full_name
```

Profile fields lain tetap nullable.

Relasi yang diharapkan:

```text
User 1 ───── 1 Customer
```

---

## 10. Email Verification

`User` sudah menggunakan `Notifiable`, sehingga gunakan native Laravel email verification flow sebanyak mungkin dan jangan membuat sistem token custom tanpa kebutuhan kuat.

State:

```text
registration:
email_verified_at = NULL

verification success:
email_verified_at = current timestamp
```

## Development: Mailpit

Gunakan SMTP Mailpit:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@jualantar.local
MAIL_FROM_NAME=JualAntar
```

Mailpit UI:

```text
http://localhost:8025
```

Flow development:

```text
Laravel
   ↓ SMTP
mailpit:1025
   ↓
Mailpit UI :8025
```

Nilai environment harus mengikuti setup repository yang aktif.

---

## 11. Verification Endpoints

Recommended:

```http
POST /api/v1/auth/register/customer
GET /api/v1/auth/email/verify/{id}/{hash}
POST /api/v1/auth/email/verification-notification
```

## Verification endpoint

Harus:

- memvalidasi signed URL/hash.
- memastikan user target benar.
- mengisi `email_verified_at` saat valid.
- bersifat idempotent untuk already-verified state.
- menggunakan response contract API yang sudah ada.
- tidak mengekspos token/hash internal.

## Resend

Rate-limit resend untuk mencegah email bombing.

Untuk MVP, pilih flow konsisten:

```text
Register
 ↓
Verification email
 ↓
Verify
 ↓
Login
```

---

## 12. Login Policy

Registration tidak membuat access token.

Flow:

```text
REGISTER
   ↓
VERIFY EMAIL
   ↓
LOGIN
   ↓
SANCTUM TOKEN
```

Login harus mengecek:

```text
credentials valid
+
email_verified_at != NULL
+
account status eligible
```

Jika belum verified, gunakan error contract existing sesuai kebijakan authentication module.

---

## 13. HTTP Response

Successful registration:

```text
201 Created
```

Recommended payload:

```json
{
    "data": {
        "user_id": "uuid",
        "email": "customer@example.com",
        "email_verified": false
    }
}
```

Jangan return:

```text
password
password_confirmation
remember_token
verification token/hash
```

Gunakan helper/response contract existing repository dan error format RFC/problem response yang sudah ada.

---

## 14. Error Scenarios

At minimum:

### Duplicate email

```text
422 validation_error
```

### Duplicate phone

```text
422 validation_error
```

### Duplicate username

```text
422 validation_error
```

### Invalid input

```text
422 validation_error
```

### Verification already completed

Idempotent success atau semantics existing yang konsisten.

### Invalid/expired verification

Return error aman tanpa kebocoran detail internal.

### Mail failure

Kegagalan SMTP tidak boleh membuat database menjadi partial.

Preferred:

```text
DB commit
↓
queue/notification dispatch
↓
retry delivery
```

---

## 15. API Route Structure

Extend existing module route:

```text
app/Modules/IdentityAccess/Routes/api.php
```

Gunakan prefix yang sudah ada:

```text
/api/v1
```

Tambahkan:

```php
Route::prefix('auth')->group(function () {
    Route::post('/register/customer', ...);
    Route::get('/email/verify/{id}/{hash}', ...);
    Route::post('/email/verification-notification', ...);
});
```

Registration dan email verification adalah public endpoints dan tidak menggunakan `auth:sanctum`.

---

## 16. Recommended Code Structure

```text
app/Modules/IdentityAccess/
├── Application/
│   └── Actions/
│       ├── RegisterCustomer.php
│       └── VerifyEmail.php
│
├── Domain/
│   └── Models/
│       └── User.php
│
├── Http/
│   ├── Controllers/
│   │   └── AuthenticationController.php
│   ├── Requests/
│   │   ├── RegisterCustomerRequest.php
│   │   └── ResendVerificationRequest.php
│   └── Resources/
│
├── Routes/
│   └── api.php
│
└── Tests/
    ├── Feature/
    └── Unit/
```

Jika repository sudah memiliki convention berbeda untuk Customer module, ikuti convention existing daripada membuat struktur paralel.

---

## 17. Domain Separation

## `User`

Menangani:

```text
authentication identity
email
password
email verification
roles
Sanctum tokens
notifications
```

## `Customer`

Menangani:

```text
username
full_name
date_of_birth
gender
profile_image
bio
customer-specific data
```

Target future:

```text
User
├── Customer
├── Driver
└── Merchant
```

---

## 18. Security Requirements

1. Password harus di-hash oleh Laravel.
2. Jangan log password atau password confirmation.
3. Jangan return password.
4. Jangan menyimpan verification secret plaintext jika mekanisme native Laravel tidak membutuhkan persistence custom.
5. Registration wajib rate limited.
6. Verification resend wajib rate limited.
7. Role injection harus ditolak.
8. Gunakan HTTPS di production.
9. Canonicalize phone number.
10. Jangan log verification secret/link secara sensitif.
11. User disabled/deleted tidak boleh menyelesaikan auth flow.
12. Gunakan existing API problem response.

---

# 19. Rate Limiting

Minimal:

```text
POST /auth/register/customer
POST /auth/email/verification-notification
```

Tujuan:

- mencegah registration spam.
- mencegah email bombing.
- mengontrol brute-force/abuse terhadap flow verification.

Gunakan Laravel rate limiter yang sudah tersedia.

---

# 20. Tests

## Registration feature tests

### Happy path

```text
POST register/customer
→ 201
→ users created
→ customer created
→ customer role assigned
→ verification notification dispatched
```

### Duplicate email

```text
→ 422
→ no duplicate account/profile
```

### Duplicate phone

```text
→ 422
```

### Duplicate username

```text
→ 422
```

### Password validation

```text
→ 422
```

### Role safety

Client mencoba mengirim:

```json
{
    "role": "super-admin"
}
```

Role tetap harus `customer`.

### Transaction rollback

Simulasikan kegagalan pada customer creation dan pastikan user/customer/role tidak meninggalkan partial state.

## Email verification tests

Test:

```text
verification success
already verified
invalid verification signature/hash
missing user
resend notification
rate limiting
```

Gunakan Laravel mail/notification fakes sesuai convention test repository.

---

# 21. Mailpit Acceptance Test

1. Start development compose.
2. Register customer.
3. Open `http://localhost:8025`.
4. Pastikan verification email muncul.
5. Buka email.
6. Klik verification link.
7. Pastikan `email_verified_at` terisi.
8. Login menggunakan customer account.

### Important

Jangan menguji development verification flow dengan inbox production.

---

# 22. Observability

Gunakan observability foundation yang sudah ada.

Recommended events/metrics/log points:

```text
registration_success
registration_validation_failed
registration_failed
email_verification_success
email_verification_failed
verification_resend
```

Jangan log:

```text
password
password_confirmation
authorization headers
session cookies
verification secrets
```

---

# 23. API Documentation

Dokumentasikan:

```text
POST /api/v1/auth/register/customer
GET /api/v1/auth/email/verify/{id}/{hash}
POST /api/v1/auth/email/verification-notification
```

Untuk tiap endpoint jelaskan:

- purpose
- authentication requirement
- request payload
- validation rules
- success response
- error responses
- rate limit
- verification behavior
- Mailpit development behavior

Gunakan tooling dokumentasi yang sudah ada di repository.

---

# 24. Implementation Steps

## Step 1 — Audit sebelum coding

Inspect:

```text
User model
Sanctum configuration
Spatie roles/guards
existing auth routes
API response helpers
mail configuration
existing tests
Customer schema/model if already present
```

Jangan overwrite auth/RBAC code yang sudah bekerja.

## Step 2 — Validate customer schema

- buat/konfirmasi migration customers.
- fix duplicate `profile_image`.
- add/confirm FK `customers.user_id`.
- confirm UUID compatibility.
- confirm unique constraints.

## Step 3 — Customer model

Implement:

```php
Customer belongsTo User
User hasOne Customer
```

Jika Customer module belum ada, buat sesuai module convention repository.

## Step 4 — Registration Request

Buat `RegisterCustomerRequest` dengan validation dan phone normalization.

## Step 5 — Registration Action

Buat `RegisterCustomer` dengan transaction.

## Step 6 — Role assignment

Assign `customer` server-side menggunakan Spatie.

## Step 7 — Verification notification

Gunakan Laravel email verification dan dispatch after commit.

## Step 8 — Verification endpoints

Implement verify + resend.

## Step 9 — Login guard/policy

Pastikan unverified customer tidak dapat login bila verification adalah prerequisite.

## Step 10 — Tests

Feature + unit tests untuk happy path, validation, transaction, role safety, verification, dan rate limiting.

## Step 11 — Mailpit validation

Pastikan email masuk ke Mailpit dan link dapat digunakan.

## Step 12 — API docs

Update API documentation.

## Step 13 — Quality gates

Minimum:

```bash
php artisan test
php artisan pint --test
php artisan route:list
php artisan migrate:fresh --seed
```

Tambahkan command project-specific yang sudah dipakai repository.

---

# 25. Definition of Done

- [ ] Customer dapat register melalui API.
- [ ] User record berhasil dibuat.
- [ ] Customer record berhasil dibuat.
- [ ] `users` ↔ `customers` relation valid.
- [ ] Role `customer` otomatis diberikan.
- [ ] Client tidak dapat menentukan role.
- [ ] Password ter-hash.
- [ ] `email_verified_at` awalnya `NULL`.
- [ ] Verification email masuk Mailpit.
- [ ] Verification link berhasil diproses.
- [ ] `email_verified_at` terisi setelah sukses.
- [ ] Verification idempotent.
- [ ] Resend tersedia dan rate-limited.
- [ ] Unverified account ditolak login sesuai policy.
- [ ] Duplicate email/phone/username ditolak.
- [ ] Error menggunakan existing problem response.
- [ ] Transaction rollback diuji.
- [ ] Sensitive data tidak masuk response/log.
- [ ] API docs diperbarui.
- [ ] Test suite lulus.
- [ ] Pint/checks lulus.
- [ ] Tidak ada perubahan tidak perlu pada RBAC.

---

# 26. Non-Goals

Tidak termasuk dalam feature ini:

- Google OAuth/Social Login.
- OTP SMS.
- Forgot password.
- Reset password.
- Complete customer profile editing.
- Customer address management.
- Customer wallet.
- Payment gateway.
- Driver registration.
- Merchant registration.
- Push notification.
- KYC.

---

# 27. Future Evolution

Fondasi harus memungkinkan:

```text
User
├── Customer
├── Driver
└── Merchant
```

dengan onboarding terpisah:

```text
/auth/register/customer
/auth/register/driver
/auth/register/merchant
```

Shared identity tetap berada pada `users`, sedangkan data role-specific berada pada profile/domain table masing-masing.

---

# 28. Final Architecture Decision

```text
                 Customer PWA
                      │
                      ▼
        POST /api/v1/auth/register/customer
                      │
                      ▼
             RegisterCustomer Action
                      │
                DB Transaction
              ┌───────┼────────┐
              ▼       ▼        ▼
            users  customers  role=customer
              │       │        │
              └───────┴────────┘
                      │
                   COMMIT
                      │
              Email verification
                      │
                      ▼
                   Mailpit
                      │
                      ▼
                 Verify link
                      │
                      ▼
          users.email_verified_at
                      │
                      ▼
                    LOGIN
                      │
                      ▼
             Sanctum authentication
```

### Invariants

```text
1 User = 1 Customer
Registration = User + Customer + customer role
Email verification = separate lifecycle step
Role = server controlled
Registration = no access token
Development email = Mailpit
Production email provider can be swapped later
```

**Implementation rule:** gunakan PRD ini sebagai source of truth, tetapi sebelum coding lakukan audit terhadap branch/repository terbaru dan jangan mengasumsikan file/model yang belum terkonfirmasi ada di codebase.
