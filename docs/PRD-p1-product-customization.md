# PRD — P1 Product Customization

## JualAntar Merchant Catalog

|               |                                                                    |
| ------------- | ------------------------------------------------------------------ |
| **Versi**     | v2 (revisi dari draft awal)                                        |
| **Status**    | Draft — menunggu konfirmasi keputusan di Bagian 15                 |
| **Fase**      | Step 3 — Merchant Catalog/Product · **P1 Product Customization**   |
| **Area**      | Merchant Application / Catalog                                     |
| **Backend**   | `jualantar-api` — `https://github.com/Rumahkodingku/jualantar-api` |
| **Prasyarat** | Step 1–2 ✅ · **P0 Core Catalog ✅** (PRD P0 v2)                   |

---

## 1. Tujuan & Posisi dalam Roadmap

```text
1. Merchant Approval        ✅
2. Merchant Operations      ✅
3. Merchant Catalog/Product
     ├── P0 Core Catalog            ✅
     └── P1 Product Customization   ← dokumen ini
4. Customer Ordering
5. Merchant Order Processing
6. Driver
7. Dispatch
8. End-to-end
```

P1 menambahkan kemampuan customization pada master product: owner merchant dapat membuat **Modifier Group** dan **Modifier** untuk sebuah product.

```text
Product
   └── Modifier Group   (aturan pemilihan: single/multiple, min, max, wajib)
          └── Modifier  (pilihan + harga tambahan)
```

Contoh:

```text
Burger Ayam
├── Pilihan Saus   (wajib pilih 1)
│   ├── Original       Rp 0
│   ├── Pedas          Rp 0
│   └── BBQ            Rp 0
└── Tambahan       (opsional, maks. 3)
    ├── Extra Cheese   Rp 5.000
    ├── Extra Egg      Rp 4.000
    └── Extra Chicken  Rp 8.000
```

**Batas P1:** P1 hanya mengelola **konfigurasi customization di master catalog** lewat endpoint merchant (owner). P1 tidak menyimpan pilihan customer, tidak menyediakan endpoint customer, dan tidak mengubah tabel P0.

---

## 2. Scope

### 2.1 Masuk P1

- Modifier Group: CRUD, tipe pemilihan, min/max selection, required, status, urutan.
- Modifier: CRUD, harga tambahan, status, default marker, urutan.
- Perubahan **additif** pada perilaku P0 (Bagian 3.4): respons product detail, respons outlet catalog, dan cascade soft delete product.

### 2.2 Di luar P1

| Item                                                                                      | Ditangani di                       |
| ----------------------------------------------------------------------------------------- | ---------------------------------- |
| Cart, order, checkout, payment, snapshot modifier pada order item, kuantitas per modifier | **Step 4** dan seterusnya          |
| Endpoint katalog untuk customer                                                           | **Step 4**                         |
| UI Merchant App (layar, alur, form)                                                       | Repo `jualantar-merchant`          |
| Nested creation (product + group + modifier dalam satu request)                           | Tidak dibuat (konsisten dengan P0) |
| Harga modifier per outlet, availability modifier per outlet                               | Belum dijadwalkan                  |
| Modifier khusus per variant                                                               | Belum dijadwalkan                  |
| Pustaka group yang dipakai ulang lintas product                                           | Belum dijadwalkan                  |
| Stok/inventory modifier, jadwal modifier, promosi, bundle/combo, resep/BOM                | Tidak ada di roadmap saat ini      |

---

## 3. Konteks Codebase & Prasyarat

### 3.1 Konvensi repo (terverifikasi dari `AGENTS.md`)

- Laravel 13, PHP 8.3, Sanctum, PostgreSQL 16, Pest 4 (Feature test memakai `RefreshDatabase` pada `jualantar_test`).
- Route di `routes/api.php`, group `prefix('v1')->name('api.v1.')`.
- Response lewat `App\Support\Http\ApiResponse`; error RFC 9457 lewat `ProblemDetailsFactory`; kode error baru wajib didaftarkan di `config/api.php`.
- Business logic di `app/Services`, mengembalikan `App\Support\Result\Result`; error bisnis memakai subclass `ApiException`.
- Serialisasi lewat API Resource; model memakai `#[Fillable]`/`#[Hidden]` + `casts()`; key enum TitleCase.
- `tests/Arch/ArchTest.php`: `ApiResponse` dan `App\Services` hanya boleh dipakai `App\Http\Controllers`.
- Model baru wajib punya factory dan seeder; kode PHP wajib lolos `vendor/bin/pint`.
- Jangan membuat base folder baru tanpa persetujuan.

### 3.2 Wajib diverifikasi terhadap implementasi P0 aktual

Dokumen ini disusun dari `AGENTS.md` dan PRD P0 v2. Isi folder `app/`, `routes/`, dan `database/` tidak dapat dibuka saat analisis, sehingga implementasi P0 sebenarnya **belum terverifikasi**. **Kode P0 yang ada menjadi sumber kebenaran untuk konvensi; PRD ini menjadi sumber kebenaran untuk perilaku P1.**

| #   | Yang dicek di kode P0                                                                      | Dipakai untuk                                    |
| --- | ------------------------------------------------------------------------------------------ | ------------------------------------------------ |
| 1   | Lokasi dan penamaan Controller, Service, Request, Resource, Policy, Model, Enum P0 Catalog | Menempatkan file P1 sebagai sibling (Bagian 3.3) |
| 2   | Nama tabel, kolom, dan enum P0 (`products`, `status`, `deleted_at`, dll.)                  | FK dan konsistensi                               |
| 3   | Penamaan kode error P0 di `config/api.php`                                                 | Kode error P1 mengikuti gaya yang sama           |
| 4   | Cara P0 menerapkan `merchant.context`, `merchant.owner`, dan scoped binding                | Mengulang pola yang sama                         |
| 5   | Pola reorder P0 (payload, validasi, status) dan pola activate/deactivate                   | Reorder dan status P1 identik                    |
| 6   | Service soft delete product P0 dan Resource product detail / outlet catalog P0             | Titik perubahan additif (Bagian 3.4)             |
| 7   | Cara P0 membuat partial unique index dan CHECK constraint                                  | Migration P1                                     |
| 8   | Apakah keputusan D1–D12 PRD P0 v2 dijalankan seperti tertulis                              | Keselarasan aturan                               |
| 9   | `.ai/rules/index.md` dan rule file yang cocok dengan path yang akan diubah                 | Diwajibkan `CLAUDE.md`                           |
| 10  | Tooling dokumentasi API di `docs/`                                                         | DoD                                              |

### 3.3 Pemetaan lapisan

Letakkan file P1 **berdampingan dengan file P0 Catalog** (satu direktori/namespace yang sama), mengikuti hasil verifikasi #1. Tidak ada base folder baru.

| Lapisan    | Catatan                                                                                                         |
| ---------- | --------------------------------------------------------------------------------------------------------------- |
| Route      | `routes/api.php`; nama `api.v1.merchant.catalog.products.modifier-groups.*`                                     |
| Controller | `ProductModifierGroupController`, `ProductModifierController`; tipis                                            |
| Validasi   | FormRequest per aksi; aturan lintas-field dievaluasi terhadap **state hasil akhir** (data existing + perubahan) |
| Service    | Return `Result`; error bisnis lewat `ApiException`                                                              |
| Resource   | `ProductModifierGroupResource`, `ProductModifierResource`                                                       |
| Model      | `ProductModifierGroup`, `ProductModifier` + enum, factory, seeder                                               |
| Test       | Pest; lihat Bagian 12                                                                                           |

### 3.4 Dampak additif pada P0

P1 tidak mengubah **tabel** P0. P1 memang menambah tiga **perilaku** pada P0, dan ketiganya harus dikerjakan eksplisit:

| #   | Perubahan                                                                                                               | Alasan                                                                                       |
| --- | ----------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| 1   | `GET /products/{product}` menambah `modifier_groups` (beserta `modifiers`)                                              | Owner perlu melihat customization saat review product                                        |
| 2   | Item outlet catalog (`GET /outlets/{outlet}/products`) menambah `modifier_groups` yang **aktif** beserta modifier aktif | Outlet user melihat customization yang berlaku. `is_sellable` **tidak berubah** (Bagian 7.5) |
| 3   | Soft delete product (P0 7.2) juga men-soft-delete modifier group dan modifier product tersebut                          | Cascade P0 hanya mencakup variant, media, dan assignment                                     |

Perubahan bersifat additif: field baru pada respons, tanpa mengubah field atau perilaku yang sudah ada. Test regresi P0 harus tetap hijau.

---

## 4. Domain Model & Keputusan Desain

### 4.1 Hierarki

Modifier Group **milik Product** (bukan milik Merchant), dan Modifier **milik Modifier Group**. Customization dikonfigurasi per product dan berlaku untuk semua outlet yang menerima product tersebut.

### 4.2 Variant vs Modifier

Keduanya tetap terpisah, sama seperti P0.

|            | Variant (P0)                                             | Modifier (P1)                |
| ---------- | -------------------------------------------------------- | ---------------------------- |
| Sifat      | Pilihan/versi utama product                              | Kustomisasi atau add-on      |
| Harga      | Menentukan harga dasar                                   | Harga **tambahan** (`>= 0`)  |
| Contoh     | Ice Cream: Strawberry, Chocolate. Burger: Regular, Large | Saus, Extra Cheese, Less Ice |
| Wajib ada? | Variable product wajib punya ≥ 1 variant aktif           | Opsional                     |

Aturan praktis: jika pilihan itu **mengubah identitas atau harga dasar** product (rasa, ukuran), jadikan **variant**. Jika berupa tambahan atau preferensi, jadikan **modifier**.

Modifier dapat dipakai pada product `simple` maupun `variable`. Pada variable product, group berlaku untuk **semua** variant.

Contoh variable product yang benar:

```text
Burger  (variable)
├── Variants:        Regular Rp 25.000 · Large Rp 32.000
└── Modifier Group:  Extra (opsional, maks. 2)
                     ├── Cheese  +Rp 5.000
                     └── Egg     +Rp 4.000
```

### 4.3 Satu modifier dipilih paling banyak satu kali

Tidak ada kuantitas per modifier (tidak ada "Extra Cheese ×2"). Kuantitas item adalah urusan Step 4.

### 4.4 Status

| Entitas        | Status                | Default saat dibuat                                        |
| -------------- | --------------------- | ---------------------------------------------------------- |
| Modifier Group | `active` / `inactive` | **`inactive`** (group kosong tidak boleh aktif, lihat 7.5) |
| Modifier       | `active` / `inactive` | `active`                                                   |

`status` tidak diterima pada create/PATCH; hanya diubah lewat `activate` / `deactivate` (sama seperti P0). Status inactive tidak menghapus konfigurasi. Modifier atau group inactive tidak boleh ditawarkan sebagai pilihan aktif.

---

## 5. Hak Akses

| Aksi                                              | Owner |  Outlet Manager  |   Outlet Staff   |
| ------------------------------------------------- | :---: | :--------------: | :--------------: |
| CRUD, status, reorder modifier group dan modifier |   ✔   |        ✘         |        ✘         |
| Melihat customization lewat product detail        |   ✔   |        ✘         |        ✘         |
| Melihat customization aktif lewat outlet catalog  |   ✔   | ✔ outlet sendiri | ✔ outlet sendiri |

Aturan penolakan mengikuti P0: resource merchant lain → **404**; role tidak berhak → **403**; tanpa token → **401**.
Middleware: `auth:sanctum` + `merchant.context` + `merchant.owner` untuk seluruh endpoint P1 (Bagian 8). Akses baca outlet user hanya lewat endpoint outlet catalog P0 yang sudah ada.

---

## 6. Data Model

Ketentuan umum (sama dengan P0): PK UUID, `created_at`/`updated_at`/`deleted_at`, `merchant_id` **diisi dari parent** (bukan dari request), semua unique berupa **partial unique index** `WHERE deleted_at IS NULL`, FK `ON DELETE RESTRICT`.

### 6.1 `product_modifier_groups`

| Field          | Type         | Wajib | Keterangan                                 |
| -------------- | ------------ | :---: | ------------------------------------------ |
| id             | UUID         |   ✔   | PK                                         |
| merchant_id    | UUID         |   ✔   | FK `merchants`                             |
| product_id     | UUID         |   ✔   | FK `products`                              |
| name           | varchar(100) |   ✔   |                                            |
| description    | text         |       |                                            |
| selection_type | varchar(20)  |   ✔   | `single` / `multiple`                      |
| min_selection  | integer      |   ✔   | `>= 0`                                     |
| max_selection  | integer      |       | `NULL` = tanpa batas                       |
| is_required    | boolean      |   ✔   | Harus konsisten dengan `min_selection`     |
| status         | varchar(20)  |   ✔   | Default `inactive`                         |
| display_order  | integer      |   ✔   | Default: urutan terakhir + 1 dalam product |

Constraint:

```text
UNIQUE (product_id, lower(name))  WHERE deleted_at IS NULL
CHECK  (min_selection >= 0)
CHECK  (max_selection IS NULL OR max_selection >= GREATEST(min_selection, 1))
CHECK  (selection_type <> 'single' OR max_selection = 1)
CHECK  (is_required = (min_selection >= 1))
```

### 6.2 `product_modifiers`

| Field             | Type          | Wajib | Keterangan                               |
| ----------------- | ------------- | :---: | ---------------------------------------- |
| id                | UUID          |   ✔   | PK                                       |
| merchant_id       | UUID          |   ✔   |                                          |
| modifier_group_id | UUID          |   ✔   | FK `product_modifier_groups`             |
| name              | varchar(100)  |   ✔   |                                          |
| description       | text          |       |                                          |
| price             | numeric(12,2) |   ✔   | Harga tambahan, `>= 0`                   |
| status            | varchar(20)   |   ✔   | Default `active`                         |
| is_default        | boolean       |   ✔   | Default `false`                          |
| display_order     | integer       |   ✔   | Default: urutan terakhir + 1 dalam group |

Constraint:

```text
UNIQUE (modifier_group_id, lower(name))  WHERE deleted_at IS NULL
CHECK  (price >= 0)
```

### 6.3 Index

```text
product_modifier_groups (product_id, display_order)
product_modifier_groups (merchant_id, product_id, status)
product_modifiers       (modifier_group_id, display_order)
product_modifiers       (merchant_id, modifier_group_id, status)
```

Unique biasa (tanpa `WHERE deleted_at IS NULL`) **tidak boleh dipakai**: setelah soft delete, nama yang sama tidak bisa dibuat ulang.

---

## 7. Aturan Bisnis

### 7.1 Aturan selection (Modifier Group)

```text
min_selection >= 0
max_selection = NULL (tanpa batas) atau max_selection >= max(min_selection, 1)
selection_type = single   → max_selection = 1   (min_selection ∈ {0, 1})
is_required               ⇔ min_selection >= 1
```

Contoh kombinasi valid:

| Kasus                                | `selection_type` | `min` | `max` | `is_required` |
| ------------------------------------ | ---------------- | :---: | :---: | :-----------: |
| Pilih 1, wajib                       | single           |   1   |   1   |     true      |
| Pilih 1, opsional                    | single           |   0   |   1   |     false     |
| Beberapa, opsional, maks. 3          | multiple         |   0   |   3   |     false     |
| Beberapa, wajib, min. 1, tanpa batas | multiple         |   1   | null  |     true      |
| Beberapa, wajib, 2–3                 | multiple         |   2   |   3   |     true      |

Normalisasi input:

- `is_required=true` tanpa `min_selection` → `min_selection = 1`. `is_required=false` tanpa `min_selection` → `0`.
- Jika keduanya dikirim tetapi tidak konsisten → **422**.
- `selection_type=single` tanpa `max_selection` → `max_selection = 1`.
- PATCH dievaluasi terhadap **state hasil akhir**, bukan hanya field yang dikirim.

### 7.2 Modifier Group

- Tersedia untuk product `simple` maupun `variable`; product boleh `inactive`; product yang sudah dihapus → 404.
- Batas usulan: maksimal **20 group** per product; melebihi → **409** `catalog.modifier_group_limit_reached` _(D10)_.
- Nama unik per product (case-insensitive) di antara group yang belum terhapus.
- Group baru selalu `inactive`.
- **Activate** hanya jika invariant 7.5 terpenuhi; jika tidak → **409** `catalog.modifier_group_insufficient_modifiers`.
- **Mengubah aturan selection** pada group aktif (misalnya menaikkan `min_selection`) juga dicek terhadap invariant 7.5 (409 yang sama).
- Mengubah `selection_type` menjadi `single` ditolak (**422**) jika group memiliki lebih dari satu modifier default.
- **Delete:** soft delete group dan seluruh modifier-nya dalam satu transaksi. Diperbolehkan walau group aktif.

### 7.3 Modifier

- Parent group harus milik product pada URL dan milik merchant yang sama.
- `price >= 0`; harga `0` diperbolehkan. Tersimpan sebagai `numeric(12,2)`, tidak memakai float.
- Nama unik per group (case-insensitive).
- Batas usulan: maksimal **50 modifier** per group; melebihi → **409** `catalog.modifier_limit_reached` _(D10)_.
- Modifier baru `active`.
- Modifier tidak dapat dipindahkan ke group atau product lain.
- **Deactivate / delete** modifier aktif pada group aktif ditolak dengan **409** `catalog.modifier_required_by_active_group` jika membuat invariant 7.5 tidak terpenuhi. Pada group `inactive`, selalu diperbolehkan.

### 7.4 Default modifier

- `is_default` hanya boleh pada modifier `active`.
- Group `single`: maksimal **satu** default. Menetapkan default baru otomatis melepas default lama (satu transaksi).
- Group `multiple`: jumlah default tidak boleh melebihi `max_selection` (jika ada); melampaui → **422**.
- Jika modifier default di-deactivate atau dihapus, `is_default` di-reset ke `false` (tanpa auto-promote).
- `is_default` hanya metadata katalog di P1; pemakaiannya (preselection) diputuskan di Step 4 _(D7)_.

### 7.5 Invariant group aktif

Setiap group **aktif** harus dapat dipenuhi customer:

```text
active_modifier_count >= max(min_selection, 1)
```

Invariant dijaga pada: activate group, PATCH aturan selection group aktif, deactivate modifier, dan delete modifier. Mengaktifkan modifier tidak pernah melanggarnya.

`max_selection` **tidak** dibandingkan dengan jumlah modifier aktif: `max` hanya batas atas (group dengan `max=3` dan 2 modifier aktif tetap valid) _(D5)_.

Karena invariant dijaga di titik-titik di atas, **`is_sellable` P0 (Bagian 4.5) tidak perlu diubah**. Group aktif selalu dapat dipenuhi; group `inactive` tidak ditawarkan.

### 7.6 Soft delete & cascade

| Aksi                | Efek                                                                          |
| ------------------- | ----------------------------------------------------------------------------- |
| Delete modifier     | Soft delete modifier (dengan cek 7.3)                                         |
| Delete group        | Soft delete group + semua modifier-nya                                        |
| Delete product (P0) | Cascade P0 **ditambah** soft delete semua group dan modifier product tersebut |

Soft delete tidak menghapus data secara fisik sehingga referensi historis tetap aman untuk Step 4.

### 7.7 Ordering

Sama dengan P0 (Bagian 7.6): `display_order` adalah kunci urut (integer, tidak harus unik); reorder menerima `items` 1–200 entri, ID unik dan harus dalam scope (product untuk group, group untuk modifier), hanya item yang dikirim yang diubah, satu transaksi, respons `204`. Urutan default `display_order ASC`, lalu `created_at`, lalu `id`.

---

## 8. API

Base path: `/api/v1/merchant/catalog`. Semua endpoint: `auth:sanctum` + `merchant.context` + `merchant.owner`.
Registrasi route: definisikan route statis `.../order` **sebelum** route dengan parameter `{...}`; batasi parameter sebagai UUID; gunakan scoped binding (`{group}` harus milik `{product}`, `{modifier}` harus milik `{group}`).

### 8.1 Modifier Group

| Method | Path                                                     | Fungsi                    |
| ------ | -------------------------------------------------------- | ------------------------- |
| GET    | `/products/{product}/modifier-groups`                    | List (beserta modifier)   |
| POST   | `/products/{product}/modifier-groups`                    | Create                    |
| GET    | `/products/{product}/modifier-groups/{group}`            | Detail (beserta modifier) |
| PATCH  | `/products/{product}/modifier-groups/{group}`            | Update                    |
| DELETE | `/products/{product}/modifier-groups/{group}`            | Soft delete (cascade 7.6) |
| POST   | `/products/{product}/modifier-groups/{group}/activate`   | Activate                  |
| POST   | `/products/{product}/modifier-groups/{group}/deactivate` | Deactivate                |
| PUT    | `/products/{product}/modifier-groups/order`              | Reorder                   |

List: query `status`, `sort` (`display_order`, `name`, `created_at`), `order`. Tidak berpaginasi karena jumlah dibatasi (7.2); respons `{ "data": [...] }`.

Create:

```json
{
    "name": "Pilihan Saus",
    "description": "Pilih satu saus favorit",
    "selection_type": "single",
    "min_selection": 1,
    "max_selection": 1,
    "is_required": true,
    "display_order": 1
}
```

PATCH boleh: `name`, `description`, `selection_type`, `min_selection`, `max_selection`, `is_required`, `display_order`.

### 8.2 Modifier

| Method | Path                                                                          | Fungsi      |
| ------ | ----------------------------------------------------------------------------- | ----------- |
| GET    | `/products/{product}/modifier-groups/{group}/modifiers`                       | List        |
| POST   | `/products/{product}/modifier-groups/{group}/modifiers`                       | Create      |
| GET    | `/products/{product}/modifier-groups/{group}/modifiers/{modifier}`            | Detail      |
| PATCH  | `/products/{product}/modifier-groups/{group}/modifiers/{modifier}`            | Update      |
| DELETE | `/products/{product}/modifier-groups/{group}/modifiers/{modifier}`            | Soft delete |
| POST   | `/products/{product}/modifier-groups/{group}/modifiers/{modifier}/activate`   | Activate    |
| POST   | `/products/{product}/modifier-groups/{group}/modifiers/{modifier}/deactivate` | Deactivate  |
| PUT    | `/products/{product}/modifier-groups/{group}/modifiers/order`                 | Reorder     |

List: query `status`, `sort`, `order`; tidak berpaginasi.

Create:

```json
{
    "name": "Extra Cheese",
    "description": "Tambahan keju",
    "price": 5000,
    "is_default": false,
    "display_order": 1
}
```

PATCH boleh: `name`, `description`, `price`, `is_default`, `display_order`.

Body reorder (group maupun modifier):

```json
{
    "items": [
        { "id": "uuid-1", "display_order": 1 },
        { "id": "uuid-2", "display_order": 2 }
    ]
}
```

### 8.3 Perubahan respons P0 (additif, Bagian 3.4)

Field baru pada `GET /products/{product}` dan pada tiap item `GET /outlets/{outlet}/products`:

```text
modifier_groups : [
  id, name, description, selection_type, min_selection, max_selection,
  is_required, status, display_order,
  modifiers : [ id, name, description, price, is_default, status, display_order ]
]
```

- **Product detail:** semua group dan modifier yang belum terhapus (termasuk `inactive`), agar owner bisa mengelolanya.
- **Outlet catalog:** hanya group `active` beserta modifier `active` _(D9)_.
- Eager load terkontrol; tidak boleh ada N+1 pada outlet catalog berpaginasi.

### 8.4 Catatan untuk Merchant App

Alur API yang mendukung form group (UI berada di repo `jualantar-merchant`):

```text
1. POST  modifier-groups                     → group dibuat (inactive)
2. POST  modifier-groups/{group}/modifiers   → ulangi per modifier
3. POST  modifier-groups/{group}/activate    → group aktif
```

Label seperti "Wajib · Pilih 1" atau "Opsional · Maks. 3" diturunkan client dari `is_required`, `min_selection`, dan `max_selection`.

---

## 9. Konvensi Response & Error

Mengikuti `AGENTS.md` dan P0.

| Kasus                  | Bentuk                                |
| ---------------------- | ------------------------------------- |
| Data tunggal / koleksi | `{ "data": ... }`                     |
| Create                 | `201` (+ `Location` bila relevan)     |
| Delete, reorder        | `204`                                 |
| Error                  | `application/problem+json` (RFC 9457) |

Response sukses **tidak** memiliki field `message`; error validasi **bukan** `{message, errors}`.

| Status    | Dipakai untuk                         |
| --------- | ------------------------------------- |
| 401 / 403 | Tidak terautentikasi / tidak berhak   |
| 404       | Tidak ada, atau milik merchant lain   |
| 409       | Konflik state (`ConflictException`)   |
| 422       | Validasi input dan pelanggaran aturan |

**Kode error baru** — daftarkan di `config/api.php` dengan gaya penamaan yang sama dengan kode P0 (hasil verifikasi 3.2 #3):

| Code                                            | Status | Kondisi                                                                                         |
| ----------------------------------------------- | :----: | ----------------------------------------------------------------------------------------------- |
| `catalog.modifier_group_insufficient_modifiers` |  409   | Activate group, atau ubah aturan group aktif, sehingga modifier aktif kurang dari `max(min, 1)` |
| `catalog.modifier_required_by_active_group`     |  409   | Deactivate/hapus modifier yang membuat group aktif tidak terpenuhi                              |
| `catalog.modifier_group_limit_reached`          |  409   | Melebihi batas group per product                                                                |
| `catalog.modifier_limit_reached`                |  409   | Melebihi batas modifier per group                                                               |

Input tidak valid lainnya (selection rule, harga negatif, nama duplikat, default tidak valid) dikembalikan sebagai **422** validation problem.

---

## 10. Tenant Isolation & Security

- Semua query dibatasi merchant context; jangan mengandalkan UUID saja (`ModifierGroup::find($id)` tidak cukup).
- Rantai kepemilikan diverifikasi: product → merchant; group → product + merchant; modifier → group + product + merchant.
- `merchant_id` tidak pernah diterima dari request.
- Merchant A tidak dapat melihat, membuat, mengubah, menghapus, atau memindahkan group/modifier milik merchant B (→ 404). Contoh: `/products/PRODUCT_B/modifier-groups` harus 404 jika `PRODUCT_B` bukan milik merchant A.
- Group tidak dapat dipindahkan ke product lain; modifier tidak dapat dipindahkan ke group lain.

---

## 11. Persyaratan Non-Fungsional

**Transaksi database** wajib untuk: reorder group/modifier, pergantian default modifier, delete group (cascade modifier), delete product (cascade P0 + P1), serta activate group / deactivate-delete modifier / PATCH aturan group aktif. Untuk tiga operasi terakhir, kunci baris group (`lockForUpdate`) agar pengecekan invariant 7.5 aman dari race condition.

**Performa:** tidak ada N+1 pada product detail dan outlet catalog (`with` terkontrol untuk `modifierGroups.modifiers`, dibatasi status jika perlu). Index sesuai 6.3.

**Dokumentasi:** setiap endpoint P1 dan perubahan respons P0 didokumentasikan pada sistem dokumentasi API repo (hasil verifikasi 3.2 #10).

---

## 12. Testing

Framework **Pest 4**, `RefreshDatabase` pada `jualantar_test`. Test ditulis bersamaan dengan tiap tahap.

- **Factory** untuk `ProductModifierGroup` dan `ProductModifier` dengan state (`single`, `multiple`, `required`, `active`, `withModifiers`).
- **CRUD & status:** create, list, detail, update, delete, activate, deactivate, reorder — untuk group dan modifier.
- **Aturan selection (table-driven):** seluruh kombinasi valid dan invalid Bagian 7.1, termasuk `max = 0`, `single` dengan `max ≠ 1`, `is_required` tidak konsisten dengan `min`, normalisasi input, dan PATCH terhadap state hasil akhir.
- **Invariant 7.5:** activate tanpa modifier cukup ditolak; deactivate/hapus modifier terakhir pada group aktif ditolak; hal yang sama pada group `inactive` diperbolehkan; PATCH `min_selection` group aktif dicek.
- **Default modifier:** single maksimal satu dan auto-swap; multiple ≤ `max_selection`; reset saat deactivate/delete; default pada modifier inactive ditolak.
- **Unik & soft delete:** nama duplikat ditolak; nama yang sama dapat dibuat ulang setelah delete (partial unique).
- **Batas:** group per product dan modifier per group.
- **Cascade:** delete group menghapus modifier-nya; delete product menghapus group dan modifier.
- **Isolasi tenant:** Merchant A → product/group/modifier Merchant B menghasilkan 404; group tidak dapat diakses lewat product yang salah; role non-owner → 403.
- **Perubahan additif P0:** product detail memuat semua group; outlet catalog hanya memuat group/modifier aktif; `is_sellable` tidak berubah.
- **Arch test** tetap lulus. **Regression:** seluruh test Step 1–2 dan P0 tetap hijau. Jalankan `vendor/bin/pint`.

---

## 13. Rencana Pengerjaan & Definition of Done

### 13.1 Urutan

0. Verifikasi Bagian 3.2; baca `.ai/rules`; konfirmasi Bagian 15.
1. Migration, model, enum, factory, seeder untuk 2 tabel.
2. Modifier Group (CRUD, aturan selection, status, ordering).
3. Modifier (CRUD, harga, status, default, ordering) + invariant 7.5.
4. Perubahan additif P0 (product detail, outlet catalog, cascade delete product).
5. Authorization dan isolasi tenant.
6. Dokumentasi API.

Test dikerjakan di setiap tahap.

### 13.2 Definition of Done

**Database**

- [ ] Dua tabel, FK, CHECK, dan partial unique index tersedia; soft delete berjalan.

**Modifier Group**

- [ ] CRUD, activate/deactivate, reorder berjalan.
- [ ] Seluruh aturan selection 7.1 ditegakkan (API dan CHECK database).
- [ ] Group baru `inactive`; activate mengikuti invariant 7.5.

**Modifier**

- [ ] CRUD, harga `>= 0`, activate/deactivate, reorder berjalan.
- [ ] Deactivate/delete yang melanggar invariant 7.5 ditolak (409).
- [ ] Aturan default 7.4 berjalan.

**Batas & cascade**

- [ ] Batas group/modifier berjalan.
- [ ] Delete group dan delete product melakukan cascade.

**Perubahan additif P0**

- [ ] Product detail dan outlet catalog memuat `modifier_groups` sesuai 8.3.
- [ ] `is_sellable` dan seluruh perilaku P0 lain tidak berubah.

**Authorization & isolasi**

- [ ] Hanya owner yang dapat mengubah; cross-merchant → 404; non-owner → 403.

**Kualitas**

- [ ] Response mengikuti `ApiResponse` dan RFC 9457; kode error terdaftar di `config/api.php`.
- [ ] Pest (feature, unit, arch, regression) lulus; Pint bersih.
- [ ] Endpoint, aturan bisnis, dan perubahan respons P0 terdokumentasi.

---

## 14. Kompatibilitas ke Depan (batasan saja)

- **Step 4 Customer Ordering:** order item akan menyimpan snapshot pilihan (minimal `modifier_id`, nama, dan harga saat dipesan). P1 hanya menjamin: harga ada di `product_modifiers.price`, modifier tidak dihapus fisik, dan tiap modifier dipilih paling banyak sekali. Validasi pilihan customer terhadap aturan group, aturan penggunaan `is_default`, dan endpoint customer dibahas di PRD Step 4.
- **Perluasan ke depan** (harga/availability modifier per outlet, modifier per variant, pustaka group): ditambahkan sebagai tabel atau kolom baru, tanpa mengubah konsep Product → Modifier Group → Modifier.

---

## 15. Keputusan yang Perlu Dikonfirmasi

Keputusan berikut saya ambil untuk menutup celah pada draft awal. Ubah jika tidak sesuai.

| #   | Keputusan                                                                 | Default di PRD ini                                                                                                                                                                                                  |
| --- | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | Lokasi dan penamaan file P1                                               | Sibling dari file P0 Catalog aktual (bukan `app/Modules/...` dari draft awal)                                                                                                                                       |
| D2  | `is_required`                                                             | Dipertahankan; harus konsisten dengan `min_selection` (`is_required ⇔ min ≥ 1`), dijaga CHECK                                                                                                                       |
| D3  | Status awal                                                               | Group `inactive`; Modifier `active`                                                                                                                                                                                 |
| D4  | Menonaktifkan/menghapus modifier yang membuat group aktif tidak terpenuhi | Ditolak (409), bukan auto-deactivate group                                                                                                                                                                          |
| D5  | `max_selection ≤ jumlah modifier aktif`                                   | Tidak diberlakukan                                                                                                                                                                                                  |
| D6  | Kuantitas per modifier                                                    | Tidak ada; maksimal satu kali per item                                                                                                                                                                              |
| D7  | `is_default`                                                              | Single ≤ 1 (auto-swap); multiple ≤ `max_selection`; reset saat deactivate/delete; pemakaian di Step 4                                                                                                               |
| D8  | Delete group                                                              | Cascade soft delete modifier; delete product ikut cascade P1                                                                                                                                                        |
| D9  | Respons P0                                                                | Product detail: semua group; outlet catalog: hanya yang aktif; `is_sellable` tidak berubah                                                                                                                          |
| D10 | Batas                                                                     | Maks. 20 group per product, 50 modifier per group                                                                                                                                                                   |
| D11 | Unik nama                                                                 | Per product (group) dan per group (modifier), case-insensitive, partial unique                                                                                                                                      |
| D12 | Keterbatasan yang diterima                                                | Tanpa availability/harga modifier per outlet, tanpa modifier khusus variant, tanpa pustaka group. Contoh: outlet kehabisan "Extra Cheese" hanya bisa diatasi owner dengan menonaktifkan modifier untuk semua outlet |
| D13 | Nested creation dan UI                                                    | Dikeluarkan dari P1                                                                                                                                                                                                 |

---

## Lampiran A — Ringkasan Perubahan dari Draft Awal

| Area           | Perubahan                                                                                                                      | Alasan                                                                                                                                     |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Scope          | Bagian 27–30 (Mobile UI), Step 7 dan blok UI pada DoD dikeluarkan; diganti Bagian 8.4 (alur API)                               | `jualantar-api` adalah pure JSON API tanpa frontend; UI ada di repo Merchant App                                                           |
| Scope          | Bagian 17 dan 33 (nested creation) dihapus                                                                                     | Bukan requirement, tidak ada di P0, dan referensi "LRS" tidak dikenal di repo                                                              |
| Scope          | Bagian 40 (extension outlet-specific dan snapshot order) dipadatkan menjadi Bagian 14                                          | Milik Step 4 dan fase mendatang                                                                                                            |
| Scope          | Bagian 25 (customer effective configuration) dilebur ke aturan aktif/inactive                                                  | Endpoint customer bukan bagian P1                                                                                                          |
| Konsistensi P0 | Contoh Bagian 18 menaruh "Size" (Regular/Large) sebagai modifier pada variable product                                         | Bertentangan dengan aturan Variant ≠ Modifier; contoh diganti dan ditambah aturan praktis (4.2)                                            |
| Konsistensi P0 | Ditambahkan Bagian 3.4: dampak additif pada product detail, outlet catalog, cascade delete product                             | Draft awal menyatakan "tidak mengubah P0" tetapi Bagian 27/34 dan cascade delete P0 membutuhkannya                                         |
| Konvensi       | Response `{data, message}` dan error `{message, errors}` diganti sesuai `AGENTS.md`; kode error baru harus di `config/api.php` | Repo memakai `ApiResponse` dan RFC 9457                                                                                                    |
| Konvensi       | Struktur `app/Modules/Merchant/...` diganti "sibling file P0"; checklist verifikasi kode P0 ditambahkan                        | Bertentangan dengan `AGENTS.md`; tidak terverifikasi                                                                                       |
| Data           | `UNIQUE(product_id, name)` dan `UNIQUE(group_id, name)` diganti partial unique case-insensitive                                | Unique biasa + soft delete memblokir pembuatan ulang nama                                                                                  |
| Data           | Ditambahkan CHECK untuk aturan selection dan harga                                                                             | Menegakkan invariant di level database                                                                                                     |
| Aturan         | `is_required` didefinisikan setara dengan `min ≥ 1`                                                                            | Draft awal mengizinkan `is_required=false` dengan `min > 0` (kontradiktif)                                                                 |
| Aturan         | Ditambahkan `max_selection ≥ 1`                                                                                                | Draft awal mengizinkan `max = 0`                                                                                                           |
| Aturan         | Aturan aktivasi dijadikan invariant 7.5 dan dijaga juga pada deactivate/delete modifier serta PATCH aturan                     | Draft awal hanya mengecek saat activate group; `max ≤ active count` bersifat "dapat diberlakukan" (ambigu) → diputuskan tidak diberlakukan |
| Aturan         | Aturan default modifier dibuat pasti (single, multiple, reset)                                                                 | Draft awal: "recommended", "jika UX membutuhkan"                                                                                           |
| Aturan         | Ditambahkan: status awal, `status` tidak writable, normalisasi input, batas jumlah, cascade delete group                       | Sebelumnya tidak terdefinisi                                                                                                               |
| Aturan         | Ditegaskan: tiada kuantitas per modifier; group berlaku untuk semua variant                                                    | Mencegah ambiguitas di Step 4                                                                                                              |
| Akses          | Ditambahkan matriks peran; klaim "outlet-level authorization tidak diperlukan" diperjelas                                      | Outlet user membaca customization lewat outlet catalog                                                                                     |
| Testing        | Test berbasis Pest, table-driven untuk aturan, per tahap, plus arch dan regression                                             | Selaras dengan `AGENTS.md`/`CLAUDE.md`                                                                                                     |
| Struktur       | Bagian 36 digabung ke 8; 22 dan 32 masuk ke aturan bisnis; 35 dan 39 digabung ke 12–13; 41 dan 42 dihapus                      | Duplikasi (1.818 → sekitar 600 baris)                                                                                                      |
| Format         | Tabel dan escape hasil konversi (`\>=`, tabel rusak) dirapikan                                                                 | Keterbacaan                                                                                                                                |
