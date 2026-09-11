# PRD.md

# RBAC — Role Based Access Control

## 1. Document Information

| Item           | Value                            |
| -------------- | -------------------------------- |
| Project        | JualAntar API                    |
| Feature        | Role Based Access Control (RBAC) |
| Module         | `IdentityAccess`                 |
| Architecture   | Laravel Modular Monolith         |
| Framework      | Laravel 13                       |
| Authentication | Laravel Sanctum                  |
| Authorization  | Spatie Laravel Permission        |
| Testing        | Pest                             |
| Status         | Planned                          |

---

# 2. Background

JualAntar membutuhkan mekanisme authorization yang memungkinkan sistem membatasi akses pengguna berdasarkan role dan permission.

Authentication yang sudah tersedia hanya memastikan bahwa pengguna telah terautentikasi. Sistem belum memiliki mekanisme terstruktur untuk menjawab:

- siapa yang boleh mengakses endpoint tertentu;
- siapa yang boleh mengelola user;
- siapa yang boleh mengelola merchant;
- siapa yang boleh mengelola driver;
- siapa yang boleh melihat atau mengubah data tertentu;
- bagaimana role dan permission dapat dikembangkan ketika modul bisnis JualAntar bertambah.

Feature ini akan menambahkan RBAC menggunakan package **Spatie Laravel Permission** dengan tetap mengikuti aturan Modular Monolith pada `docs/ARCHITECTURE.md`.

---

# 3. Architectural Context

JualAntar menggunakan Modular Monolith.

Struktur utama:

```text
app/
├── Modules/
│   ├── IdentityAccess/
│   ├── Geography/
│   ├── BankDirectory/
│   ├── Catalog/
│   ├── Ordering/
│   ├── Logistics/
│   └── Payment/
│
└── Shared/
```

`IdentityAccess` diklasifikasikan sebagai **Generic Domain** dan bertanggung jawab terhadap:

- user identity;
- authentication;
- authorization;
- role;
- permission;
- access control.

RBAC tidak boleh ditempatkan di `app/Models`, `app/Services`, atau `app/Http` global.

Semua kode feature ini harus berada di:

```text
app/Modules/IdentityAccess/
```

Aturan arsitektur wajib:

1. Domain model `IdentityAccess` bersifat privat.
2. Modul lain tidak boleh menggunakan model Spatie secara langsung.
3. Modul lain tidak boleh meng-query tabel RBAC secara langsung.
4. Inter-module communication hanya menggunakan `Contracts`.
5. Migration berada di dalam `IdentityAccess/Database/Migrations`.
6. Seeder berada di dalam `IdentityAccess/Database/Seeders`.
7. Route berada di `IdentityAccess/Routes/api.php`.
8. Service provider menjadi titik registrasi modul.
9. Shared Kernel tidak boleh mengetahui detail RBAC.

---

# 4. Problem Statement

Saat ini:

```text
Authenticated User
       │
       ▼
Sanctum
       │
       ▼
Endpoint
```

Belum terdapat authorization layer:

```text
Authenticated User
       │
       ▼
Role / Permission
       │
       ▼
Authorization
       │
       ▼
Endpoint
```

Akibatnya endpoint yang membutuhkan access control akan sulit dikembangkan secara konsisten ketika domain JualAntar semakin besar.

---

# 5. Goals

## 5.1 Primary Goals

Implementasi harus memungkinkan sistem:

1. membuat role;
2. membuat permission;
3. memberikan permission ke role;
4. memberikan role ke user;
5. mencabut role dari user;
6. mencabut permission dari role;
7. memeriksa permission user;
8. memeriksa role user;
9. melindungi endpoint menggunakan permission;
10. melindungi endpoint menggunakan role;
11. menampilkan role dan permission user melalui API;
12. mengelola RBAC melalui API;
13. melakukan seed role dan permission awal;
14. melakukan automated testing;
15. tetap mengikuti modular monolith boundary.

---

# 6. Non Goals

Feature ini tidak mencakup:

- Attribute Based Access Control (ABAC);
- Policy engine kompleks;
- Multi-tenant authorization;
- Organization-level permission;
- dynamic permission expression;
- OAuth2 authorization server;
- LDAP authorization;
- external IAM provider;
- permission inheritance;
- hierarchical role;
- audit log authorization tingkat lanjut.

Semua hal tersebut dapat menjadi future enhancement.

---

# 7. Technology Decision

Gunakan:

```bash
composer require spatie/laravel-permission
```

Package digunakan sebagai implementation authorization.

Konsep:

```text
IdentityAccess
│
├── Domain
│   └── Models
│       └── User
│
├── Application
│
├── Infrastructure
│   └── Authorization
│
├── Http
│
├── Database
│
├── Routes
│
└── Contracts
```

Spatie tidak boleh membuat architecture baru yang berdiri di luar module.

---

# 8. RBAC Model

Relationship:

```text
User
  │
  │ many-to-many
  ▼
Role
  │
  │ many-to-many
  ▼
Permission
```

Contoh:

```text
User: Thomas

Roles:
- super-admin

Permissions:
- users.view
- users.create
- users.update
- users.delete
- roles.view
- roles.create
- roles.update
- roles.delete
- permissions.view
```

---

# 9. Initial Role Design

Role awal:

## 9.1 `super-admin`

Full system access.

Permission:

```text
*
```

atau mekanisme `super-admin` dari Spatie.

Catatan:

Jangan membuat ratusan permission hard-coded hanya untuk mensimulasikan super admin.

---

## 9.2 `admin`

Administrative access.

Contoh:

```text
users.view
users.create
users.update

roles.view
roles.create
roles.update

permissions.view
```

---

## 9.3 `customer`

Customer application access.

Contoh:

```text
profile.view
profile.update
orders.view
orders.create
```

---

## 9.4 `merchant`

Merchant application access.

Contoh:

```text
profile.view
profile.update

merchant.view
merchant.update

products.view
products.create
products.update
products.delete

orders.view
```

---

## 9.5 `driver`

Driver application access.

Contoh:

```text
profile.view
profile.update

deliveries.view
deliveries.update
```

Role `driver` digunakan untuk pengguna yang menjalankan aktivitas pengantaran pada platform JualAntar.

Role ini menggantikan istilah `courier`.

---

# 10. Permission Naming Convention

Permission wajib menggunakan format:

```text
resource.action
```

Contoh:

```text
users.view
users.create
users.update
users.delete

roles.view
roles.create
roles.update
roles.delete

permissions.view
permissions.create
permissions.update
permissions.delete

profile.view
profile.update

merchant.view
merchant.update

products.view
products.create
products.update
products.delete

orders.view
orders.create
orders.update

deliveries.view
deliveries.update
```

Jangan menggunakan format:

```text
can_manage_users
user-management
manageUser
```

Gunakan lowercase dot notation.

---

# 11. Permission Ownership

Permission harus dibuat berdasarkan capability dari module.

Contoh:

```text
IdentityAccess
├── users.view
├── users.create
├── users.update
├── users.delete
├── roles.view
├── roles.create
├── roles.update
├── roles.delete
└── permissions.view
```

Ketika `Logistics` dibuat:

```text
Logistics
├── deliveries.view
└── deliveries.update
```

Permission `deliveries.*` dimiliki secara konseptual oleh capability Logistics, bukan oleh IdentityAccess.

IdentityAccess hanya bertanggung jawab terhadap mekanisme RBAC.

---

# 12. Directory Structure

```text
app/
└── Modules/
    └── IdentityAccess/
        ├── module.json
        ├── IdentityAccessServiceProvider.php
        │
        ├── Contracts/
        │   ├── IdentityAccessFacade.php
        │   └── DataTransferObjects/
        │       ├── RoleData.php
        │       └── PermissionData.php
        │
        ├── Domain/
        │   └── Models/
        │       └── User.php
        │
        ├── Application/
        │   ├── Actions/
        │   │   ├── AssignRoleToUser.php
        │   │   ├── RemoveRoleFromUser.php
        │   │   ├── AssignPermissionToRole.php
        │   │   └── RemovePermissionFromRole.php
        │   │
        │   └── Services/
        │
        ├── Infrastructure/
        │   └── Authorization/
        │       └── SpatieAuthorizationService.php
        │
        ├── Http/
        │   ├── Controllers/
        │   │   ├── RoleController.php
        │   │   ├── PermissionController.php
        │   │   └── UserRoleController.php
        │   │
        │   ├── Requests/
        │   │   ├── StoreRoleRequest.php
        │   │   ├── UpdateRoleRequest.php
        │   │   ├── StorePermissionRequest.php
        │   │   └── AssignRoleRequest.php
        │   │
        │   └── Resources/
        │       ├── RoleResource.php
        │       ├── PermissionResource.php
        │       └── UserResource.php
        │
        ├── Database/
        │   ├── Migrations/
        │   ├── Seeders/
        │   │   └── RbacSeeder.php
        │   └── Factories/
        │
        ├── Routes/
        │   └── api.php
        │
        └── Tests/
            ├── Feature/
            │   ├── RoleApiTest.php
            │   ├── PermissionApiTest.php
            │   ├── UserRoleApiTest.php
            │   └── AuthorizationTest.php
            │
            └── Unit/
                └── AuthorizationServiceTest.php
```

---

# 13. Step 0 — Current State Audit

Sebelum mengubah kode:

```bash
git checkout main
git pull
git checkout -b feature/identity-access-rbac
```

Lakukan audit:

```bash
php artisan about
php artisan route:list
php artisan migrate:status
composer show
```

Pastikan:

- Laravel berjalan;
- Sanctum berjalan;
- IdentityAccess provider terdaftar;
- User model berjalan;
- existing authentication tests tetap pass.

Jalankan:

```bash
php artisan test
```

Tidak boleh ada regression sebelum RBAC dimulai.

---

# 14. Step 1 — Install Spatie Permission

Install:

```bash
composer require spatie/laravel-permission
```

Publikasikan migration/config yang diperlukan:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

Hasil publikasi harus direview.

Jangan langsung menerima struktur default package tanpa melakukan penyesuaian terhadap architecture JualAntar.

---

# 15. Step 2 — Configure Permission Package

Konfigurasi:

```text
config/permission.php
```

Pastikan konfigurasi guard sesuai dengan authentication JualAntar.

RBAC menggunakan:

```text
sanctum
```

Permission dan role harus menggunakan guard yang konsisten.

---

# 16. Step 3 — Place Spatie Tables Inside IdentityAccess Ownership

Migration RBAC harus menjadi milik:

```text
app/Modules/IdentityAccess/Database/Migrations/
```

Target table:

```text
roles
permissions
model_has_roles
model_has_permissions
role_has_permissions
```

Jika menggunakan PostgreSQL sesuai architecture:

```text
identity_access.roles
identity_access.permissions
identity_access.model_has_roles
identity_access.model_has_permissions
identity_access.role_has_permissions
```

Jika menggunakan MySQL:

```text
identity_access_roles
identity_access_permissions
identity_access_model_has_roles
identity_access_model_has_permissions
identity_access_role_has_permissions
```

Gunakan convention database yang sedang digunakan project.

Semua foreign key internal RBAC boleh digunakan.

Tidak boleh ada foreign key lintas module.

---

# 17. Step 4 — Update User Model

Update:

```text
app/Modules/IdentityAccess/Domain/Models/User.php
```

Tambahkan:

```php
use Spatie\Permission\Traits\HasRoles;
```

Kemudian:

```php
use HasRoles;
```

User tetap menggunakan:

```php
class User extends Authenticatable
```

serta mempertahankan:

```text
HasApiTokens
HasFactory
Notifiable
```

---

# 18. Step 5 — Role Model

Gunakan default Spatie Role Model jika tidak terdapat kebutuhan customization.

Jika custom model diperlukan, letakkan di:

```text
app/Modules/IdentityAccess/Domain/Models/
```

Model tetap bersifat internal terhadap `IdentityAccess`.

---

# 19. Step 6 — Permission Model

Gunakan Spatie Permission model.

Jika customization tidak diperlukan, gunakan implementation bawaan package.

Model tidak boleh digunakan langsung oleh module lain.

---

# 20. Step 7 — Define Authorization Contract

Buat:

```text
app/Modules/IdentityAccess/Contracts/Authorization.php
```

Contoh:

```php
interface Authorization
{
    public function userHasRole(
        int|string $userId,
        string $role
    ): bool;

    public function userHasPermission(
        int|string $userId,
        string $permission
    ): bool;
}
```

Contract hanya mengekspos kebutuhan authorization yang benar-benar diperlukan oleh module lain.

---

# 21. Step 8 — Implement Authorization Contract

Implementasi:

```text
app/Modules/IdentityAccess/Infrastructure/Authorization/
    SpatieAuthorization.php
```

Implementation menggunakan Spatie.

Register pada:

```text
IdentityAccessServiceProvider
```

Contoh:

```php
$this->app->bind(
    Authorization::class,
    SpatieAuthorization::class,
);
```

---

# 22. Step 9 — Create RBAC Seeder

Buat:

```text
app/Modules/IdentityAccess/Database/Seeders/RbacSeeder.php
```

Seeder bertanggung jawab terhadap:

```text
Permissions
    ↓
Roles
    ↓
Role Permissions
```

Urutan:

1. create permissions;
2. create roles;
3. sync permissions to roles;
4. assign default roles jika diperlukan.

Seeder harus idempotent.

---

# 23. Step 10 — Default Roles Seeder

Initial roles:

```text
super-admin
admin
customer
merchant
driver
```

Jangan otomatis memberikan `super-admin` kepada seluruh existing users.

Untuk development/testing, factory dapat menyediakan:

```text
superAdmin()
admin()
customer()
merchant()
driver()
```

---

# 24. Step 11 — Create Role API

Endpoint:

```http
GET    /api/v1/roles
POST   /api/v1/roles
GET    /api/v1/roles/{role}
PUT    /api/v1/roles/{role}
DELETE /api/v1/roles/{role}
```

Authorization:

```text
roles.view
roles.create
roles.update
roles.delete
```

---

# 25. Step 12 — Create Permission API

Endpoint:

```http
GET    /api/v1/permissions
POST   /api/v1/permissions
GET    /api/v1/permissions/{permission}
PUT    /api/v1/permissions/{permission}
DELETE /api/v1/permissions/{permission}
```

Authorization:

```text
permissions.view
permissions.create
permissions.update
permissions.delete
```

---

# 26. Step 13 — Assign Permission to Role

Endpoint:

```http
POST /api/v1/roles/{role}/permissions
```

Request:

```json
{
    "permissions": ["users.view", "users.create", "users.update"]
}
```

Action:

```text
AssignPermissionToRole
```

---

# 27. Step 14 — Remove Permission from Role

Endpoint:

```http
DELETE /api/v1/roles/{role}/permissions
```

Request:

```json
{
    "permissions": ["users.delete"]
}
```

Action:

```text
RemovePermissionFromRole
```

---

# 28. Step 15 — Assign Role to User

Endpoint:

```http
POST /api/v1/users/{user}/roles
```

Request:

```json
{
    "roles": ["merchant"]
}
```

Action:

```text
AssignRoleToUser
```

---

# 29. Step 16 — Remove Role from User

Endpoint:

```http
DELETE /api/v1/users/{user}/roles
```

Request:

```json
{
    "roles": ["merchant"]
}
```

Action:

```text
RemoveRoleFromUser
```

---

# 30. Step 17 — Get Current User Authorization

Existing:

```http
GET /api/v1/user
```

Response dapat ditingkatkan menjadi:

```json
{
    "data": {
        "id": 1,
        "name": "Thomas",
        "email": "example@example.com",
        "roles": ["admin"],
        "permissions": ["users.view", "users.create"],
        "created_at": "...",
        "updated_at": "..."
    }
}
```

Jangan expose password atau token.

---

# 31. Step 18 — Protect Routes

Gunakan:

```text
auth:sanctum
```

ditambah authorization middleware.

Contoh:

```php
Route::middleware([
    'auth:sanctum',
    'permission:roles.view',
])->get('/roles', ...);
```

---

# 32. Step 19 — Authorization Failure

Unauthenticated:

```text
401 Unauthorized
```

Authenticated tetapi tidak memiliki permission:

```text
403 Forbidden
```

Response harus mengikuti `ProblemDetails` dan:

```text
Content-Type: application/problem+json
```

---

# 33. Step 20 — Validation

Role:

```json
{
    "name": "admin"
}
```

Permission:

```json
{
    "name": "users.view"
}
```

Assign role:

```json
{
    "roles": ["driver"]
}
```

Validation harus memastikan role/permission valid dan tidak menyebabkan duplicate data.

---

# 34. Step 21 — Resource Layer

Gunakan:

```text
RoleResource
PermissionResource
UserResource
```

User resource dapat menampilkan:

```json
{
    "id": 1,
    "name": "Driver JualAntar",
    "email": "driver@example.com",
    "roles": ["driver"]
}
```

Permission hanya ditampilkan jika use case membutuhkannya.

---

# 35. Step 22 — Pagination

Role dan permission listing harus menggunakan:

```php
ApiResponse::paginated(...)
```

Jangan membuat pagination response baru.

---

# 36. Step 23 — Search & Filtering

MVP:

```text
GET /api/v1/roles?search=admin
GET /api/v1/permissions?search=user
```

Tidak perlu membuat abstraction query builder yang kompleks.

---

# 37. Step 24 — Prevent Dangerous Deletion

Role tidak boleh dihapus jika masih digunakan oleh user.

Contoh:

```text
Role driver
    ↓
masih digunakan oleh users
    ↓
DELETE
    ↓
409 Conflict
```

Role system seperti:

```text
super-admin
```

harus protected.

---

# 38. Step 25 — Protect Super Admin

`super-admin` merupakan system role.

Hanya super-admin yang dapat melakukan operasi sensitif terhadap super-admin.

User biasa tidak boleh mengirim:

```json
{
    "roles": ["super-admin"]
}
```

untuk memberikan privilege tersebut kepada dirinya sendiri.

---

# 39. Step 26 — Super Admin Authorization

Gunakan mekanisme:

```php
Gate::before(...)
```

untuk memberikan super-admin full ability.

Konsep:

```text
super-admin
      ↓
all permissions
```

---

# 40. Step 27 — Cache Permission

Setelah perubahan permission/role, pastikan cache permission Spatie invalidated dengan benar.

Test:

```text
assign permission
      ↓
request endpoint
      ↓
permission langsung berlaku
```

---

# 41. Step 28 — Service Provider

Update:

```text
app/Modules/IdentityAccess/IdentityAccessServiceProvider.php
```

Responsibilities:

```text
register()
    └── authorization binding

boot()
    ├── migrations
    └── routes
```

Tidak boleh berisi business logic.

---

# 42. Step 29 — module.json

Update:

```text
app/Modules/IdentityAccess/module.json
```

Contoh:

```json
{
    "name": "IdentityAccess",
    "description": "Autentikasi, identitas, role, permission, dan authorization pengguna.",
    "classification": "generic",
    "depends_on": [],
    "exposes_contracts": [
        "App\\Modules\\IdentityAccess\\Contracts\\Authorization"
    ]
}
```

---

# 43. Step 30 — Route Organization

Semua endpoint RBAC berada pada:

```text
app/Modules/IdentityAccess/Routes/api.php
```

Semua endpoint RBAC wajib:

```text
auth:sanctum
```

dan authorization permission yang sesuai.

---

# 44. Step 31 — Controller Responsibility

Controller hanya menangani:

```text
HTTP
 ↓
Request
 ↓
Action
 ↓
Resource
```

Controller tidak boleh mengimplementasikan business logic RBAC.

---

# 45. Step 32 — Application Actions

Minimal:

```text
CreateRole
UpdateRole
DeleteRole

CreatePermission
UpdatePermission
DeletePermission

AssignRoleToUser
RemoveRoleFromUser

AssignPermissionToRole
RemovePermissionFromRole
```

Satu Action = satu use case.

---

# 46. Step 33 — Result Pattern

Pertahankan:

```text
Result::ok()
Result::err()
```

Application layer tidak menggunakan exception untuk normal business failure.

Contoh:

```text
role_in_use
protected_role
permission_in_use
invalid_role
invalid_permission
```

---

# 47. Step 34 — Database Transaction

Gunakan transaction untuk mutation yang terdiri dari beberapa database operation.

Contoh:

```text
Assign permission
      ↓
Update role
      ↓
Sync permissions
```

Gunakan:

```php
DB::transaction(...)
```

jika memang diperlukan.

---

# 48. Step 35 — API Endpoint Matrix

## Roles

| Method | Endpoint        | Permission     |
| ------ | --------------- | -------------- |
| GET    | `/roles`        | `roles.view`   |
| POST   | `/roles`        | `roles.create` |
| GET    | `/roles/{role}` | `roles.view`   |
| PUT    | `/roles/{role}` | `roles.update` |
| DELETE | `/roles/{role}` | `roles.delete` |

## Permissions

| Method | Endpoint                    | Permission           |
| ------ | --------------------------- | -------------------- |
| GET    | `/permissions`              | `permissions.view`   |
| POST   | `/permissions`              | `permissions.create` |
| GET    | `/permissions/{permission}` | `permissions.view`   |
| PUT    | `/permissions/{permission}` | `permissions.update` |
| DELETE | `/permissions/{permission}` | `permissions.delete` |

## Role Permission

| Method | Endpoint                    | Permission     |
| ------ | --------------------------- | -------------- |
| POST   | `/roles/{role}/permissions` | `roles.update` |
| DELETE | `/roles/{role}/permissions` | `roles.update` |

## User Role

| Method | Endpoint              | Permission     |
| ------ | --------------------- | -------------- |
| GET    | `/users/{user}/roles` | `users.view`   |
| POST   | `/users/{user}/roles` | `users.update` |
| DELETE | `/users/{user}/roles` | `users.update` |

---

# 49. Step 36 — Authentication vs Authorization

### Guest

```text
GET /api/v1/roles
        ↓
401 Unauthorized
```

### Authenticated tanpa permission

```text
GET /api/v1/roles
        ↓
403 Forbidden
```

### Authenticated dengan permission

```text
GET /api/v1/roles
        ↓
200 OK
```

Ketiga scenario wajib ditest.

---

# 50. Step 37 — Feature Tests

Buat:

```text
app/Modules/IdentityAccess/Tests/Feature/RoleApiTest.php
app/Modules/IdentityAccess/Tests/Feature/PermissionApiTest.php
app/Modules/IdentityAccess/Tests/Feature/UserRoleApiTest.php
app/Modules/IdentityAccess/Tests/Feature/AuthorizationTest.php
```

Test minimum:

- guest → 401;
- unauthorized user → 403;
- authorized user → success;
- super-admin → full access;
- duplicate role → validation error;
- duplicate permission → validation error;
- role in use → conflict;
- invalid role → validation error;
- invalid permission → validation error.

---

# 51. Step 38 — Driver Role Tests

Test khusus:

```text
User
 ↓
driver role
 ↓
deliveries.view
deliveries.update
```

Expected:

```text
driver
    → deliveries.view       ALLOWED
    → deliveries.update     ALLOWED
```

Tetapi:

```text
driver
    → roles.create          DENIED
    → users.delete          DENIED
```

---

# 52. Step 39 — Unit Tests

Unit test authorization implementation:

```text
userHasRole()
userHasPermission()
```

Tidak perlu menjalankan HTTP stack.

---

# 53. Step 40 — Arch Tests

Tambahkan architecture tests untuk memastikan:

```text
IdentityAccess Domain
        ↓
hanya digunakan IdentityAccess
```

dan:

```text
Shared
        ↓
tidak boleh bergantung
        ↓
IdentityAccess
```

Module lain tidak boleh menggunakan Spatie model secara langsung.

---

# 54. Step 41 — Cross Module Boundary

Forbidden:

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
```

di:

```text
Catalog
Ordering
Logistics
Payment
Geography
BankDirectory
```

Allowed:

```php
use App\Modules\IdentityAccess\Contracts\Authorization;
```

---

# 55. Step 42 — Permission Naming Governance

Permission harus mengikuti ownership capability.

Contoh:

```text
IdentityAccess
├── users.view
├── users.create
├── users.update
└── users.delete
```

```text
Logistics
├── deliveries.view
└── deliveries.update
```

```text
Ordering
├── orders.view
├── orders.create
└── orders.update
```

Role hanya menggabungkan permission dari berbagai capability.

---

# 56. Step 43 — Seeder Verification

Jalankan:

```bash
php artisan db:seed \
  --class="App\Modules\IdentityAccess\Database\Seeders\RbacSeeder"
```

Jalankan kembali command yang sama.

Expected:

```text
No duplicate roles.
No duplicate permissions.
No errors.
```

---

# 57. Step 44 — Factory Helpers

UserFactory dapat menyediakan:

```php
User::factory()->admin()->create();

User::factory()->customer()->create();

User::factory()->merchant()->create();

User::factory()->driver()->create();

User::factory()->superAdmin()->create();
```

Factory helper hanya untuk testing/development.

---

# 58. Step 45 — API Documentation

Dokumentasikan seluruh endpoint:

```text
GET    /roles
POST   /roles
GET    /roles/{role}
PUT    /roles/{role}
DELETE /roles/{role}

GET    /permissions
POST   /permissions
GET    /permissions/{permission}
PUT    /permissions/{permission}
DELETE /permissions/{permission}

POST   /roles/{role}/permissions
DELETE /roles/{role}/permissions

GET    /users/{user}/roles
POST   /users/{user}/roles
DELETE /users/{user}/roles
```

---

# 59. Step 46 — Security Requirements

RBAC wajib:

1. tidak expose password;
2. tidak expose token;
3. tidak expose internal Spatie implementation;
4. mencegah privilege escalation;
5. melindungi `super-admin`;
6. melakukan authorization server-side;
7. tidak mempercayai role dari client;
8. tidak mempercayai permission dari client;
9. membatasi role assignment;
10. melindungi role/permission deletion.

---

# 60. Step 47 — Privilege Escalation Test

Scenario:

```text
customer
    ↓
POST /api/v1/users/{id}/roles
    ↓
role = super-admin
```

Expected:

```text
403 Forbidden
```

Scenario:

```text
driver
    ↓
POST /api/v1/users/{id}/roles
    ↓
role = admin
```

Expected:

```text
403 Forbidden
```

Scenario:

```text
super-admin
    ↓
POST /api/v1/users/{id}/roles
    ↓
role = driver
```

Expected:

```text
success
```

---

# 61. Step 48 — Logging

Operation sensitif dapat dicatat:

```text
role assigned
role removed
permission assigned
permission removed
super-admin granted
super-admin revoked
```

Jangan log:

```text
password
access token
refresh token
secret
```

---

# 62. Step 49 — Performance

Pastikan:

- Spatie permission cache aktif;
- tidak terjadi N+1;
- roles tidak selalu eager-load permissions;
- users tidak selalu eager-load roles + permissions;
- gunakan query sesuai kebutuhan endpoint.

---

# 63. Step 50 — Migration Verification

Jalankan:

```bash
php artisan migrate:fresh
```

Kemudian:

```bash
php artisan db:seed \
  --class="App\Modules\IdentityAccess\Database\Seeders\RbacSeeder"
```

Kemudian:

```bash
php artisan migrate:status
```

---

# 64. Step 51 — Full Test

Jalankan:

```bash
php artisan test
```

Pastikan:

```text
Feature tests       PASS
Unit tests          PASS
Authorization tests PASS
Arch tests          PASS
Existing tests      PASS
```

---

# 65. Step 52 — Code Formatting

Jalankan:

```bash
vendor/bin/pint
```

Kemudian jalankan kembali test suite.

---

# 66. Step 53 — Route Audit

Jalankan:

```bash
php artisan route:list
```

Verifikasi setiap RBAC endpoint mempunyai:

```text
auth:sanctum
+
appropriate permission
```

---

# 67. Step 54 — Manual API Verification

### Scenario A — Customer

```text
customer
→ GET /roles
```

Expected:

```text
403
```

### Scenario B — Admin

```text
admin
→ GET /roles
```

Expected:

```text
200
```

### Scenario C — Merchant

```text
merchant
→ GET /roles
```

Expected:

```text
403
```

### Scenario D — Driver

```text
driver
→ GET /roles
```

Expected:

```text
403
```

### Scenario E — Super Admin

```text
super-admin
→ POST /users/{id}/roles
→ role: driver
```

Expected:

```text
success
```

---

# 68. Step 55 — Definition of Done

## Architecture

- [ ] Semua RBAC code berada di `IdentityAccess`.
- [ ] Tidak ada RBAC logic di `app/Services`.
- [ ] Tidak ada RBAC model di `app/Models`.
- [ ] Service provider digunakan sebagai module bootstrap.
- [ ] Contract tersedia untuk cross-module authorization.
- [ ] Arch test boundary pass.

## Package

- [ ] Spatie Permission terinstall.
- [ ] Configuration reviewed.
- [ ] Sanctum guard configured.
- [ ] Permission cache working.

## Database

- [ ] Migration tersedia.
- [ ] Migration berada dalam module.
- [ ] RBAC tables berada pada ownership IdentityAccess.
- [ ] Tidak ada FK lintas module.

## Seeder

- [ ] `super-admin` seeded.
- [ ] `admin` seeded.
- [ ] `customer` seeded.
- [ ] `merchant` seeded.
- [ ] `driver` seeded.
- [ ] permissions seeded.
- [ ] role-permission seeded.
- [ ] Seeder idempotent.

## API

- [ ] CRUD role.
- [ ] CRUD permission.
- [ ] Assign role to user.
- [ ] Remove role from user.
- [ ] Assign permission to role.
- [ ] Remove permission from role.
- [ ] Current user menampilkan roles/permissions.

## Security

- [ ] 401 works.
- [ ] 403 works.
- [ ] Privilege escalation blocked.
- [ ] `super-admin` protected.
- [ ] `driver` tidak dapat mengakses administrative endpoint.
- [ ] Sensitive data tidak exposed.

## Testing

- [ ] Role feature tests.
- [ ] Permission feature tests.
- [ ] User role feature tests.
- [ ] Driver authorization tests.
- [ ] Unit tests.
- [ ] Arch tests.
- [ ] Full test suite passing.

## Documentation

- [ ] OpenAPI updated.
- [ ] `module.json` updated.
- [ ] Module documentation updated.

---

# 69. Implementation Order

```text
1. Audit current state
        ↓
2. Create feature branch
        ↓
3. Install Spatie
        ↓
4. Configure package
        ↓
5. Move/adapt migrations into IdentityAccess
        ↓
6. Update User model
        ↓
7. Register Spatie integration
        ↓
8. Create RBAC seeder
        ↓
9. Seed permissions
        ↓
10. Seed roles
        ↓
11. Create authorization contract
        ↓
12. Implement authorization infrastructure
        ↓
13. Create Role actions
        ↓
14. Create Permission actions
        ↓
15. Create User Role actions
        ↓
16. Create controllers
        ↓
17. Create requests
        ↓
18. Create resources
        ↓
19. Register API routes
        ↓
20. Add authorization middleware
        ↓
21. Protect super-admin
        ↓
22. Add feature tests
        ↓
23. Add driver authorization tests
        ↓
24. Add unit tests
        ↓
25. Add arch tests
        ↓
26. Run migrations
        ↓
27. Run seeders
        ↓
28. Run full tests
        ↓
29. Run Pint
        ↓
30. Manual API verification
        ↓
31. Update documentation
        ↓
32. Code review
        ↓
33. Merge
```

---

# 70. Recommended Commit Strategy

```text
feat(identity-access): install spatie permission

feat(identity-access): configure rbac database

feat(identity-access): integrate spatie with user model

feat(identity-access): add rbac seeder

feat(identity-access): add authorization contract

feat(identity-access): add role management api

feat(identity-access): add permission management api

feat(identity-access): add user role management api

feat(identity-access): protect rbac endpoints

test(identity-access): add rbac feature tests

test(identity-access): add driver authorization tests

test(identity-access): add authorization tests

test(architecture): enforce identity access boundary

docs(identity-access): document rbac
```

---

# 71. Recommended Branch

```text
feature/identity-access-rbac
```

Setelah seluruh test pass:

```text
feature/identity-access-rbac
        ↓
Pull Request
        ↓
Code Review
        ↓
main
```

---

# 72. Future Extension

RBAC harus dapat berkembang menuju:

```text
IdentityAccess
│
├── Authentication
├── Authorization
├── Users
├── Roles
├── Permissions
├── Sessions
├── Devices
└── Audit
```

Future business model:

```text
User
 ├── Customer
 ├── Merchant
 └── Driver
```

Role `driver` nantinya dapat berintegrasi dengan module `Logistics`.

Contoh:

```text
IdentityAccess
      │
      │ Authorization Contract
      ▼
Logistics
      │
      ├── deliveries.view
      └── deliveries.update
```

IdentityAccess tidak mengetahui detail internal Logistics.

---

# 73. Architectural Constraints

Developer wajib melakukan architecture review apabila implementasi membutuhkan:

```text
Catalog → Role model
Ordering → Permission model
Logistics → User model
Payment → Role model
```

Solusi:

```text
Module
   ↓
IdentityAccess Contract
   ↓
DTO
```

Bukan:

```text
Module
   ↓
IdentityAccess Domain Model
```

---

# 74. Final Target Architecture

```text
                         ┌──────────────────────┐
                         │      API Client      │
                         └──────────┬───────────┘
                                    │
                                    ▼
                         ┌──────────────────────┐
                         │     Sanctum Auth     │
                         └──────────┬───────────┘
                                    │
                                    ▼
                    ┌──────────────────────────────┐
                    │      IdentityAccess          │
                    │                              │
                    │ User                         │
                    │   │                          │
                    │   ▼                          │
                    │ Role                         │
                    │   │                          │
                    │   ▼                          │
                    │ Permission                   │
                    │   │                          │
                    │   ▼                          │
                    │ Spatie Permission            │
                    └──────────────┬───────────────┘
                                   │
                         Authorization Contract
                                   │
              ┌────────────────────┼────────────────────┐
              ▼                    ▼                    ▼
          Catalog              Ordering              Logistics
                                                       │
                                                       ▼
                                                    Driver
```

Role model:

```text
User
 │
 ├── super-admin
 ├── admin
 ├── customer
 ├── merchant
 └── driver
```

`driver` adalah istilah resmi yang digunakan oleh JualAntar untuk aktor yang menjalankan proses pengantaran.

---

# 75. Success Criteria

Feature berhasil apabila:

```text
User
 ↓
Login via Sanctum
 ↓
Authenticated
 ↓
Role
 ↓
Permission
 ↓
Authorization
 ↓
API Endpoint
```

dan:

```text
super-admin → full access
admin       → administrative access
merchant    → merchant capabilities
customer    → customer capabilities
driver      → delivery capabilities
```

dengan seluruh implementasi tetap mengikuti:

```text
Modular Monolith
        +
Explicit Module Boundary
        +
Spatie Authorization
        +
Sanctum Authentication
        +
Contract-based Communication
        +
Automated Testing
```

# End of PRD
