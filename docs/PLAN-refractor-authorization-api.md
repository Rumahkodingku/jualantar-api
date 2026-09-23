# PLAN.md --- Refactor Authorization API JualAntar

> **Catatan revisi:** Dokumen ini adalah hasil perapian dari draf sebelumnya.
> Isi/keputusan teknis **tidak berubah**, tetapi:
>
> 1.  Penomoran fase dirapikan menjadi **satu urutan eksekusi yang konsisten**
>     (draf sebelumnya punya dua urutan berbeda: urutan judul section dan
>     urutan di "Migration Order", yang saling bertentangan).
> 2.  Fase yang **menghentikan penulisan Spatie role global**
>     (`CreateOutletEmployee`, `AssignOutletUser`, `ChangeOutletUserRole`,
>     `RemoveOutletUser`) dipindah ke **akhir**, setelah mekanisme
>     enforcement baru (capability resolver, contextual authorization
>     service, route middleware) benar-benar siap. Urutan lama berisiko
>     membuat seluruh employee ter-_lockout_ (403 di semua endpoint outlet)
>     jika dieksekusi apa adanya dari atas ke bawah.
> 3.  Ditambahkan catatan bertanda **⚠️** di beberapa fase, berisi temuan
>     konkret dari audit langsung ke repository `jualantar-api` (bukan
>     asumsi), supaya tim/agent yang mengeksekusi plan ini tidak perlu
>     menebak ulang.
> 4.  Lihat **Lampiran A** di bagian akhir untuk peta "nomor fase lama →
>     nomor fase baru".

---

## 1. Tujuan

Dokumen ini menjadi rencana implementasi untuk memperbaiki model
authorization pada `jualantar-api` sebelum dilakukan perubahan
authorization/UI pada `jualantar-merchant`.

Fokus utama:

1.  Memisahkan **global/account role** dari **outlet-scoped role**.
2.  Menjadikan `merchant_outlet_users.role` sebagai source of truth
    untuk role employee pada outlet tertentu.
3.  Menghentikan penggunaan role `outlet_manager` dan `outlet_staff`
    sebagai global Spatie roles.
4.  Memastikan setiap operasi terhadap outlet mempertimbangkan konteks:
    - authenticated user;
    - merchant;
    - outlet;
    - outlet assignment;
    - outlet role;
    - capability/action.
5.  Menyediakan authorization contract yang stabil untuk frontend
    Merchant.
6.  Menutup celah authorization akibat permission global yang tidak
    memiliki konteks outlet.
7.  Menyediakan test coverage sebelum frontend mulai bergantung pada
    model authorization baru.

> Prinsip utama: **Frontend boleh menyembunyikan menu, tetapi API tetap
> menjadi security boundary.**

---

## 2. Masalah Saat Ini

### 2.1 Dua sumber role untuk konteks yang sama

Saat employee ditambahkan, API saat ini menyimpan role di:

```text
merchant.merchant_outlet_users.role
```

dan juga memberikan role yang sama sebagai Spatie role pada user:

```text
outlet_manager
outlet_staff
```

Secara konsep ini menghasilkan dua sumber data:

```text
merchant_outlet_users.role
        +
Spatie roles
```

Padahal keduanya merepresentasikan hal yang berbeda.

> ⚠️ **Dikonfirmasi di kode.** Pola ini terjadi persis di empat action:
> `CreateOutletEmployee.php`, `AssignOutletUser.php`,
> `ChangeOutletUserRole.php`, dan `RemoveOutletUser.php`
> (`app/Modules/Merchant/Application/Operations/Actions/`). Semuanya
> memanggil `$this->authorization->assignRole()` /
> `removeRole()` di samping menulis `merchant_outlet_users.role`.
> `outlet_manager` dan `outlet_staff` juga sudah terdaftar sebagai role
> Spatie global lengkap dengan daftar permission-nya di
> `app/Modules/IdentityAccess/Database/Seeders/RbacSeeder.php`.

### 2.2 Outlet role sebenarnya bersifat contextual

Contoh:

```text
Budi
├── Outlet A → outlet_manager
└── Outlet B → outlet_staff
```

`merchant_outlet_users` dapat merepresentasikan kondisi tersebut dengan
benar.

Namun global Spatie role hanya dapat melihat:

```text
Budi
├── outlet_manager
└── outlet_staff
```

Tidak ada informasi bahwa `outlet_manager` hanya berlaku untuk Outlet A.

Akibatnya, permission dapat menjadi gabungan:

```text
permissions(outlet_manager)
        ∪
permissions(outlet_staff)
```

dan user berpotensi memperoleh capability manager ketika sedang bekerja
pada outlet yang seharusnya hanya memberinya role staff.

> ⚠️ **Ini bukan risiko teoretis.** Endpoint outlet-scoped seperti
> `PUT /outlets/{outlet}/operating-hours` hanya dijaga oleh middleware
> permission global (lihat 2.3) ditambah
> `MerchantOperationsAuthorization::authorizedOutlet()`, yang hanya
> mengecek "apakah user assigned ke outlet ini", **bukan** "apakah role
> user di outlet ini punya capability ini". Tidak ada test yang
> menguji skenario satu user dengan role berbeda di dua outlet
> sekaligus di `MerchantOperationsApiTest.php` saat draf ini ditulis.

### 2.3 Route permission middleware belum cukup

Route seperti:

```php
->middleware('permission:merchant.operations.hours.update,sanctum')
```

hanya menjawab:

> Apakah user mempunyai permission tersebut?

Yang dibutuhkan untuk endpoint outlet adalah:

> Apakah user mempunyai permission tersebut **untuk outlet yang sedang
> diakses**?

Jadi authorization harus memiliki outlet context.

> ⚠️ Pola middleware ini dipakai di hampir seluruh route outlet-scoped
> di `app/Modules/Merchant/Routes/api.php` (outlets, outlet users,
> operating-hours, service-area, availability). Ini penting untuk
> Phase 5 di bawah: middleware ini **satu-satunya gate** sebelum
> controller dijalankan, jadi tidak bisa langsung dihapus tanpa
> pengganti.

---

## 3. Target Authorization Model

Model yang dituju:

```text
User
│
├── Global / Account Role
│      └── merchant
│
└── Merchant Outlet Assignments
       │
       ├── Outlet A → outlet_manager
       └── Outlet B → outlet_staff
```

### 3.1 Global role

Spatie digunakan untuk role yang memang berlaku secara
global/account-level.

Contoh:

```text
merchant
customer
admin
```

Daftar final harus mengikuti role yang benar-benar digunakan repository
(lihat `RbacSeeder::ROLE_PERMISSIONS` untuk daftar saat ini:
`super-admin`, `customer`, `merchant`, `driver`, plus
`outlet_manager`/`outlet_staff` yang akan dihapus dari daftar Spatie).

### 3.2 Outlet role

Role berikut dipertahankan sebagai domain role:

```text
outlet_manager
outlet_staff
```

dan disimpan di:

```text
merchant.merchant_outlet_users.role
```

Role tersebut **tidak lagi diberikan sebagai global Spatie role**.

### 3.3 Source of truth

Informasi Source of Truth

---

Account/global role Spatie
User ↔ merchant/outlet membership `merchant_outlet_users`
Role user pada outlet `merchant_outlet_users.role`
Capability pada outlet mapping domain `outlet role → capabilities`
Authorization terhadap outlet contextual authorization service

---

# 4. Scope Pekerjaan

## In Scope

- Refactor employee creation (`CreateOutletEmployee`, `AssignOutletUser`).
- Refactor employee role update & removal (`ChangeOutletUserRole`,
  `RemoveOutletUser`).
- Refactor outlet authorization.
- Refactor role/capability mapping.
- Audit endpoint outlet.
- Audit middleware/policies/authorization service.
- Penyesuaian `/auth/me` atau endpoint authorization context.
- Migration/cleanup data role global.
- Unit/integration/feature tests.
- Backward compatibility strategy.
- Documentation internal untuk authorization model.

## Out of Scope

Hal-hal berikut **jangan dikerjakan pada fase API ini**:

- redesign UI Merchant;
- filtering sidebar/menu frontend;
- redesign dashboard;
- perubahan UX employee management;
- perubahan navigation;
- implementasi authorization UI secara penuh;
- integrasi ke module lain yang tidak diperlukan untuk authorization
  outlet.

Frontend Merchant dikerjakan setelah API authorization contract stabil.

---

# 5. Prinsip Implementasi

## 5.1 Jangan menghapus `merchant_outlet_users.role`

Field tersebut bukan redundant.

Field tersebut diperlukan karena role bersifat:

```text
user + outlet
```

bukan:

```text
user
```

## 5.2 Jangan mengganti outlet role dengan Spatie global role

Jangan melakukan solusi:

```text
merchant_outlet_users.role → dihapus
outlet_manager/outlet_staff → Spatie global
```

Ini akan memperburuk masalah multi-outlet.

## 5.3 Jangan menjadikan frontend sebagai security boundary

Frontend boleh melakukan:

```text
hide menu
disable button
redirect
show 403 page
```

tetapi API tetap wajib melakukan authorization.

## 5.4 Authorization harus menggunakan resource context

Untuk endpoint:

```text
/outlets/{outlet}
```

authorization harus mengetahui outlet tersebut.

## 5.5 Urutan eksekusi wajib mencegah lockout (baru)

Enforcement baru (capability resolver + contextual authorization
service + route middleware yang sudah diperbarui) **harus sudah live
dan terverifikasi** sebelum kode berhenti menulis Spatie role global.

Jangan menghentikan `assignRole()`/`removeRole()` di action
(`CreateOutletEmployee`, `AssignOutletUser`, `ChangeOutletUserRole`,
`RemoveOutletUser`) selama route outlet-scoped masih semata-mata
dijaga oleh middleware `permission:merchant.operations.*` yang lama.
Jika urutan ini dilanggar, seluruh `outlet_manager`/`outlet_staff`
akan kehilangan akses ke endpoint outlet begitu Spatie role mereka
kosong, karena middleware tersebut adalah satu-satunya gate saat ini.

---

# 6. Phase 0 --- Baseline & Inventory

## Tujuan

Membuat baseline sebelum kode diubah.

## Tasks

### 6.1 Inventarisasi role

Cari seluruh penggunaan:

```text
outlet_manager
outlet_staff
merchant
```

di:

- seeders;
- migrations;
- models;
- actions;
- services;
- policies;
- middleware;
- controllers;
- resources;
- tests;
- factories;
- fixtures.

> ⚠️ **Titik awal (hasil audit awal, verifikasi ulang sebelum mulai):**
>
> - Enum: `app/Modules/Merchant/Domain/Enums/OutletUserRole.php`
>   (sudah ada, pertahankan).
> - Model: `app/Modules/Merchant/Domain/Models/MerchantOutletUser.php`
>   (sudah cast `role` ke `OutletUserRole`, sudah bersih).
> - Seeder: `app/Modules/IdentityAccess/Database/Seeders/RbacSeeder.php`
>   (`outlet_manager`/`outlet_staff` masih terdaftar sebagai Spatie
>   role di `ROLE_PERMISSIONS`).
> - Actions: `CreateOutletEmployee.php`, `AssignOutletUser.php`,
>   `ChangeOutletUserRole.php`, `RemoveOutletUser.php` (keempatnya
>   memanggil `assignRole`/`removeRole`).
> - Tests: `MerchantOperationsApiTest.php`,
>   `MerchantDomainTest.php`, `AuthorizationServiceTest.php`
>   menyebut `outlet_manager`/`outlet_staff` secara langsung, termasuk
>   assertion `hasRole('outlet_manager')` yang akan pecah setelah
>   refactor (lihat Phase 14).

### 6.2 Inventarisasi permission

Audit permission:

```text
merchant.operations.*
```

dan kelompokkan:

```text
merchant/global
outlet-scoped
employee-management
outlet-management
hours
service-area
availability
```

> ⚠️ Daftar permission lengkap sudah ada di
> `RbacSeeder::PERMISSIONS`; gunakan itu sebagai starting point, jangan
> membuat daftar baru dari nol.

### 6.3 Inventarisasi endpoint outlet

Buat daftar semua endpoint yang menerima:

```text
outlet_id
```

atau:

```text
/outlets/{outlet}
```

atau secara tidak langsung bekerja terhadap outlet.

> ⚠️ Semua endpoint outlet-scoped saat ini terdaftar di
> `app/Modules/Merchant/Routes/api.php` di bawah prefix
> `merchant/operations`, dilayani oleh empat controller:
> `OutletOperationsController`, `OutletUsersController`,
> `OperatingHoursController`, `ServiceAreaController`.

### 6.4 Inventarisasi authorization

Cari seluruh penggunaan:

```text
permission middleware
hasRole
hasAnyRole
hasPermission
authorize
policy
Gate
MerchantOperationsAuthorization
```

> ⚠️ `MerchantOperationsAuthorization` (di
> `app/Modules/Merchant/Application/Operations/Services/`) sudah ada
> dan sudah punya `authorizedOutlet()` yang membedakan 403 (assigned
> ke merchant yang sama tapi outlet lain) dari 404 (merchant asing).
> Ini fondasi yang bagus untuk Phase 3 — jangan ditulis ulang dari
> nol, cukup diperluas. Method `can()` di service yang sama saat ini
> hanya dipakai di satu tempat (`CreateOperationalUpload.php`) dan
> masih berbasis permission global, bukan capability per-outlet.

### 6.5 Baseline test

Jalankan test suite sebelum perubahan dan catat:

- jumlah test;
- pass;
- fail;
- skipped;
- coverage jika tersedia.

### Acceptance Criteria

- Seluruh role/permission outlet sudah terinventarisasi.
- Semua endpoint outlet sudah memiliki daftar.
- Baseline test terdokumentasi.

---

# 7. Phase 1 --- Define Authorization Contract

Sebelum refactor implementasi, tetapkan kontrak domain.

## 7.1 Domain roles

Pertahankan:

```text
outlet_manager
outlet_staff
```

sebagai `OutletUserRole`.

Contoh conceptual enum:

```php
enum OutletUserRole: string
{
    case OUTLET_MANAGER = 'outlet_manager';
    case OUTLET_STAFF = 'outlet_staff';
}
```

> ⚠️ Enum ini **sudah ada** di
> `app/Modules/Merchant/Domain/Enums/OutletUserRole.php` dengan nama
> case `OutletManager`/`OutletStaff`. Gunakan yang sudah ada, jangan
> buat ulang.

## 7.2 Capability mapping

Definisikan mapping capability per outlet role.

Contoh:

### outlet_manager

```text
merchant.operations.view
merchant.operations.outlets.view
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
merchant.operations.availability.update
```

### outlet_staff

```text
merchant.operations.view
merchant.operations.outlets.view

merchant.operations.hours.view
merchant.operations.service_area.view
merchant.operations.availability.view
```

> ⚠️ Daftar di atas identik dengan `ROLE_PERMISSIONS['outlet_manager']`
> dan `ROLE_PERMISSIONS['outlet_staff']` yang ada sekarang di
> `RbacSeeder.php`. Artinya mapping capability ini bisa langsung
> "dipindahkan" dari seeder Spatie ke resolver domain (Phase 2) tanpa
> perlu didesain ulang — cukup dipindahkan sumber kebenarannya.

## 7.3 Bedakan role dari permission

Jangan menggunakan:

```text
role == outlet_manager
```

sebagai authorization check di seluruh code.

Gunakan konsep:

```text
assignment.role
        ↓
capabilities
        ↓
requested action
```

Dengan demikian perubahan permission tidak harus mengubah seluruh
controller.

### Acceptance Criteria

- Domain role final disepakati dan cocok dengan enum yang sudah ada.
- Capability mapping final per role didokumentasikan dan disetujui.
- Tidak ada perbandingan role literal (`=== 'outlet_manager'`) yang
  direncanakan sebagai mekanisme authorization permanen.

---

# 8. Phase 2 --- Permission/Capability Resolver

Buat satu resolver untuk:

```text
OutletUserRole → capabilities
```

Conceptual interface:

```php
interface OutletRoleCapabilityResolver
{
    public function permissionsFor(OutletUserRole $role): array;

    public function allows(
        OutletUserRole $role,
        string $permission
    ): bool;
}
```

Implementasi dapat berupa:

- config;
- enum;
- dedicated domain class;
- value object;

pilih yang paling sesuai dengan architecture repository (mis.
`app/Modules/Merchant/Domain/Authorization/` — lihat Phase 25).

## Rule

Jangan menduplikasi mapping di:

- controller;
- policy;
- middleware;
- frontend;
- service.

Satu source of truth di backend, diisi dari mapping capability yang
sudah disepakati di Phase 1.2.

Fase ini murni menambah kode baru (belum mengubah perilaku endpoint
mana pun), sehingga aman dikerjakan dan diuji secara terisolasi
sebelum menyentuh action atau route.

### Acceptance Criteria

- Resolver tersedia dan diuji unit (setiap kombinasi role × permission
  sesuai mapping Phase 1.2).
- Tidak ada endpoint yang memanggil resolver ini (belum diwire) --
  itu tugas Phase 3--6.

---

# 9. Phase 3 --- Contextual Authorization Service

Ini merupakan bagian inti.

Authorization service harus dapat menjawab:

```text
Can user perform action X on outlet Y?
```

Conceptual API:

```php
authorizeOutletAction(
    User $user,
    Outlet $outlet,
    string $permission
): void
```

atau equivalent yang mengikuti architecture repository.

> ⚠️ `MerchantOperationsAuthorization::authorizedOutlet()` sudah
> mengerjakan sebagian besar alur di bawah ini (resolve outlet, cek
> membership, bedakan 403/404). Tambahkan method baru, misalnya
> `authorizeOutletAction(string $outletId, string $capability)`, yang
> memanggil `authorizedOutlet()` lalu, jika sukses, memanggil resolver
> dari Phase 2 dengan `assignment->role` untuk memvalidasi capability.
> Jangan menulis ulang logika membership/403/404 yang sudah benar.

## Flow

```text
User
 ↓
Find merchant outlet assignment
 ↓
Is user assigned to outlet?
 ├── No → deny
 │
 └── Yes
       ↓
Read assignment.role
       ↓
Resolve role capabilities
       ↓
Check requested capability
       ├── No → deny
       └── Yes → allow
```

## Required checks

Authorization harus memvalidasi:

1.  user authenticated;
2.  outlet exists;
3.  outlet belongs to merchant yang benar;
4.  user memiliki membership/assignment ke outlet;
5.  assignment aktif jika domain memiliki status active/inactive;
6.  role assignment memiliki capability;
7.  action yang diminta sesuai capability;
8.  merchant owner (lihat Phase 8) mendapat bypass eksplisit, bukan
    implisit lewat role Spatie.

### Acceptance Criteria

- Method contextual baru tersedia dan diuji unit terpisah dari
  `authorizedOutlet()` yang lama.
- Owner tetap bisa melakukan seluruh aksi (lihat Phase 8).
- Belum ada route/controller yang wajib pakai method ini -- itu
  tugas Phase 5--6. Fase ini fokus menyediakan kapabilitasnya.

---

# 10. Phase 4 --- Outlet Access Helper

Buat satu abstraksi yang dapat dipakai oleh seluruh endpoint.

Conceptual behavior:

```php
$assignment = $merchantOutletUsers
    ->forUser($user)
    ->forOutlet($outlet)
    ->first();
```

Jika tidak ada:

```text
403 Forbidden
```

Jangan mengandalkan query controller yang berbeda-beda untuk menentukan
akses.

## Goal

Menghindari implementasi seperti:

```text
Controller A → query assignment sendiri
Controller B → query assignment dengan kondisi berbeda
Controller C → hanya check merchant_id
Controller D → hanya check Spatie permission
```

Semua harus menuju authorization abstraction yang konsisten.

> ⚠️ Untuk repo ini, abstraksi tersebut **adalah**
> `MerchantOperationsAuthorization` (Phase 3). Tugas fase ini adalah
> memastikan seluruh controller Operations (`OutletOperationsController`,
> `OutletUsersController`, `OperatingHoursController`,
> `ServiceAreaController`) memanggilnya dengan cara yang sama --
> termasuk yang saat ini hanya memanggil `authorizedOutlet()` tanpa
> lanjut ke pengecekan capability (mis. `OperatingHoursController`,
> `ServiceAreaController` saat ini tidak memanggil `can()` sama
> sekali).

### Acceptance Criteria

- Tidak ada controller Operations yang melakukan query assignment
  sendiri di luar `MerchantOperationsAuthorization`.

---

# 11. Phase 5 --- Refactor Route Authorization

Current pattern:

```php
permission:merchant.operations.hours.update
```

tidak boleh menjadi satu-satunya protection untuk outlet-scoped
endpoint.

## Target

Route middleware dapat tetap dipakai untuk coarse/global authorization
jika masih berguna.

Tetapi endpoint harus melakukan contextual authorization.

Conceptual:

```text
HTTP request
 ↓
Authentication
 ↓
Route/resource resolution
 ↓
Outlet contextual authorization
 ↓
Controller/action
```

## Contoh

Request:

```text
PATCH /outlets/{outlet}/hours
```

Authorization:

```text
User Budi
Outlet = Outlet B
Assignment = outlet_staff
Action = hours.update
Result = DENY
```

Sedangkan:

```text
User Budi
Outlet = Outlet A
Assignment = outlet_manager
Action = hours.update
Result = ALLOW
```

## ⚠️ Keputusan wajib untuk repo ini (bukan opsional)

Middleware `permission:merchant.operations.*,sanctum` pada seluruh
route outlet-scoped di `app/Modules/Merchant/Routes/api.php` saat ini
adalah **satu-satunya gate** sebelum controller dijalankan. Begitu
Phase 9/10 menghentikan pemberian Spatie role ke
`outlet_manager`/`outlet_staff`, employee akan kehilangan permission
Spatie tersebut sepenuhnya, dan middleware ini akan menolak mereka
dengan 403 di semua endpoint outlet -- termasuk pada outlet yang
memang menjadi haknya.

Pilih salah satu, dan dokumentasikan pilihannya:

1.  **Ganti middleware** pada route outlet-scoped menjadi pengecekan
    coarse yang tidak bergantung pada permission per-capability (mis.
    cukup `auth:sanctum` + memiliki minimal satu assignment/merchant
    context), lalu pindahkan pengecekan capability spesifik ke
    `authorizeOutletAction()` (Phase 3) di dalam controller/action.
2.  **Pertahankan middleware**, tetapi ganti basis pengecekannya dari
    Spatie permission per-user menjadi permission yang dihitung dari
    capability resolver (Phase 2) + assignment outlet dari route
    parameter -- ini butuh custom middleware baru, bukan middleware
    `permission:` bawaan Spatie yang tidak outlet-aware.

Opsi 1 lebih sederhana untuk arsitektur repo ini karena
`MerchantOperationsAuthorization` sudah dipakai di semua controller
terkait.

**Fase ini wajib selesai dan sudah di-deploy sebelum Phase 9 dan
Phase 10 dieksekusi** (lihat Prinsip 5.5).

### Acceptance Criteria

- Tidak ada endpoint outlet-scoped yang otorisasinya murni bergantung
  pada Spatie permission global.
- Employee dengan capability yang sesuai tetap bisa mengakses
  endpoint meski Spatie role global mereka nanti dikosongkan
  (diverifikasi dengan test yang menonaktifkan/mengosongkan Spatie
  role secara manual di test, mensimulasikan kondisi setelah
  Phase 9/10).

---

# 12. Phase 6 --- Audit Semua Outlet Endpoints

Buat matrix endpoint:

---

Endpoint/Action Resource Scope Required Capability Authorization

---

outlet detail outlet outlet outlets.view contextual

outlet update outlet outlet outlets.update contextual

outlet status outlet outlet outlets.status.update contextual

employee list outlet outlet outlet_users.view contextual

employee assign outlet outlet outlet_users.assign contextual

employee remove outlet outlet outlet_users.remove contextual

employee role outlet outlet outlet_users.role.update contextual
update

hours view outlet outlet hours.view contextual

hours update outlet outlet hours.update contextual

service area view outlet outlet service_area.view contextual

service area outlet outlet service_area.update contextual
update

availability view outlet outlet availability.view contextual

availability outlet outlet availability.update contextual
update

---

Daftar final wajib berasal dari endpoint aktual repository -- pemetaan
di atas sudah dicocokkan dengan `app/Modules/Merchant/Routes/api.php`
per draf ini, tetapi verifikasi ulang saat eksekusi (route bisa
berubah).

## Audit question per endpoint

Untuk setiap endpoint:

1.  Apa resource-nya?
2.  Outlet mana yang sedang diakses?
3.  Bagaimana outlet tersebut ditemukan?
4.  Apakah user assigned ke outlet?
5.  Apa role assignment-nya?
6.  Apa capability yang diperlukan?
7.  Apakah global merchant owner mempunyai bypass?
8.  Apakah endpoint dapat menerima ID resource dari outlet lain?
9.  Apakah response juga harus dibatasi ke outlet yang authorized?

> ⚠️ Pertahankan perilaku `authorizedOutlet()` yang sudah ada:
> membedakan **403** (outlet milik merchant yang sama tetapi di luar
> assignment user) dari **404** (outlet milik merchant lain / tidak
> ditemukan). Jangan disederhanakan menjadi satu status saja saat
> endpoint-endpoint ini diaudit ulang.

### Acceptance Criteria

- Setiap endpoint di matrix memanggil `authorizeOutletAction()`
  (Phase 3) dengan capability yang benar, bukan hanya
  `authorizedOutlet()` tanpa lanjutan pengecekan capability.

---

# 13. Phase 7 --- Prevent Cross-Outlet Access (Verification Gate)

Ini wajib diuji, dan menjadi **gate** sebelum lanjut ke Phase 9/10.

Contoh:

```text
Budi
Outlet A → manager
Outlet B → staff
```

Request:

```text
PATCH /outlets/B/hours
```

harus menghasilkan:

```text
403
```

meskipun:

```text
Budi memiliki capability hours.update
```

karena capability tersebut berasal dari assignment Outlet A, bukan
Outlet B.

Ini adalah alasan utama authorization harus contextual.

> ⚠️ Test dengan skenario persis di atas **belum ada** di
> `MerchantOperationsApiTest.php` saat draf ini ditulis (setiap
> pemanggilan helper `operationsEmployee()` di test yang ada hanya
> membuat satu assignment per user). Tambahkan sebagai test baru,
> bukan modifikasi test yang sudah ada, dan jadikan syarat wajib lulus
> sebelum Phase 9/10 dimulai.

### Acceptance Criteria

- Test multi-outlet (manager@A + staff@B) hijau dan dijalankan di
  CI sebelum merge Phase 9/10.

---

# 14. Phase 8 --- Merchant Owner / Global Merchant Access

API harus menetapkan secara eksplisit bagaimana owner/global merchant
bekerja.

Jangan mengandalkan efek samping Spatie role.

Tentukan rule:

```text
Merchant owner
    ↓
Can access all outlets?
```

Jika iya, implementasikan sebagai explicit authorization rule.

Conceptual:

```text
if user is merchant owner
    allow according to owner capabilities

else
    resolve outlet assignment
```

Jangan membuat:

```text
merchant owner → otomatis outlet_manager
```

karena owner dan outlet manager merupakan konsep domain berbeda.

> ⚠️ `MerchantOperationsAuthorization::isOwner()` sudah ada dan
> dipakai di `authorizedOutlet()`. Pastikan
> `authorizeOutletAction()` (Phase 3) memanggil `isOwner()` sebagai
> bypass eksplisit di awal alur, bukan lewat efek samping Spatie role
> `merchant`.

### Acceptance Criteria

- Aturan bypass owner terdokumentasi dan diuji (lihat juga Phase 15).

---

# 15. Phase 9 --- Refactor Employee Creation

> ⚠️ **Baru boleh dimulai setelah Phase 1--8 selesai dan Phase 7
> hijau** (lihat Prinsip 5.5).

Target file/area:

```text
CreateOutletEmployee
AssignOutletUser
```

## Current behavior

Secara konseptual saat ini:

```php
$assignment = MerchantOutletUser::create([
    'merchant_id' => $merchant->id,
    'outlet_id' => $outlet->id,
    'user_id' => $user->id,
    'role' => $role,
]);

$authorization->assignRole($user->id, $role);
```

## Target behavior

```php
MerchantOutletUser::create([
    'merchant_id' => $merchant->id,
    'outlet_id' => $outlet->id,
    'user_id' => $user->id,
    'role' => $role,
]);
```

Tidak lagi:

```php
assignRole($user, 'outlet_manager')
assignRole($user, 'outlet_staff')
```

> ⚠️ Ada **dua** action dengan pola identik di repo ini:
> `CreateOutletEmployee.php` (membuat akun user baru sekaligus
> assignment) dan `AssignOutletUser.php` (assign user yang sudah ada
> ke outlet). Keduanya memanggil `assignRole()` dan keduanya harus
> difix bersamaan -- jangan hanya salah satu.

## Important

Sebelum menghapus assignment Spatie dari flow:

- cek apakah role tersebut dipakai module lain;
- cek apakah ada user lama yang masih memiliki role global tersebut;
- cek seeder/factory;
- cek authorization non-outlet.

### Acceptance Criteria

- Employee baru (lewat `CreateOutletEmployee` maupun
  `AssignOutletUser`) tetap mendapatkan `merchant_outlet_users.role`.
- Employee baru tidak mendapatkan global Spatie
  `outlet_manager/outlet_staff`.
- Global account role tidak berubah secara tidak sengaja.
- Endpoint terkait tetap bisa diakses employee (dibuktikan oleh test
  Phase 5 & Phase 14), karena enforcement baru sudah live.

---

# 16. Phase 10 --- Refactor Change & Remove Outlet User Role

> ⚠️ **Baru boleh dimulai setelah Phase 1--8 selesai dan Phase 7
> hijau** (lihat Prinsip 5.5), dapat dikerjakan paralel dengan
> Phase 9.

Target:

```text
ChangeOutletUserRole
RemoveOutletUser
```

## Current problem

Flow saat ini melakukan:

```text
update assignment role
        +
remove old Spatie role
        +
assign new Spatie role
```

`RemoveOutletUser` melakukan pola simetris: hapus assignment, lalu
`removeRole()` Spatie jika user tidak punya assignment lain dengan
role yang sama.

## Target

Role change hanya mengubah:

```text
merchant_outlet_users.role
```

Contoh:

```php
$assignment->update([
    'role' => $newRole,
]);
```

Tidak ada:

```php
removeRole(...)
assignRole(...)
```

untuk outlet role. `RemoveOutletUser` cukup menghapus baris
`merchant_outlet_users` tanpa memanggil `removeRole()`.

## Multi-outlet case

Contoh:

```text
Budi
Outlet A → manager
Outlet B → staff
```

Jika Outlet A diubah:

```text
Outlet A → staff
```

hasil:

```text
Outlet A → staff
Outlet B → staff
```

Tidak ada perubahan global role.

### Acceptance Criteria

- Perubahan role hanya memengaruhi assignment target.
- Assignment outlet lain tidak berubah.
- Global Spatie role tidak berubah (dan setelah Phase 9/10 selesai,
  `outlet_manager`/`outlet_staff` tidak lagi dipakai sebagai Spatie
  role sama sekali).
- Effective permission berubah sesuai assignment target.
- Penghapusan assignment (`RemoveOutletUser`) tidak menyisakan efek
  samping pada Spatie role user.

---

# 17. Phase 11 --- `/auth/me` Authorization Contract

Frontend nantinya membutuhkan data untuk membangun UI berdasarkan
authorization.

Current response (`UserResource.php`,
`app/Modules/IdentityAccess/Http/Resources/`) sudah memiliki konsep:

```text
roles
permissions
```

Setelah refactor, global `permissions` tidak boleh digunakan frontend
sebagai representasi permission outlet jika permission tersebut memang
contextual.

## Target contract

Conceptual:

```json
{
    "id": "...",
    "email": "...",
    "roles": ["merchant"],
    "permissions": [],
    "outlet_assignments": [
        {
            "outlet_id": "...",
            "role": "outlet_manager"
        },
        {
            "outlet_id": "...",
            "role": "outlet_staff"
        }
    ]
}
```

Atau bentuk lain yang lebih sesuai dengan architecture API.

## Decision yang harus dibuat

Tentukan apakah aplikasi memiliki:

### Model A --- Active outlet

User memilih satu outlet aktif:

```text
active_outlet_id
```

Authorization menggunakan outlet tersebut.

### Model B --- Outlet context dari route

Contoh:

```text
/outlets/{outletId}/...
```

Authorization mengambil outlet langsung dari resource/route.

### Model C --- Hybrid

Frontend memilih active outlet untuk UX, tetapi API tetap memvalidasi
outlet dari request.

**Recommended architectural direction:** API tetap mempercayai
resource/route context sebagai source of authorization; active outlet
hanya menjadi UX state.

### Acceptance Criteria

- `UserResource` mengembalikan `outlet_assignments` (atau setara)
  berisi role per outlet.
- Frontend tidak perlu menebak permission outlet dari daftar
  `permissions` global.

---

# 18. Phase 12 --- Data Migration

Sebelum deployment, lakukan audit data.

## 18.1 Find legacy global outlet roles

Cari user yang masih memiliki:

```text
outlet_manager
outlet_staff
```

sebagai Spatie global role.

## 18.2 Reconcile dengan assignment

Untuk setiap user:

```text
global role
        vs
merchant_outlet_users.role
```

Pastikan assignment domain tersedia.

## 18.3 Migration strategy

Recommended:

```text
1. Deploy Phase 1-8 (enforcement baru: resolver, contextual
   authorization service, route authorization) dan verifikasi hijau
   (Phase 7).
2. Deploy Phase 9-10 (kode berhenti membuat/mengubah Spatie role
   outlet_manager/outlet_staff untuk operasi baru).
3. Migrate existing data (reconcile assignment lama).
4. Remove legacy global outlet roles dari user existing.
5. Verify.
6. Enable frontend authorization contract.
```

Jangan menghapus global roles sebelum data audit selesai, dan jangan
menjalankan langkah 2 sebelum langkah 1 selesai (lihat Prinsip 5.5).

## 18.4 Data anomaly cases

Tangani:

- user memiliki global outlet_manager tetapi tidak punya assignment;
- assignment ada tetapi role invalid;
- user punya assignment ke merchant yang tidak sesuai;
- user punya duplicate assignment;
- user memiliki beberapa assignment outlet dengan role berbeda.

Setiap anomaly harus dicatat dan diselesaikan secara eksplisit.

---

# 19. Phase 13 --- Database Integrity

Audit constraint pada:

```text
merchant_outlet_users
```

Pertimbangkan memastikan kombinasi yang seharusnya unik:

```text
merchant_id
outlet_id
user_id
```

tidak menghasilkan duplicate assignment.

Jika domain memang hanya mengizinkan satu assignment per user per
outlet, gunakan unique constraint yang sesuai.

> ⚠️ **Sudah terpenuhi.** Migration
> `create_merchant_outlet_users_table` sudah memiliki
> `unique(['outlet_id', 'user_id'])`. Fase ini berubah menjadi
> **verifikasi**, bukan pekerjaan baru, kecuali business rule berubah
> (mis. satu user boleh punya lebih dari satu role di outlet yang
> sama, yang saat ini tidak didukung constraint tersebut).

Jangan menambah constraint tanpa memastikan business rule aktual.

---

# 20. Phase 14 --- Tests

Testing adalah gate sebelum frontend.

## 20.1 Role assignment tests

### Case 1

```text
Create employee as manager
```

Expected:

```text
merchant_outlet_users.role = outlet_manager
Spatie outlet_manager role = absent
```

### Case 2

```text
Create employee as staff
```

Expected:

```text
merchant_outlet_users.role = outlet_staff
Spatie outlet_staff role = absent
```

> ⚠️ **Breaking test yang harus diperbarui:** test
> `'assigns, lists, changes and removes outlet users as the owner'`
> di `MerchantOperationsApiTest.php` saat ini secara eksplisit
> menegaskan `expect($target->fresh()->hasRole('outlet_manager'))
->toBeTrue()` dan `hasRole('outlet_staff')->toBeTrue()`. Setelah
> Phase 9/10, assertion ini harus dibalik menjadi `toBeFalse()` sesuai
> Case 1/2 di atas. Ini adalah perubahan yang **diharapkan**, bukan
> regresi -- tandai secara eksplisit di PR agar tidak membingungkan
> reviewer.

## 20.2 Role change tests

```text
manager → staff
```

Expected:

```text
assignment role = staff
global role unchanged
```

## 20.3 Multi-outlet tests

User:

```text
Outlet A → manager
Outlet B → staff
```

Test:

```text
A.hours.update → allow
B.hours.update → deny
```

> ⚠️ Ini adalah test yang sama dengan gate di Phase 7 -- pastikan
> tidak dikerjakan dua kali secara terpisah, cukup satu suite yang
> dipakai sebagai gate di kedua fase.

## 20.4 Unauthorized outlet tests

User tidak memiliki assignment:

```text
GET /outlets/C
```

Expected:

```text
403
```

atau status yang telah distandardisasi oleh API.

## 20.5 Cross-merchant tests

User merchant A mencoba mengakses outlet merchant B.

Expected:

```text
403
```

## 20.6 Permission matrix tests

Test seluruh capability:

```text
             manager   staff
view           ✓        ✓
update         ✓        ✗
assign         ✓        ✗
remove         ✓        ✗
role update    ✓        ✗
```

Matrix harus disesuaikan dengan capability final.

### Acceptance Criteria

- Seluruh test di 20.1--20.6 hijau, termasuk test yang sudah ada dan
  sudah diperbarui sesuai catatan di 20.1.

---

# 21. Phase 15 --- Authorization Regression Tests

Tambahkan regression tests untuk memastikan refactor tidak merusak
owner.

Minimum:

```text
Merchant owner
    → merchant-level access
    → outlet access
```

dan:

```text
Outlet manager
    → manager capabilities pada assigned outlet

Outlet staff
    → staff capabilities pada assigned outlet
```

Pastikan employee tidak mendapatkan capability karena global role lama.

---

# 22. Phase 16 --- Error Contract

Standardisasi response authorization.

Minimal bedakan:

```text
401 Unauthorized
```

untuk:

```text
not authenticated
```

dan:

```text
403 Forbidden
```

untuk:

```text
authenticated but not authorized
```

Pastikan frontend nantinya dapat membedakan:

```text
login expired
```

vs:

```text
tidak memiliki akses
```

---

# 23. Phase 17 --- Observability / Audit

Jika project sudah memiliki audit logging, gunakan untuk event
authorization penting.

Minimal pertimbangkan logging:

```text
employee role changed
employee assigned to outlet
employee removed from outlet
authorization denied for outlet action
```

Jangan memasukkan secret/token/password.

Tujuannya adalah mempermudah investigasi jika terdapat:

```text
403 unexpected
cross-outlet access attempt
role mismatch
```

---

# 24. Phase 18 --- Documentation

Setelah implementasi, dokumentasikan:

## Authorization architecture

```text
Global Role
    ↓
Account identity

Outlet Assignment Role
    ↓
Contextual capability

Outlet Resource
    ↓
Authorization
```

## Developer rule

Setiap developer yang membuat endpoint outlet harus mengikuti:

```text
1. Resolve outlet.
2. Resolve user assignment.
3. Resolve outlet role.
4. Check capability.
5. Execute operation.
```

Jangan membuat authorization baru di controller secara ad-hoc.

---

# 25. Recommended Backend Structure

Struktur final tidak harus persis seperti ini; sesuaikan dengan
architecture repository.

> ⚠️ Repo ini sudah memakai struktur modular
> (`Domain/Enums`, `Domain/Models`, `Application/Actions`,
> `Application/Services`, `Http/...` di dalam
> `app/Modules/Merchant/`), jadi tidak perlu restrukturisasi besar --
> cukup tambahkan kelas baru ke folder yang sudah ada, atau buat
> subfolder `Domain/Authorization/` untuk resolver baru.

Conceptual:

```text
Merchant/
├── Domain/
│   ├── Enums/
│   │   └── OutletUserRole.php            (sudah ada)
│   │
│   ├── Models/
│   │   └── MerchantOutletUser.php        (sudah ada)
│   │
│   └── Authorization/                     (baru)
│       ├── OutletRoleCapabilityResolver.php
│       └── OutletCapabilities.php
│
├── Application/
│   ├── Actions/
│   │   ├── CreateOutletEmployee.php      (sudah ada, direfactor)
│   │   ├── AssignOutletUser.php          (sudah ada, direfactor)
│   │   ├── ChangeOutletUserRole.php      (sudah ada, direfactor)
│   │   └── RemoveOutletUser.php          (sudah ada, direfactor)
│   │
│   └── Services/
│       └── MerchantOperationsAuthorization.php  (sudah ada, diperluas)
│
└── Presentation/
    ├── Controllers/
    ├── Requests/
    └── Resources/
```

Struktur aktual wajib mengikuti convention repository.

---

# 26. Migration Order

Urutan implementasi yang wajib diikuti -- section di dokumen ini sudah
disusun ulang sehingga **urutan section = urutan eksekusi yang aman**:

```text
Phase 0  — Baseline & inventory
   ↓
Phase 1  — Authorization contract (domain role + capability mapping)
   ↓
Phase 2  — Capability resolver
   ↓
Phase 3  — Contextual authorization service
   ↓
Phase 4  — Outlet access helper (konsolidasi pemakaian)
   ↓
Phase 5  — Refactor route authorization  ⚠️ wajib live sebelum Phase 9/10
   ↓
Phase 6  — Audit/refactor semua outlet endpoints
   ↓
Phase 7  — Verifikasi: prevent cross-outlet access (gate wajib hijau)
   ↓
Phase 8  — Merchant owner / global merchant access rules
   ↓
Phase 9  — Refactor employee creation (stop assignRole)
   ↓
Phase 10 — Refactor change/remove outlet user role (stop assignRole/removeRole)
   ↓
Phase 11 — /auth/me contract
   ↓
Phase 12 — Data migration (legacy Spatie role cleanup)
   ↓
Phase 13 — Database integrity (verifikasi, sudah sebagian terpenuhi)
   ↓
Phase 14 — Tests & regression
   ↓
Phase 15 — Owner regression tests
   ↓
Phase 16 — Error contract
   ↓
Phase 17 — Observability / audit logging
   ↓
Phase 18 — Documentation
   ↓
API authorization sign-off
   ↓
Frontend Merchant
```

**Aturan tidak boleh dilanggar:** Phase 9 dan Phase 10 tidak boleh
mulai dikerjakan -- apalagi di-deploy -- sebelum Phase 1 sampai 8
selesai dan Phase 7 hijau. Ini yang mencegah seluruh employee
kehilangan akses saat Spatie role global mereka dikosongkan.

---

# 27. Definition of Done

API dianggap selesai hanya jika seluruh kondisi berikut terpenuhi.

## Architecture

- [ ] Global role dan outlet role sudah dipisahkan.
- [ ] `merchant_outlet_users.role` menjadi source of truth untuk
      outlet role.
- [ ] `outlet_manager` dan `outlet_staff` tidak lagi digunakan sebagai
      global Spatie role.
- [ ] Capability mapping memiliki single source of truth.
- [ ] Outlet authorization bersifat contextual.

## Employee management

- [ ] Create employee (`CreateOutletEmployee`, `AssignOutletUser`)
      tidak assign global outlet role.
- [ ] Change employee role tidak memodifikasi global Spatie role.
- [ ] Remove employee (`RemoveOutletUser`) menghapus/revoke
      assignment sesuai domain rule, tanpa efek samping ke Spatie role.
- [ ] Assignment outlet lain tidak terpengaruh.

## Endpoint security

- [ ] Semua endpoint outlet sudah diaudit.
- [ ] Semua endpoint outlet menggunakan contextual authorization.
- [ ] Route middleware outlet-scoped tidak lagi bergantung semata
      pada Spatie permission global.
- [ ] Cross-outlet access ditolak.
- [ ] Cross-merchant access ditolak.
- [ ] Unassigned user ditolak.
- [ ] Owner/global merchant behavior terdokumentasi dan tested.

## Data

- [ ] Legacy global outlet roles teridentifikasi.
- [ ] Existing assignments direkonsiliasi.
- [ ] Data anomaly ditangani.
- [ ] Migration dapat dijalankan dengan aman.
- [ ] Tidak ada duplicate assignment yang tidak valid.

## Tests

- [ ] Unit tests capability resolver.
- [ ] Authorization service tests.
- [ ] Employee creation tests.
- [ ] Employee role change tests.
- [ ] Multi-outlet tests (manager@A + staff@B, gate Phase 7).
- [ ] Cross-outlet tests.
- [ ] Cross-merchant tests.
- [ ] Owner regression tests.
- [ ] Test lama yang mengasersi Spatie role (`hasRole(...)`) sudah
      diperbarui, bukan dihapus diam-diam.
- [ ] Full API test suite pass.

## Contract

- [ ] `/auth/me` atau endpoint authorization context telah
      didefinisikan.
- [ ] Frontend tidak perlu menebak permission dari global role.
- [ ] Outlet context dapat dipahami frontend.
- [ ] 401/403 behavior konsisten.

---

# 28. Frontend Gate

**Jangan mulai refactor authorization frontend sebelum checklist API
berikut selesai:**

```text
[ ] API role model final
[ ] API capability model final
[ ] API contextual authorization final
[ ] Outlet endpoint audit selesai
[ ] Legacy role migration selesai
[ ] /auth/me contract final
[ ] API tests green
[ ] Cross-outlet tests green
[ ] Cross-merchant tests green
```

Setelah semua green, baru `jualantar-merchant` dapat direfactor untuk:

```text
API authorization contract
        ↓
Frontend permission context
        ↓
Navigation filtering
        ↓
Route guards
        ↓
Page-level action guards
        ↓
Button/action visibility
        ↓
403 page
```

---

# 29. Risiko dan Mitigasi

## Risiko 1 --- Existing users masih memiliki global outlet roles

**Mitigasi:**

Migration + audit data sebelum cleanup.

## Risiko 2 --- Module lain bergantung pada global outlet roles

**Mitigasi:**

Search seluruh repository sebelum menghapus role dari Spatie.

Jika ditemukan dependency, migrasikan dependency tersebut terlebih
dahulu.

## Risiko 3 --- Frontend masih mengandalkan `permissions`

**Mitigasi:**

Jangan mengubah frontend bersamaan dengan refactor API.

Stabilkan authorization contract terlebih dahulu.

## Risiko 4 --- Owner kehilangan akses

**Mitigasi:**

Tambahkan regression test khusus merchant owner.

## Risiko 5 --- Cross-outlet privilege escalation

**Mitigasi:**

Wajib memiliki multi-outlet test:

```text
manager @ A
staff @ B
```

dan memastikan capability manager tidak bocor ke B.

## Risiko 6 --- Inconsistent authorization implementation

**Mitigasi:**

Gunakan centralized authorization service/resolver.

## Risiko 7 --- Employee lockout saat migrasi (baru)

Menghentikan Spatie `assignRole()`/`removeRole()` sebelum route
middleware dan contextual authorization siap akan membuat seluruh
`outlet_manager`/`outlet_staff` kehilangan akses ke semua endpoint
outlet, karena middleware permission global saat ini adalah satu-
satunya gate.

**Mitigasi:**

Ikuti urutan Phase pada Section 26 secara ketat: Phase 5 (route
authorization) dan Phase 1--8 (enforcement baru) wajib selesai dan
di-deploy sebelum Phase 9/10 dieksekusi. Uji dengan akun employee
sungguhan di staging sebelum ke produksi, dan jalankan test Phase 7
sebagai gate CI, bukan sekadar checklist manual.

---

# 30. Final Target Architecture

```text
                         ┌──────────────────────┐
                         │        User          │
                         └──────────┬───────────┘
                                    │
                   ┌────────────────┴────────────────┐
                   │                                 │
                   ▼                                 ▼
        ┌────────────────────┐          ┌────────────────────────┐
        │ Global Role / RBAC │          │ Outlet Assignment      │
        │                    │          │                        │
        │ merchant           │          │ merchant_id            │
        │ admin              │          │ outlet_id              │
        │ ...                │          │ user_id                │
        └────────────────────┘          │ role                   │
                                        └───────────┬────────────┘
                                                    │
                                                    ▼
                                      ┌────────────────────────┐
                                      │ OutletUserRole         │
                                      │                        │
                                      │ outlet_manager         │
                                      │ outlet_staff            │
                                      └───────────┬────────────┘
                                                  │
                                                  ▼
                                      ┌────────────────────────┐
                                      │ Capability Resolver    │
                                      └───────────┬────────────┘
                                                  │
                                                  ▼
                                      ┌────────────────────────┐
                                      │ Requested Action       │
                                      │                        │
                                      │ hours.update           │
                                      │ outlet_users.assign    │
                                      │ etc.                   │
                                      └───────────┬────────────┘
                                                  │
                                                  ▼
                                      ┌────────────────────────┐
                                      │ Contextual Authorization│
                                      │                        │
                                      │ user + merchant +      │
                                      │ outlet + capability    │
                                      └───────────┬────────────┘
                                                  │
                                      ┌───────────┴───────────┐
                                      ▼                       ▼
                                    ALLOW                    DENY
```

---

# 31. Kesimpulan

Refactor API ini bukan sekadar menghapus duplicate role.

Tujuan utamanya adalah mengubah authorization dari:

```text
User → Global Role → Permission
```

menjadi:

```text
User
  +
Outlet
  +
Outlet Assignment Role
  +
Capability
  =
Effective Authorization
```

Dengan model tersebut:

```text
Budi
├── Outlet A → manager
└── Outlet B → staff
```

dapat direpresentasikan dan diamankan dengan benar.

**Urutan yang wajib dipertahankan:**

```text
API authorization refactor (Phase 0-8: bangun enforcement baru)
        ↓
API employee-action refactor (Phase 9-10: hentikan Spatie role lama)
        ↓
API data migration (Phase 12)
        ↓
API tests & security verification (Phase 14-15)
        ↓
API contract freeze
        ↓
Frontend Merchant authorization refactor
```

Frontend Merchant **tidak boleh menjadi langkah pertama**, karena
frontend hanya dapat menyembunyikan UI, sedangkan masalah utama berada
pada model authorization API dan konteks outlet. Dan di dalam refactor
API sendiri, **fase yang membangun enforcement baru harus selesai
sebelum fase yang mencabut mekanisme lama**, agar tidak terjadi
lockout di tengah migrasi.

---

# Lampiran A --- Peta Nomor Fase Lama → Baru

Untuk keperluan traceability terhadap draf PLAN.md sebelumnya:

Fase (isi) Nomor lama Nomor baru

---

Baseline & Inventory Phase 0 Phase 0
Authorization contract Phase 1 Phase 1
Capability resolver Phase 6 Phase 2
Contextual authorization service Phase 4 Phase 3
Outlet access helper Phase 5 Phase 4
Refactor route authorization Phase 7 Phase 5
Audit semua outlet endpoints Phase 8 Phase 6
Prevent cross-outlet access Phase 9 Phase 7
Merchant owner / global access Phase 10 Phase 8
Refactor employee creation Phase 2 Phase 9
Refactor change/remove outlet user role Phase 3 Phase 10
`/auth/me` contract Phase 11 Phase 11
Data migration Phase 12 Phase 12
Database integrity Phase 13 Phase 13
Tests Phase 14 Phase 14
Authorization regression tests Phase 15 Phase 15
Error contract Phase 16 Phase 16
Observability / audit Phase 17 Phase 17
Documentation Phase 18 Phase 18

Hanya 9 fase (yang berubah nomor) yang urutan eksekusinya digeser;
isi/keputusan teknis setiap fase tidak berubah dari draf sebelumnya.
