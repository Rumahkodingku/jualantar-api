# PRD — P0 Core Catalog

## JualAntar Merchant Catalog

|               |                                                                    |
| ------------- | ------------------------------------------------------------------ |
| **Status**    | Draft — menunggu konfirmasi keputusan di Bagian 15                 |
| **Fase**      | Step 3 — Merchant Catalog/Product · **P0 Core Catalog**            |
| **Area**      | Merchant Application / Catalog                                     |
| **Backend**   | `jualantar-api` — <https://github.com/Rumahkodingku/jualantar-api> |
| **Prasyarat** | Step 1 Merchant Approval ✅ · Step 2 Merchant Operations ✅        |

---

## 1. Tujuan & Posisi dalam Roadmap

```text
1. Merchant Approval        ✅
2. Merchant Operations      ✅
3. Merchant Catalog/Product ← dokumen ini
     ├── P0 Core Catalog            (PRD ini)
     └── P1 Product Customization   (PRD terpisah)
4. Customer Ordering
5. Merchant Order Processing
6. Driver
7. Dispatch
8. End-to-end
```

P0 menyediakan fondasi katalog **sisi merchant**: merchant owner mengelola satu master catalog, lalu men-assign product ke outlet. Setiap outlet punya status dan availability sendiri untuk product tersebut.

Kemampuan yang dibangun di P0:

1. **Category** — CRUD, status, urutan.
2. **Product** — simple dan variable, CRUD, status, urutan.
3. **Variant** — CRUD, status, urutan (hanya untuk variable product).
4. **Media** — gambar product.
5. **Outlet assignment** — assign/replace/remove, status assignment, availability manual.
6. **Outlet catalog** — tampilan katalog efektif per outlet untuk kebutuhan manajemen merchant.

**Batas P0:** semua endpoint P0 adalah endpoint merchant (owner/outlet user). P0 **tidak** menyediakan endpoint untuk customer. P0 dapat dikembangkan dan diuji sendiri (standalone) tanpa bergantung pada modul Step 4–8.

---

## 2. Scope

### 2.1 Masuk P0

Enam kemampuan pada Bagian 1.

### 2.2 Di luar P0

| Item                                                                                            | Ditangani di                                                |
| ----------------------------------------------------------------------------------------------- | ----------------------------------------------------------- |
| Modifier group, modifier/add-on, aturan pilihan (required, min/max), harga tambahan             | **P1**                                                      |
| Endpoint katalog untuk customer, cart, order, checkout, payment, snapshot harga pada order item | **Step 4** dan seterusnya                                   |
| Availability per-variant per-outlet                                                             | Belum dijadwalkan                                           |
| Harga berbeda per outlet (price override / multi-price-list)                                    | Belum dijadwalkan                                           |
| Gambar kategori                                                                                 | Ditunda (butuh alur upload tersendiri)                      |
| Stok/inventory, promosi, bundle/combo, jadwal product, resep/BOM, cost, import/export           | Tidak ada di roadmap saat ini                               |
| Modul audit baru                                                                                | Di luar P0 (cukup `created_at`, `updated_at`, `deleted_at`) |

> `availability_status` di P0 adalah **toggle manual** oleh user, bukan hasil hitung stok.

---

## 3. Konteks Repository & Prasyarat

### 3.1 Konvensi repo (terverifikasi dari `AGENTS.md`)

- Laravel 13, PHP 8.3, Sanctum, PostgreSQL 16, Pest 4 (Feature test memakai `RefreshDatabase` pada database `jualantar_test`).
- Route berada di `routes/api.php`, dalam group `prefix('v1')->name('api.v1.')`.
- Response dibangun lewat `App\Support\Http\ApiResponse`, bukan `response()->json()`.
- Error berformat RFC 9457 (`application/problem+json`) lewat `ProblemDetailsFactory`; kode error baru wajib didaftarkan di `config/api.php`.
- Business logic ada di `app/Services` dan mengembalikan `App\Support\Result\Result`; error bisnis memakai subclass `ApiException` (`BadRequest`, `NotFound`, `Conflict`, `UnprocessableEntity`).
- Serialisasi memakai Eloquent API Resource (`App\Http\Resources\*`).
- Model memakai atribut `#[Fillable]`/`#[Hidden]` dan method `casts()`; key enum memakai TitleCase.
- `tests/Arch/ArchTest.php` membatasi: `ApiResponse` dan `App\Services` hanya boleh dipakai oleh `App\Http\Controllers`.
- Model baru wajib disertai factory dan seeder. Kode PHP yang diubah wajib lolos `vendor/bin/pint`.

### 3.2 Belum terverifikasi — wajib dicek sebelum coding

| #   | Hal yang dicek                                                                                                                                               | Kenapa                                                                                           |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------ |
| 1   | Struktur folder. Draft awal menyebut `app/Modules/Merchant/...`, sedangkan `AGENTS.md` menyebut `app/Http/Controllers`, `app/Services`, `app/Http/Resources` | Salah satunya tidak sesuai. `AGENTS.md` juga melarang membuat base folder baru tanpa persetujuan |
| 2   | Isi `.ai/rules/index.md` dan rule file yang cocok dengan path yang akan diubah                                                                               | Diwajibkan `CLAUDE.md` sebelum membuat/mengubah file                                             |
| 3   | Nama middleware `merchant.context`, `merchant.owner`, nama role, dan relasi user ↔ outlet dari Step 2                                                        | Dasar authorization outlet-level                                                                 |
| 4   | Abstraksi storage/upload file, konvensi UUID, penamaan migration, tooling dokumentasi API                                                                    | Dipakai Media dan DoD                                                                            |
| 5   | Tidak ada tabel existing bernama `products`, `product_variants`, `product_media`, `outlet_products`                                                          | Hindari tabrakan nama (kategori memakai prefix `catalog_` kemungkinan karena alasan ini)         |

### 3.3 Pemetaan lapisan implementasi

Mengikuti `AGENTS.md`. Jika hasil verifikasi (3.2 #1) menunjukkan repo memakai struktur modul, ikuti folder tersebut; batas domain tetap sama.

| Lapisan                      | Lokasi                               | Catatan                                                                                  |
| ---------------------------- | ------------------------------------ | ---------------------------------------------------------------------------------------- |
| Route                        | `routes/api.php`                     | Nama route `api.v1.merchant.catalog.*`                                                   |
| Controller                   | `App\Http\Controllers`               | Tipis; hanya memanggil Service dan `ApiResponse`                                         |
| Validasi input               | FormRequest                          | Satu per aksi bila aturannya berbeda                                                     |
| Business logic               | `App\Services`                       | Return `Result`; error bisnis lewat `ApiException`                                       |
| Serialisasi                  | `App\Http\Resources`                 | Tidak ada array inline di controller                                                     |
| Model, Enum, Factory, Seeder | Sesuai konvensi repo                 | 5 model: `CatalogCategory`, `Product`, `ProductVariant`, `ProductMedia`, `OutletProduct` |
| Authorization                | Policy / middleware existing         | Lihat Bagian 5                                                                           |
| Test                         | Pest (`tests/Feature`, `tests/Unit`) | Lihat Bagian 12                                                                          |

### 3.4 Ketergantungan domain

P0 memakai tabel `merchants` dan `merchant_outlets` dari Step 1–2 dan **tidak membuat ulang** keduanya.

```text
Merchant
   ├── Categories
   ├── Products
   │     ├── Variants
   │     └── Media
   └── Outlets
         └── Outlet Product Assignment  (Outlet ↔ Product)
```

---

## 4. Domain Model & Keputusan Desain

### 4.1 Master catalog, bukan salinan per outlet

Product dibuat **satu kali** pada level merchant, lalu di-assign ke outlet. Outlet tidak membuat salinan product.

```text
Master Catalog (Kopi Kita)         Outlet A            Outlet B
├── Kopi Susu                      ├── Kopi Susu       ├── Kopi Susu
├── Americano                      ├── Americano       ├── Matcha Latte
├── Matcha Latte                   └── Es Teh          └── Es Teh
└── Es Teh
```

### 4.2 Simple vs Variable

|                  | Simple            | Variable                                    |
| ---------------- | ----------------- | ------------------------------------------- |
| Contoh           | Es Teh — Rp 5.000 | Ice Cream: Strawberry, Chocolate, Blueberry |
| `product_type`   | `simple`          | `variable`                                  |
| `products.price` | wajib terisi      | wajib `null`                                |
| Variant          | tidak boleh ada   | minimal 1 (agar bisa aktif)                 |
| Harga ada di     | `products.price`  | `product_variants.price`                    |

Simple product **tidak** dibuat dengan variant palsu bernama `Default`.

### 4.3 Variant ≠ Modifier

Variant adalah pilihan/versi dari product (rasa, ukuran). Modifier adalah kustomisasi/add-on dan masuk **P1**. Keduanya dipisahkan sejak P0 agar P1 tidak perlu merombak schema P0.

### 4.4 Status berlapis

| Lapisan        | Field                                 | Arti                                          | Diubah oleh                                          |
| -------------- | ------------------------------------- | --------------------------------------------- | ---------------------------------------------------- |
| Kategori       | `catalog_categories.status`           | Kategori tampil/tidak                         | Owner                                                |
| Master product | `products.status`                     | Product siap dijual secara merchant-wide      | Owner                                                |
| Variant        | `product_variants.status`             | Variant bisa dipilih                          | Owner                                                |
| Assignment     | `outlet_products.status`              | Product terdaftar aktif di outlet ini         | Owner, Outlet Manager (outlet sendiri)               |
| Availability   | `outlet_products.availability_status` | Sedang tersedia/habis sementara di outlet ini | Owner, Outlet Manager, Outlet Staff (outlet sendiri) |

Kombinasi assignment yang valid: `active + available`, `active + unavailable`, `inactive` (availability tidak relevan).

Perubahan status pada satu lapisan **tidak** menulis ulang status lapisan lain (tanpa cascade update status).

### 4.5 Definisi "efektif" (sellable)

Sebuah product dianggap **sellable** di sebuah outlet jika **semua** kondisi berikut terpenuhi:

```text
is_sellable =
      products.status                     = active
  AND catalog_categories.status           = active
  AND outlet_products.status              = active
  AND outlet_products.availability_status = available
  AND ( product_type = simple
        OR product punya >= 1 variant dengan status active )
```

`is_sellable` dihitung saat query dan dikembalikan sebagai field read-only di outlet catalog (Bagian 8.6). Aturan yang sama akan dipakai ulang oleh endpoint customer pada Step 4; endpoint customer itu sendiri **bukan** bagian P0.

---

## 5. Peran & Hak Akses

Tiga role: `merchant` (owner), `outlet_manager`, `outlet_staff` (nama role diverifikasi, lihat 3.2 #3).

| Aksi                                              |     Owner      |  Outlet Manager  |      Outlet Staff       |
| ------------------------------------------------- | :------------: | :--------------: | :---------------------: |
| CRUD category, reorder category                   |       ✔        |        ✘         |            ✘            |
| CRUD product, activate/deactivate, reorder master |       ✔        |        ✘         |            ✘            |
| CRUD variant                                      |       ✔        |        ✘         |            ✘            |
| Kelola media                                      |       ✔        |        ✘         |            ✘            |
| Lihat daftar outlet suatu product                 |       ✔        |        ✘         |            ✘            |
| Assign / replace / remove assignment              |       ✔        |        ✘         |            ✘            |
| Lihat outlet catalog                              | ✔ semua outlet | ✔ outlet sendiri |    ✔ outlet sendiri     |
| Activate/deactivate assignment                    |       ✔        | ✔ outlet sendiri |            ✘            |
| Update availability                               |       ✔        | ✔ outlet sendiri | ✔ outlet sendiri _(D2)_ |
| Reorder outlet catalog                            |       ✔        | ✔ outlet sendiri |            ✘            |

Aturan penolakan:

- Resource milik merchant lain → **404** (tidak membocorkan keberadaan resource).
- Outlet lain dalam merchant yang sama, diakses outlet user → **403**.
- Role tidak punya hak atas aksi → **403**.
- Tanpa/invalid token → **401**.

Middleware: `auth:sanctum` + `merchant.context` untuk semua endpoint; `merchant.owner` untuk aksi owner-only; policy berbasis assignment user ↔ outlet untuk aksi outlet-level.

---

## 6. Data Model

Ketentuan umum semua tabel:

- Primary key UUID (mengikuti konvensi repo). Timestamps `created_at`, `updated_at`. Soft delete `deleted_at`.
- `merchant_id` pada tabel anak **diisi dari parent**, tidak pernah dari request.
- Semua unique constraint berupa **partial unique index** `WHERE deleted_at IS NULL`, karena soft delete akan memblokir pembuatan ulang jika memakai unique biasa.
- Foreign key memakai `ON DELETE RESTRICT`.

### 6.1 `catalog_categories`

| Field         | Type         | Wajib | Keterangan                              |
| ------------- | ------------ | :---: | --------------------------------------- |
| id            | UUID         |   ✔   | PK                                      |
| merchant_id   | UUID         |   ✔   | FK `merchants`                          |
| name          | varchar(100) |   ✔   |                                         |
| description   | text         |       |                                         |
| status        | varchar(20)  |   ✔   | `active` / `inactive`, default `active` |
| display_order | integer      |   ✔   | Default: urutan terakhir + 1            |

Constraint: unique `(merchant_id, lower(name))` _(D6)_.

### 6.2 `products`

| Field         | Type          |    Wajib    | Keterangan                                                   |
| ------------- | ------------- | :---------: | ------------------------------------------------------------ |
| id            | UUID          |      ✔      | PK                                                           |
| merchant_id   | UUID          |      ✔      |                                                              |
| category_id   | UUID          |      ✔      | FK `catalog_categories`                                      |
| name          | varchar(150)  |      ✔      |                                                              |
| description   | text          |             |                                                              |
| product_type  | varchar(20)   |      ✔      | `simple` / `variable`, **tidak dapat diubah** setelah dibuat |
| price         | numeric(12,2) | kondisional | Hanya untuk `simple`                                         |
| status        | varchar(20)   |      ✔      | `active` / `inactive`, default `inactive`                    |
| display_order | integer       |      ✔      | Kunci urut master product                                    |

Constraint:

```text
CHECK ( (product_type = 'simple'   AND price IS NOT NULL)
     OR (product_type = 'variable' AND price IS NULL) )
CHECK ( price IS NULL OR price >= 0 )
```

### 6.3 `product_variants`

| Field         | Type          | Wajib | Keterangan       |
| ------------- | ------------- | :---: | ---------------- |
| id            | UUID          |   ✔   | PK               |
| merchant_id   | UUID          |   ✔   |                  |
| product_id    | UUID          |   ✔   | FK `products`    |
| sku           | varchar(50)   |       | Opsional         |
| name          | varchar(100)  |   ✔   |                  |
| price         | numeric(12,2) |   ✔   | `>= 0`           |
| status        | varchar(20)   |   ✔   | Default `active` |
| is_default    | boolean       |   ✔   | Default `false`  |
| display_order | integer       |   ✔   |                  |

Constraint:

```text
UNIQUE (merchant_id, lower(sku))  WHERE sku IS NOT NULL
UNIQUE (product_id)               WHERE is_default = true
CHECK  (price >= 0)
```

### 6.4 `product_media`

| Field         | Type         | Wajib | Keterangan                                    |
| ------------- | ------------ | :---: | --------------------------------------------- |
| id            | UUID         |   ✔   | PK                                            |
| merchant_id   | UUID         |   ✔   |                                               |
| product_id    | UUID         |   ✔   | FK `products`                                 |
| storage_key   | text         |   ✔   | **Dibuat server**, tidak diterima dari client |
| mime_type     | varchar(100) |   ✔   |                                               |
| file_size     | bigint       |   ✔   | byte                                          |
| alt_text      | varchar(255) |       |                                               |
| is_primary    | boolean      |   ✔   | Default `false`                               |
| display_order | integer      |   ✔   |                                               |

Constraint: `UNIQUE (product_id) WHERE is_primary = true`.

### 6.5 `outlet_products`

| Field               | Type         | Wajib | Keterangan                                                       |
| ------------------- | ------------ | :---: | ---------------------------------------------------------------- |
| id                  | UUID         |   ✔   | PK                                                               |
| merchant_id         | UUID         |   ✔   |                                                                  |
| outlet_id           | UUID         |   ✔   | FK `merchant_outlets`                                            |
| product_id          | UUID         |   ✔   | FK `products`                                                    |
| status              | varchar(20)  |   ✔   | `active` / `inactive`, default `active`                          |
| availability_status | varchar(20)  |   ✔   | `available` / `unavailable`, default `available`                 |
| unavailable_reason  | varchar(255) |       | Hanya terisi jika `unavailable`                                  |
| display_order       | integer      |   ✔   | Kunci urut di outlet; default: urutan terakhir + 1 di outlet tsb |

Constraint:

```text
UNIQUE (outlet_id, product_id) WHERE deleted_at IS NULL
CHECK  (availability_status = 'unavailable' OR unavailable_reason IS NULL)
```

### 6.6 Index

```text
catalog_categories (merchant_id, status)
catalog_categories (merchant_id, display_order)
products           (merchant_id, status)
products           (merchant_id, category_id)
products           (merchant_id, product_type)
product_variants   (merchant_id, product_id, status)
product_media      (merchant_id, product_id)
outlet_products    (merchant_id, outlet_id, status)
```

`outlet_products (outlet_id, product_id)` sudah tercakup oleh unique index.

---

## 7. Aturan Bisnis

### 7.1 Category

- Delete = soft delete. Jika masih punya product yang belum terhapus → **409** `catalog.category_in_use`.
- Deactivate tetap diperbolehkan walau punya product; product tersebut menjadi tidak sellable (Bagian 4.5), status product tidak diubah.

### 7.2 Product

- `status` **tidak** diterima pada create/PATCH; hanya diubah lewat `activate` / `deactivate`. Product baru selalu `inactive`.
- **Simple:** `price` wajib, `>= 0`; tidak boleh punya variant.
- **Variable:** `price` wajib `null` (PATCH yang mengirim `price` ditolak); harga ada di variant.
- `product_type` tidak boleh diubah lewat PATCH (field ditolak).
- `category_id` boleh diubah, tetapi harus category milik merchant yang sama.
- **Activate:** untuk `variable`, wajib ada ≥ 1 variant `active`; jika tidak → **409** `catalog.variable_product_requires_active_variant`.
- **Delete:** soft delete product beserta variant, media, dan outlet assignment-nya dalam satu transaksi.

Alur:

```text
Simple:    Create (inactive) → Assign outlet → Activate → sellable jika assignment aktif & available
Variable:  Create (inactive) → Buat variant → Assign outlet → Activate → sellable
```

### 7.3 Variant

- Parent harus `variable`; jika `simple` → **422** `catalog.variant_not_allowed_for_simple_product`.
- `price >= 0`; `sku` unik per merchant (case-insensitive) di antara variant yang belum terhapus.
- `status` tidak diterima pada create/PATCH; gunakan `activate` / `deactivate`. Variant baru `active`.
- `is_default` maksimal satu per product. Menetapkan default baru otomatis melepas default lama (satu transaksi). Default harus variant `active`; jika default di-deactivate atau dihapus, `is_default` di-reset ke `false` tanpa auto-promote _(D12)_.
- Jika parent product sedang `active`, menonaktifkan atau menghapus **variant aktif terakhir** ditolak dengan **409** `catalog.last_active_variant`. Jika product `inactive`, diperbolehkan.

### 7.4 Media

- Hanya gambar. Batas usulan: `jpeg`, `png`, `webp`; maksimal 5 MB per file; maksimal 10 media per product (di atas batas → **409** `catalog.media_limit_reached`) _(D7)_.
- Tipe file diverifikasi dari isi file, bukan ekstensi. Nama file client tidak dipakai; `storage_key` dibuat server dan di-namespace per merchant, misalnya `merchants/{merchant_id}/products/{product_id}/{uuid}.{ext}`.
- Media pertama product otomatis menjadi primary.
- Upload dengan `is_primary=true` mengganti primary lama; `POST .../primary` menukar primary (satu transaksi).
- Menghapus media primary: media berikutnya menurut `display_order` dipromosikan menjadi primary (jika ada).
- Delete = soft delete pada database; file fisik tidak dihapus di P0 (pembersihan storage di luar scope).
- Jika penyimpanan DB gagal setelah file tersimpan, file dibersihkan.

### 7.5 Outlet assignment

- **Assign (`POST`)** — owner; `outlet_ids` tidak kosong, unik, semua milik merchant. Jika salah satu outlet sudah ter-assign → **409** `catalog.outlet_assignment_exists`, tidak ada yang tersimpan (all-or-nothing). Assignment baru: `status=active`, `availability=available`.
- **Replace (`PUT`)** — daftar `outlet_ids` adalah source of truth. Outlet yang tidak ada di daftar → assignment di-soft-delete. Outlet yang sudah ada → tidak diubah (status, availability, urutan dipertahankan). Outlet baru → dibuat. `outlet_ids` boleh kosong (lepas dari semua outlet). Satu transaksi.
- **Remove (`DELETE`)** — soft delete assignment; master product tidak tersentuh. Assign ulang setelah remove membuat record baru.
- **Activate/Deactivate** — mengubah `outlet_products.status` saja.
- **Availability** — `status` = `available` | `unavailable`; `reason` opsional (maks. 255) dan hanya boleh dikirim saat `unavailable`. Saat kembali `available`, `unavailable_reason` dikosongkan _(D8)_.
- Product `inactive` boleh di-assign (assign dilakukan sebelum activate). Outlet harus milik merchant dan belum terhapus; P0 tidak mensyaratkan status outlet lain _(D9)_.
- Availability satu outlet tidak memengaruhi outlet lain.

### 7.6 Ordering

- `display_order` adalah kunci urut (integer, tidak harus unik).
- Reorder: `items` berisi 1–200 entri, ID tidak boleh duplikat, semua ID harus dalam scope (merchant, atau outlet untuk outlet catalog). Hanya item yang dikirim yang diubah. Satu transaksi. Response `204`.
- Urutan default semua list: `display_order ASC`, lalu `created_at ASC`, lalu `id`. Outlet catalog: `catalog_categories.display_order` → `outlet_products.display_order` → `created_at` → `id`.
- `outlet_products.display_order` tidak mengubah `products.display_order`.

---

## 8. API

Base path: `/api/v1/merchant/catalog`
Semua endpoint: `auth:sanctum` + `merchant.context`. Kolom **Akses** merujuk ke Bagian 5.
Registrasi route: definisikan route statis `.../order` **sebelum** route dengan parameter `{...}`, dan batasi parameter sebagai UUID.

### 8.1 Category

| Method | Path                                | Fungsi      | Akses |
| ------ | ----------------------------------- | ----------- | ----- |
| GET    | `/categories`                       | List        | Owner |
| POST   | `/categories`                       | Create      | Owner |
| GET    | `/categories/{category}`            | Detail      | Owner |
| PATCH  | `/categories/{category}`            | Update      | Owner |
| DELETE | `/categories/{category}`            | Soft delete | Owner |
| POST   | `/categories/{category}/activate`   | Activate    | Owner |
| POST   | `/categories/{category}/deactivate` | Deactivate  | Owner |
| PUT    | `/categories/order`                 | Reorder     | Owner |

Create/PATCH body: `name` (wajib saat create), `description`, `display_order`.
Query list: `page`, `per_page`, `search`, `status`, `sort` (`name`, `display_order`, `created_at`), `order` (`asc`/`desc`).

### 8.2 Product

| Method | Path                             | Fungsi                               | Akses |
| ------ | -------------------------------- | ------------------------------------ | ----- |
| GET    | `/products`                      | List master product                  | Owner |
| POST   | `/products`                      | Create                               | Owner |
| GET    | `/products/{product}`            | Detail (+ category, variants, media) | Owner |
| PATCH  | `/products/{product}`            | Update                               | Owner |
| DELETE | `/products/{product}`            | Soft delete (cascade, 7.2)           | Owner |
| POST   | `/products/{product}/activate`   | Activate                             | Owner |
| POST   | `/products/{product}/deactivate` | Deactivate                           | Owner |
| PUT    | `/products/order`                | Reorder                              | Owner |

Query list: `page`, `per_page`, `search`, `category_id`, `status`, `product_type`, `sort` (`name`, `display_order`, `created_at`), `order`.

Create simple:

```json
{
    "name": "Es Teh",
    "category_id": "category-uuid",
    "product_type": "simple",
    "price": 5000,
    "description": "Es teh manis",
    "display_order": 1
}
```

Create variable (tanpa `price`):

```json
{
    "name": "Ice Cream",
    "category_id": "category-uuid",
    "product_type": "variable",
    "description": "Ice cream dengan berbagai pilihan rasa"
}
```

PATCH boleh: `name`, `description`, `category_id`, `display_order`, dan `price` (khusus simple).

### 8.3 Variant

Hanya untuk product `variable`.

| Method | Path                                                | Fungsi      | Akses |
| ------ | --------------------------------------------------- | ----------- | ----- |
| GET    | `/products/{product}/variants`                      | List        | Owner |
| POST   | `/products/{product}/variants`                      | Create      | Owner |
| GET    | `/products/{product}/variants/{variant}`            | Detail      | Owner |
| PATCH  | `/products/{product}/variants/{variant}`            | Update      | Owner |
| DELETE | `/products/{product}/variants/{variant}`            | Soft delete | Owner |
| POST   | `/products/{product}/variants/{variant}/activate`   | Activate    | Owner |
| POST   | `/products/{product}/variants/{variant}/deactivate` | Deactivate  | Owner |
| PUT    | `/products/{product}/variants/order`                | Reorder     | Owner |

```json
{
    "name": "Strawberry",
    "sku": "ICE-STRAWBERRY",
    "price": 10000,
    "display_order": 1,
    "is_default": false
}
```

### 8.4 Media

| Method | Path                                        | Fungsi                         | Akses |
| ------ | ------------------------------------------- | ------------------------------ | ----- |
| GET    | `/products/{product}/media`                 | List                           | Owner |
| POST   | `/products/{product}/media`                 | Upload (`multipart/form-data`) | Owner |
| GET    | `/products/{product}/media/{media}`         | Detail                         | Owner |
| DELETE | `/products/{product}/media/{media}`         | Soft delete                    | Owner |
| POST   | `/products/{product}/media/{media}/primary` | Jadikan primary                | Owner |
| PUT    | `/products/{product}/media/order`           | Reorder                        | Owner |

Field upload: `file` (wajib), `is_primary`, `display_order`, `alt_text`. Response memuat `url` yang diturunkan dari `storage_key` lewat abstraksi storage existing.

### 8.5 Outlet assignment

| Method | Path                                                | Fungsi                  | Akses                               |
| ------ | --------------------------------------------------- | ----------------------- | ----------------------------------- |
| GET    | `/products/{product}/outlets`                       | List assignment product | Owner                               |
| POST   | `/products/{product}/outlets`                       | Assign ke 1..n outlet   | Owner                               |
| PUT    | `/products/{product}/outlets`                       | Replace assignment      | Owner                               |
| DELETE | `/products/{product}/outlets/{outlet}`              | Remove assignment       | Owner                               |
| POST   | `/products/{product}/outlets/{outlet}/activate`     | Activate assignment     | Owner, Outlet Manager               |
| POST   | `/products/{product}/outlets/{outlet}/deactivate`   | Deactivate assignment   | Owner, Outlet Manager               |
| POST   | `/products/{product}/outlets/{outlet}/availability` | Update availability     | Owner, Outlet Manager, Outlet Staff |

Assign/Replace:

```json
{ "outlet_ids": ["outlet-uuid-1", "outlet-uuid-3"] }
```

Availability:

```json
{ "status": "unavailable", "reason": "Stok ayam habis" }
```

```json
{ "status": "available" }
```

Untuk Outlet Manager/Staff, `{outlet}` harus outlet yang ditugaskan kepadanya (403 jika bukan).

### 8.6 Outlet catalog

| Method | Path                               | Fungsi                 | Akses                               |
| ------ | ---------------------------------- | ---------------------- | ----------------------------------- |
| GET    | `/outlets/{outlet}/products`       | Katalog efektif outlet | Owner, Outlet Manager, Outlet Staff |
| PUT    | `/outlets/{outlet}/products/order` | Reorder di outlet      | Owner, Outlet Manager               |

Ini **bukan** master catalog. Endpoint mengembalikan product yang ter-assign ke outlet (assignment belum terhapus), termasuk yang `inactive`/`unavailable`, agar bisa dikelola.

Query: `page`, `per_page`, `search`, `category_id`, `status` (status assignment), `availability`, `sort` (`name`, `display_order`, `created_at`), `order`.

Field per item:

```text
product        : id, name, description, product_type, price (simple), status
category       : id, name, status
variants       : [id, name, sku, price, status, is_default]   (variable saja)
primary_media  : url, alt_text
assignment     : status, availability_status, unavailable_reason, display_order
is_sellable    : boolean (Bagian 4.5)
```

Reorder body:

```json
{ "items": [{ "product_id": "product-uuid-1", "display_order": 1 }] }
```

Semua `product_id` harus ter-assign ke outlet tersebut.

---

## 9. Konvensi Response & Error

Mengikuti `AGENTS.md`; tidak ada pola baru.

**Sukses**

| Kasus                  | Bentuk                                                                            |
| ---------------------- | --------------------------------------------------------------------------------- |
| Data tunggal / koleksi | `{ "data": ... }` (`ApiResponse::success`)                                        |
| List berpaginasi       | `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }` |
| Create                 | `201` (+ header `Location` bila relevan)                                          |
| Delete, reorder        | `204`                                                                             |

Response sukses **tidak** memiliki field `message`.

**Error** — semua `application/problem+json` (RFC 9457) via `ProblemDetailsFactory`, dengan trace id dari `X-Trace-Id`.

| Status | Dipakai untuk                                                                        |
| ------ | ------------------------------------------------------------------------------------ |
| 401    | Tidak terautentikasi                                                                 |
| 403    | Role/outlet tidak berhak                                                             |
| 404    | Resource tidak ada, atau milik merchant lain                                         |
| 409    | Konflik state (`ConflictException`)                                                  |
| 422    | Validasi input dan pelanggaran aturan (`UnprocessableEntityException` / FormRequest) |

**Kode error baru** — wajib didaftarkan di `config/api.php` sebelum dipakai (penamaan final mengikuti kode existing):

| Code                                               | Status | Kondisi                                                    |
| -------------------------------------------------- | :----: | ---------------------------------------------------------- |
| `catalog.category_in_use`                          |  409   | Hapus category yang masih punya product                    |
| `catalog.variable_product_requires_active_variant` |  409   | Activate variable product tanpa variant aktif              |
| `catalog.last_active_variant`                      |  409   | Deactivate/hapus variant aktif terakhir pada product aktif |
| `catalog.outlet_assignment_exists`                 |  409   | Assign ke outlet yang sudah ter-assign                     |
| `catalog.media_limit_reached`                      |  409   | Melebihi batas jumlah media                                |
| `catalog.variant_not_allowed_for_simple_product`   |  422   | Membuat variant pada product simple                        |

Kesalahan input lain (harga kosong/negatif, tipe file salah, SKU duplikat, availability tidak valid, ID di luar scope pada body) dikembalikan sebagai **422** validation problem, bukan kode khusus.

---

## 10. Tenant Isolation & Security

Wajib untuk semua endpoint.

- Setiap query dibatasi oleh merchant context saat ini. Jangan mengandalkan UUID saja (`Product::find($id)` tidak cukup).
- Rantai kepemilikan diverifikasi: category → merchant; product → merchant; variant → product + merchant; media → product + merchant; outlet → merchant; assignment → merchant + outlet + product.
- `merchant_id` tidak pernah diterima dari request.
- Merchant A tidak dapat melihat, mengubah, atau menghapus data merchant B, dan tidak dapat meng-assign product-nya ke outlet merchant B.
- Outlet user hanya dapat mengakses outlet yang ditugaskan; mengganti `outlet_id` di URL tidak boleh membuka outlet lain.
- Hasil: resource merchant lain → 404; outlet lain dalam merchant sama → 403.
- ID di body (`outlet_ids`, item reorder) yang di luar scope → 422.
- Upload media: lihat 7.4 (verifikasi isi file, `storage_key` dari server).

---

## 11. Persyaratan Non-Fungsional

**Transaksi database** wajib untuk: assign banyak outlet, replace assignment, semua reorder, set primary media, upload media (file + DB), pergantian `is_default`, cascade soft delete product, serta activate product / deactivate-delete variant (kunci baris product dengan `lockForUpdate` agar pengecekan "variant aktif terakhir" aman dari race condition).

**Soft delete** berlaku pada kelima tabel. Data terhapus tidak muncul pada query default. Cascade: hapus product → variant, media, assignment ikut terhapus.

**Performa:**

- Tidak ada N+1 pada list category, list product, outlet catalog, dan detail product. Relasi di-eager-load secara terkontrol.
- Semua list berpaginasi; `per_page` default dan maksimum mengikuti konvensi repo (usulan maksimum 100).
- `search` bersifat case-insensitive pada `name` dengan karakter wildcard di-escape.
- Field `sort` hanya dari whitelist.

**Audit:** cukup `created_at`, `updated_at`, `deleted_at`.

**Dokumentasi:** setiap endpoint P0 didokumentasikan pada sistem dokumentasi API yang sudah dipakai repo (folder `docs/` atau tooling existing): method, URL, autentikasi, authorization, parameter, request/response, validasi, error, dan contoh.

---

## 12. Testing

Framework: **Pest 4**. Feature test memakai `RefreshDatabase` pada `jualantar_test` (PostgreSQL). Test ditulis **bersamaan** dengan tiap tahap implementasi, bukan di akhir.

- **Factory** untuk kelima model, dengan state (`simple`, `variable`, `inactive`, `withVariants`, dll.).
- **Feature test:** autentikasi, merchant context, matriks akses Bagian 5, CRUD, filter, paginasi (`meta`), ordering, soft delete dan cascade, format error RFC 9457, dan kode error Bagian 9.
- **Isolasi tenant:** Merchant A → resource Merchant B menghasilkan 404. Outlet Manager A → Outlet B menghasilkan 403.
- **Aturan kritis:** `product_type` tidak dapat diubah; aktivasi variable product; variant aktif terakhir; `is_default` tunggal; primary media tunggal dan promosi; assign ulang setelah remove (partial unique); duplicate assignment; replace assignment; availability tidak memengaruhi outlet lain; SKU unik setelah variant lama dihapus.
- **`is_sellable`:** test berbasis tabel untuk seluruh kombinasi pada Bagian 4.5.
- **Unit test** hanya untuk logika murni (misalnya perhitungan `is_sellable`).
- **Arch test** (`tests/Arch/ArchTest.php`) tetap lulus: Service hanya dipakai Controller; `ApiResponse` hanya di Controller.
- **Regression:** seluruh test Step 1–2 tetap hijau. Jalankan `vendor/bin/pint`.

---

## 13. Rencana Pengerjaan & Definition of Done

### 13.1 Urutan

0. Verifikasi butir 3.2; baca `.ai/rules`; konfirmasi Bagian 15.
1. Migration, model, enum, factory, seeder untuk 5 tabel.
2. Category.
3. Product (simple, variable, status, urutan).
4. Variant.
5. Media.
6. Outlet assignment.
7. Outlet catalog + `is_sellable`.
8. Pengetatan authorization (owner / outlet manager / outlet staff).
9. Dokumentasi API.

Test dikerjakan di setiap tahap (Bagian 12).

### 13.2 Definition of Done

**Database**

- [ ] Kelima tabel, FK, CHECK, dan partial unique index tersedia.
- [ ] Soft delete dan index utama tersedia.

**Category**

- [ ] CRUD, activate/deactivate, reorder berjalan.
- [ ] Hapus category yang dipakai product ditolak (409).

**Product**

- [ ] Simple dan variable product dapat dibuat; aturan `price` berjalan.
- [ ] `product_type` tidak dapat diubah; `status` hanya lewat activate/deactivate.
- [ ] Activate variable product tanpa variant aktif ditolak (409).
- [ ] Soft delete product melakukan cascade.
- [ ] Reorder berjalan.

**Variant**

- [ ] Hanya untuk variable product; harga `>= 0`; SKU unik per merchant.
- [ ] `is_default` tunggal; variant aktif terakhir pada product aktif terlindungi.
- [ ] Activate/deactivate dan reorder berjalan.

**Media**

- [ ] Upload, delete, set primary, reorder berjalan.
- [ ] Hanya satu primary; validasi MIME/ukuran/jumlah berjalan.

**Outlet assignment**

- [ ] Assign 1..n outlet, replace, remove berjalan; duplicate ditolak (409).
- [ ] Assign ulang setelah remove berhasil.
- [ ] Activate/deactivate dan availability (dengan reason) berjalan.
- [ ] Availability satu outlet tidak memengaruhi outlet lain.

**Outlet catalog**

- [ ] Filter, sort, paginasi berjalan; `is_sellable` sesuai Bagian 4.5.
- [ ] Reorder outlet tidak mengubah `products.display_order`.

**Authorization & isolasi**

- [ ] Matriks Bagian 5 berjalan untuk ketiga role.
- [ ] Cross-merchant → 404; cross-outlet → 403.

**Kualitas**

- [ ] Response mengikuti `ApiResponse` dan RFC 9457; kode error terdaftar di `config/api.php`.
- [ ] Pest (feature, unit, arch, regression) lulus; Pint bersih.
- [ ] Endpoint dan aturan bisnis terdokumentasi.

---

## 14. Kompatibilitas ke Depan (batasan saja)

- **P1 Product Customization:** modifier akan menambah tabel baru yang mereferensikan `products.id`. P0 tidak boleh mengubah konsep Product, Product Variant, dan Outlet Product Assignment; perubahan pada tabel P0 dibatasi pada penambahan kolom nullable.
- **Step 4 Customer Ordering:** order item akan menyimpan snapshot nama dan harga. P0 hanya menjamin harga berada di `products.price` / `product_variants.price` dan data tidak dihapus fisik (soft delete). Desain snapshot dan endpoint customer dibahas di PRD Step 4.

---

## 15. Keputusan yang Perlu Dikonfirmasi

Keputusan berikut saya ambil untuk menutup celah pada draft awal. Ubah jika tidak sesuai.

| #   | Keputusan                                         | Default di PRD ini                                                                                                       |
| --- | ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| D1  | Struktur folder implementasi                      | Ikuti `AGENTS.md` (Controller/Service/Resource); referensi `app/Modules/...` dari draft awal dihapus sampai diverifikasi |
| D2  | Outlet Staff boleh mengubah availability          | Ya, hanya outlet sendiri                                                                                                 |
| D3  | Gambar kategori                                   | Dikeluarkan dari P0                                                                                                      |
| D4  | `product_type` dapat diubah setelah dibuat        | Tidak                                                                                                                    |
| D5  | Kode untuk akses lintas merchant vs lintas outlet | 404 vs 403                                                                                                               |
| D6  | Nama kategori unik per merchant                   | Ya (case-insensitive)                                                                                                    |
| D7  | Batas media                                       | jpeg/png/webp, 5 MB, maks. 10 per product                                                                                |
| D8  | `unavailable_reason`                              | Opsional; dikosongkan otomatis saat `available`                                                                          |
| D9  | Syarat status outlet saat assign                  | Tidak ada selain milik merchant dan belum terhapus                                                                       |
| D10 | Availability per-variant dan harga per-outlet     | Tidak didukung di P0                                                                                                     |
| D11 | Status awal                                       | Category `active`; Product `inactive`; Variant `active`; Assignment `active` + `available`                               |
| D12 | `is_default` variant                              | Dipertahankan; maks. satu; auto-swap; tanpa auto-promote                                                                 |

---

## Lampiran A — Ringkasan Perubahan dari Draft Awal

| Area      | Perubahan                                                                                                                                                         | Alasan                                                                               |
| --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Scope     | Bagian 36 (skema P1 modifier) dan 37 (snapshot order) dipadatkan menjadi Bagian 14                                                                                | Milik P1 dan Step 4                                                                  |
| Scope     | Inventory, stock, promotion dihapus dari tujuan sekunder                                                                                                          | Tidak ada di roadmap; Non-goal dipadatkan ke Bagian 2.2                              |
| Konvensi  | Response `{data, message}` dan error `{message, errors}` diganti sesuai `AGENTS.md`                                                                               | Repo memakai `ApiResponse` dan RFC 9457                                              |
| Konvensi  | Struktur `app/Modules/Merchant/...` dan route module ditandai belum terverifikasi                                                                                 | Bertentangan dengan `AGENTS.md`; folder tidak bisa dibuka saat analisis              |
| Konvensi  | Ditambahkan: kode error di `config/api.php`, pola Result/ApiException, arch test, Pest, factory/seeder, Pint                                                      | Diwajibkan `AGENTS.md`/`CLAUDE.md`                                                   |
| Data      | Unique constraint diganti partial unique index (`deleted_at IS NULL`)                                                                                             | Unique biasa + soft delete memblokir assign ulang dan SKU lama                       |
| Data      | Ditambahkan CHECK harga/tipe, index primary media dan default variant                                                                                             | Menegakkan aturan di level database                                                  |
| Data      | Kolom `image` kategori dihapus                                                                                                                                    | Tidak ada endpoint upload; menerima storage key dari client berisiko lintas merchant |
| Aturan    | Ditambahkan: `product_type` immutable, default status, `status` hanya via activate/deactivate                                                                     | Sebelumnya tidak terdefinisi                                                         |
| Aturan    | Ditambahkan definisi `is_sellable`                                                                                                                                | "Efektif" pada draft awal tidak terdefinisi                                          |
| Aturan    | Ditambahkan: proteksi variant aktif terakhir saat deactivate, aturan `is_default`, cascade soft delete, promosi primary media, semantik replace, semantik reorder | Draft awal ambigu atau tidak lengkap                                                 |
| Aturan    | `unavailable_reason` dibuat pasti (opsional, auto-clear)                                                                                                          | Draft awal: "recommended/required"                                                   |
| Akses     | Ditambahkan matriks peran per endpoint; 404 vs 403 diputuskan; 409 vs 422 dipetakan                                                                               | Draft awal: "dapat disesuaikan", "404 / 403"                                         |
| API       | Filter `outlet_id` dan `availability` dihapus dari `GET /products`                                                                                                | Duplikat dengan outlet catalog; master catalog tetap merchant-level                  |
| API       | Ditambahkan field item outlet catalog dan tata cara registrasi route `order`                                                                                      | Menghindari tabrakan route dan celah kontrak                                         |
| Media     | Ditambahkan batas file, keamanan `storage_key`, perilaku hapus                                                                                                    | Sebelumnya "configured rules" tanpa nilai                                            |
| Transaksi | Item "nested creation" dihapus                                                                                                                                    | Tidak ada endpoint nested di P0                                                      |
| Testing   | Test dikerjakan per tahap, bukan tahap terakhir; regression didefinisikan                                                                                         | Selaras dengan `AGENTS.md`/`CLAUDE.md`                                               |
| Struktur  | Bagian 24 digabung ke 8; 25 dan 35 digabung ke 13.2; 19 dan 26 masuk ke aturan bisnis; 38 dan 40 dihapus                                                          | Duplikasi (2.380 → sekitar 700 baris)                                                |
| Format    | Tabel dan escape hasil konversi (`\>=`, baris tabel rusak) dirapikan                                                                                              | Keterbacaan                                                                          |
