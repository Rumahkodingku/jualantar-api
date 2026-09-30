# PLAN Revisi API Catalogs — JualAntar API

> Repository: `Rumahkodingku/jualantar-api`  
> Scope: Revisi authorization dan API untuk **Catalogs** agar mendukung pemisahan tegas antara **Master Catalog** (owner/merchant) dan **Outlet Catalog** (outlet_manager/outlet_staff).  
> Status: Plan / implementation guide. Dokumen ini **bukan** instruksi untuk mengubah module lain di luar dependency yang benar-benar diperlukan oleh Catalog.

---

## 1. Tujuan

Revisi ini bertujuan memastikan API Catalog memiliki dua scope yang jelas:

### 1.1 Master Catalog

Dikelola oleh:

- `merchant` / owner

Owner tetap menjadi satu-satunya role yang dapat mengelola sumber data utama catalog:

- Category CRUD + status
- Product CRUD + status
- Product ordering
- Variant CRUD + status + ordering
- Product media CRUD + ordering + primary media
- Modifier group CRUD + status + ordering
- Modifier CRUD + status + ordering
- Product-to-outlet assignment / replacement / removal
- Product draft

### 1.2 Outlet Catalog

Dapat diakses oleh:

- `merchant` / owner
- `outlet_manager` pada outlet yang ditugaskan
- `outlet_staff` pada outlet yang ditugaskan

Outlet Catalog merepresentasikan **produk master yang memang ditugaskan ke outlet tersebut**, beserta state operasional pada outlet:

- assignment status
- availability status
- outlet display order

### 1.3 Batasan role employee

`outlet_manager` dan `outlet_staff`:

- BOLEH melihat product yang assigned ke outlet mereka.
- BOLEH melihat detail product melalui scope outlet.
- TIDAK BOLEH membuat product.
- TIDAK BOLEH mengedit product master.
- TIDAK BOLEH menghapus product.
- TIDAK BOLEH mengaktifkan/nonaktifkan product master.
- TIDAK BOLEH membuat/edit/delete/activate/deactivate category.
- TIDAK BOLEH mengubah variant, media, modifier group, atau modifier master.
- TIDAK BOLEH melakukan assignment/removal product ke outlet pada level master.

Perbedaan operasional:

| Kemampuan                         | merchant | outlet_manager | outlet_staff |
| --------------------------------- | -------: | -------------: | -----------: |
| Master catalog CRUD               |        ✅ |              ❌ |            ❌ |
| Master product status             |        ✅ |              ❌ |            ❌ |
| Category management               |        ✅ |              ❌ |            ❌ |
| Variant/media/modifier management |        ✅ |              ❌ |            ❌ |
| Product assignment ke outlet      |        ✅ |              ❌ |            ❌ |
| Lihat effective outlet catalog    |        ✅ |              ✅ |            ✅ |
| Lihat detail product outlet       |        ✅ |              ✅ |            ✅ |
| Update availability outlet        |        ✅ |              ✅ |            ✅ |
| Update assignment status outlet   |        ✅ |              ✅ |            ❌ |
| Reorder outlet catalog            |        ✅ |              ✅ |            ❌ |

---

## 2. Kondisi API Saat Ini yang Harus Dipertahankan

Struktur authorization yang sudah ada **jangan dibongkar dan jangan diganti dengan role check tersebar di controller**.

Komponen yang sudah tersedia:

- `app/Modules/Merchant/Application/Catalog/Services/CatalogAuthorization.php`
- `app/Modules/Merchant/Application/Catalog/Services/CatalogQueryService.php`
- `app/Modules/Merchant/Application/Operations/Services/MerchantOperationsAuthorization.php`
- `app/Modules/Merchant/Domain/Authorization/OutletRoleCapabilityResolver.php`

Authorization outlet saat ini sudah membedakan:

- owner
- employee yang assigned ke outlet
- employee pada merchant yang sama tetapi outlet berbeda
- foreign outlet

Kontrak error ini harus tetap dipertahankan:

- outlet foreign / di luar merchant scope → 404
- outlet merchant yang sama tetapi employee tidak ditugaskan → 403 `outlet_scope_forbidden`
- role tidak memiliki capability → 403 `outlet_capability_forbidden`

---

# 3. Phase 0 — Baseline & Inventory

## Task 0.1 — Freeze current behavior

Sebelum melakukan perubahan:

- [ ] Catat endpoint Catalog yang saat ini tersedia.
- [ ] Catat middleware yang digunakan setiap endpoint.
- [ ] Catat capability yang sudah digunakan.
- [ ] Catat resource/DTO response setiap endpoint.
- [ ] Catat test suite Catalog yang sudah tersedia.
- [ ] Catat route name yang digunakan frontend/API documentation.

## Task 0.2 — Audit seluruh endpoint Catalog

Audit minimal:

### Categories

- GET `/merchant/catalog/categories`
- POST `/merchant/catalog/categories`
- PUT `/merchant/catalog/categories/order`
- GET `/merchant/catalog/categories/{category}`
- PATCH `/merchant/catalog/categories/{category}`
- DELETE `/merchant/catalog/categories/{category}`
- POST `/merchant/catalog/categories/{category}/activate`
- POST `/merchant/catalog/categories/{category}/deactivate`

### Product master

- GET `/merchant/catalog/products`
- POST `/merchant/catalog/products`
- PUT `/merchant/catalog/products/order`
- GET `/merchant/catalog/products/{product}`
- PATCH `/merchant/catalog/products/{product}`
- DELETE `/merchant/catalog/products/{product}`
- POST `/merchant/catalog/products/{product}/activate`
- POST `/merchant/catalog/products/{product}/deactivate`

### Variants

- seluruh endpoint variant di bawah product

### Media

- seluruh endpoint media di bawah product

### Modifier groups

- seluruh endpoint modifier group

### Modifiers

- seluruh endpoint modifier

### Product outlet assignment

- GET `/merchant/catalog/products/{product}/outlets`
- POST `/merchant/catalog/products/{product}/outlets`
- PUT `/merchant/catalog/products/{product}/outlets`
- DELETE `/merchant/catalog/products/{product}/outlets/{outlet}`
- POST activate assignment
- POST deactivate assignment
- POST availability update

### Effective outlet catalog

- GET `/merchant/catalog/outlets/{outlet}/products`
- PUT `/merchant/catalog/outlets/{outlet}/products/order`

**Acceptance:** semua endpoint sudah terpetakan ke scope dan role yang jelas sebelum implementasi dimulai.

---

# 4. Phase 1 — Tetapkan Authorization Contract

## Task 1.1 — Pertahankan Owner-only Master Catalog

Semua endpoint master berikut harus tetap owner-only:

- categories
- product master
- variants
- media
- modifier groups
- modifiers
- product draft
- product-to-outlet assignment/removal/replacement

Gunakan middleware/controller authorization yang sudah ada; jangan memperkenalkan pengecekan seperti:

```php
if ($user->role === 'outlet_staff') { ... }
```

di banyak controller.

## Task 1.2 — Validasi capability catalog outlet

Pastikan `OutletRoleCapabilityResolver` menjadi single source of truth untuk capability:

- `merchant.operations.catalog.view`
- `merchant.operations.catalog.availability.update`
- `merchant.operations.catalog.assignment.status.update`
- `merchant.operations.catalog.order.update`

Target:

### outlet_manager

Harus memiliki:

- `merchant.operations.catalog.view`
- `merchant.operations.catalog.availability.update`
- `merchant.operations.catalog.assignment.status.update`
- `merchant.operations.catalog.order.update`

### outlet_staff

Harus memiliki:

- `merchant.operations.catalog.view`
- `merchant.operations.catalog.availability.update`

## Task 1.3 — Capability test

Tambahkan unit test untuk resolver:

- [ ] manager mendapatkan semua capability Catalog yang ditargetkan.
- [ ] staff hanya mendapatkan capability yang ditargetkan.
- [ ] staff tidak mendapatkan assignment status.
- [ ] staff tidak mendapatkan ordering.
- [ ] tidak ada capability master CRUD yang diberikan melalui outlet-role resolver.

---

# 5. Phase 2 — Pisahkan Master Catalog dan Outlet Catalog

## Task 2.1 — Jangan membuka endpoint master product list untuk employee

**Jangan** mengubah:

`GET /merchant/catalog/products`

menjadi endpoint employee.

Alasannya:

- endpoint tersebut merepresentasikan master catalog merchant;
- membuka endpoint tersebut ke employee dapat menyebabkan employee melihat product yang tidak assigned ke outlet mereka;
- frontend employee kemudian bergantung pada scope yang salah.

Tetap:

> `GET /merchant/catalog/products` = Master Catalog = owner-only.

## Task 2.2 — Jadikan Outlet Catalog sebagai sumber data employee

Endpoint:

`GET /merchant/catalog/outlets/{outlet}/products`

menjadi sumber data utama bagi:

- outlet_manager
- outlet_staff

Endpoint harus tetap:

- authorize outlet;
- enforce `merchant.operations.catalog.view`;
- query hanya assignment product untuk outlet tersebut.

## Task 2.3 — Pertahankan effective catalog semantics

`CatalogQueryService::outletCatalog()` sudah menggunakan:

- product master
- join `outlet_products`
- filter berdasarkan outlet
- assignment state
- availability
- display order

Behavior ini harus dipertahankan.

Pastikan query **tidak pernah** berubah menjadi:

> semua product milik merchant.

Query harus selalu bermakna:

> product milik merchant **yang mempunyai assignment ke outlet target**.

---

# 6. Phase 3 — Tambahkan Outlet-scoped Product Detail

## Task 3.1 — Tambahkan endpoint product detail outlet

Tambahkan endpoint rekomendasi:

`GET /merchant/catalog/outlets/{outlet}/products/{product}`

Tujuan:

Employee dapat membuka detail product yang tampil pada Outlet Catalog tanpa menggunakan endpoint master product detail.

## Task 3.2 — Authorization flow

Endpoint harus menjalankan validasi berurutan:

1. authorize outlet melalui `MerchantOperationsAuthorization::authorizeOutletAction()`;
2. gunakan capability `merchant.operations.catalog.view`;
3. pastikan product berasal dari merchant outlet tersebut;
4. pastikan product mempunyai assignment pada outlet tersebut;
5. jika tidak assigned ke outlet → jangan expose product;
6. return resource detail dalam konteks outlet.

## Task 3.3 — Hindari cross-outlet access

Scenario wajib:

- User staff assigned ke Outlet A.
- Product X assigned ke Outlet A.
- Product Y hanya assigned ke Outlet B.

Expected:

- GET Outlet A/Product X → 200
- GET Outlet A/Product Y → 404
- GET Outlet B/... → 403 bila Outlet B merchant yang sama tetapi bukan assignment user
- foreign outlet → 404 sesuai contract existing.

## Task 3.4 — Tentukan response resource

Gunakan response yang konsisten dengan Outlet Catalog, sehingga frontend tidak perlu menganggap employee memiliki hak terhadap master product.

Response minimal perlu mampu menampilkan:

- product identity
- description
- product type
- price
- master product status
- category
- variants yang dapat dilihat
- primary media
- modifier groups yang relevan
- assignment state outlet
- availability state outlet
- display order outlet
- sellability/effective state bila memang bagian dari existing Outlet Catalog contract

Jangan menambahkan field baru yang tidak dibutuhkan oleh use case employee.

---

# 7. Phase 4 — Review Detail Data Exposure

## Task 4.1 — Audit ProductDetailResource vs OutletCatalogItemResource

Pastikan employee tidak mendapatkan data master yang secara tidak sengaja mengandung:

- assignment outlet lain;
- daftar seluruh outlet product;
- internal master administration data yang hanya diperlukan owner;
- data yang memungkinkan employee mengelola master.

## Task 4.2 — Pertahankan read-only semantics

Response detail employee boleh kaya informasi karena role tetap perlu melihat product, tetapi:

> informasi detail ≠ permission untuk mengubah resource.

Authorization write tetap owner-only.

---

# 8. Phase 5 — Tegaskan Endpoint Operasional Outlet

Endpoint berikut tetap berada di luar owner-only group:

### Assignment status

- POST `/products/{product}/outlets/{outlet}/activate`
- POST `/products/{product}/outlets/{outlet}/deactivate`

Authorization:

- owner → allowed
- manager → allowed
- staff → forbidden

Capability:

`merchant.operations.catalog.assignment.status.update`

### Availability

- POST `/products/{product}/outlets/{outlet}/availability`

Authorization:

- owner → allowed
- manager → allowed
- staff → allowed

Capability:

`merchant.operations.catalog.availability.update`

### Outlet reorder

- PUT `/outlets/{outlet}/products/order`

Authorization:

- owner → allowed
- manager → allowed
- staff → forbidden

Capability:

`merchant.operations.catalog.order.update`

## Task 5.1 — Tegaskan perbedaan status

Dokumentasi API dan naming harus membedakan:

### Master product status

`POST /products/{product}/activate|deactivate`

Artinya:

> mengubah status Product master.

Owner-only.

### Outlet assignment status

`POST /products/{product}/outlets/{outlet}/activate|deactivate`

Artinya:

> mengaktifkan/nonaktifkan product pada outlet tertentu.

Manager dapat melakukannya.

Jangan menyamakan dua operasi ini sebagai satu konsep.

---

# 9. Phase 6 — Validation & Scope Safety

## Task 6.1 — Validate product/outlet ownership

Untuk semua action outlet-product:

- [ ] product harus milik merchant outlet.
- [ ] outlet harus authorized.
- [ ] assignment harus benar-benar ada.

## Task 6.2 — Prevent unauthorized assignment probing

Pastikan UUID product dari merchant yang sama tetapi:

- tidak assigned ke outlet target

tidak dapat dipakai employee untuk memanipulasi state.

## Task 6.3 — Reorder validation

Untuk reorder outlet:

- semua product ID wajib assigned ke outlet tersebut;
- tidak boleh update `products.display_order`;
- hanya update `outlet_products.display_order`;
- transaction tetap digunakan.

Behavior existing `reorderOutletCatalog()` harus dipertahankan.

## Task 6.4 — Filter semantics

Review filter:

- search
- category_id
- status
- availability
- sort
- order
- per_page

Pastikan filter tidak memungkinkan product di luar assignment outlet masuk ke result.

---

# 10. Phase 7 — Category Strategy untuk Employee

Untuk employee, category hanya dibutuhkan sebagai informasi/filter pada effective catalog.

## Task 7.1 — Jangan berikan category CRUD

Tetap owner-only:

- create
- update
- delete
- activate
- deactivate
- reorder

## Task 7.2 — Evaluasi kebutuhan category list

Prioritas implementasi:

1. Gunakan category data yang sudah ikut pada Outlet Catalog response jika frontend cukup untuk menampilkan/filter.
2. Hanya tambahkan endpoint outlet-scoped categories jika frontend benar-benar membutuhkan daftar category independent.

Rekomendasi endpoint apabila dibutuhkan:

`GET /merchant/catalog/outlets/{outlet}/categories`

Endpoint tersebut hanya boleh mengembalikan category yang digunakan oleh product yang assigned ke outlet.

**Jangan** membuka:

`GET /merchant/catalog/categories`

kepada employee hanya untuk kebutuhan filter.

---

# 11. Phase 8 — Resource & Query Consistency

## Task 8.1 — Outlet Catalog list/detail harus konsisten

List dan detail outlet sebaiknya menggunakan terminology yang sama:

- product
- category
- variants
- media
- modifier_groups
- assignment
- availability
- sellability

## Task 8.2 — Preserve existing query optimizations

Jangan menghilangkan:

- eager loading
- media URL hydration
- ordered relation loading
- pagination
- outlet assignment join
- indexes/query constraints yang sudah ada

## Task 8.3 — Avoid N+1

Untuk outlet detail:

- load category
- load variants
- load media
- load modifier groups
- load modifiers
- load assignment outlet

secara terkontrol.

---

# 12. Phase 9 — Automated Test Matrix

Implementasikan test berdasarkan behavior, bukan hanya status code.

## 9.1 Owner

### Master product

- [ ] list → 200
- [ ] show → 200
- [ ] create → 201
- [ ] update → 200
- [ ] delete → 204
- [ ] activate → 200
- [ ] deactivate → 200
- [ ] reorder → 204

### Categories

- [ ] CRUD
- [ ] activate/deactivate
- [ ] reorder

### Nested catalog resources

- [ ] variants
- [ ] media
- [ ] modifier groups
- [ ] modifiers

### Assignments

- [ ] list assignments
- [ ] assign
- [ ] replace
- [ ] remove
- [ ] activate/deactivate assignment
- [ ] availability

### Outlet Catalog

- [ ] list outlet catalog
- [ ] outlet product detail
- [ ] reorder outlet catalog

## 9.2 Outlet Manager

Master Catalog:

- [ ] GET master products → 403
- [ ] GET master product detail → 403
- [ ] POST product → 403
- [ ] PATCH product → 403
- [ ] DELETE product → 403
- [ ] master activate/deactivate → 403
- [ ] category CRUD → 403
- [ ] variant/media/modifier write → 403
- [ ] product assignment create/remove/replace → 403

Outlet Catalog:

- [ ] own outlet list → 200
- [ ] own outlet detail → 200
- [ ] availability → 200
- [ ] assignment activate → 200
- [ ] assignment deactivate → 200
- [ ] reorder → 204
- [ ] another outlet same merchant → 403
- [ ] foreign outlet → 404

## 9.3 Outlet Staff

Master Catalog:

- [ ] GET master products → 403
- [ ] GET master product detail → 403
- [ ] POST product → 403
- [ ] PATCH product → 403
- [ ] DELETE product → 403
- [ ] master activate/deactivate → 403
- [ ] category CRUD → 403
- [ ] variant/media/modifier write → 403
- [ ] assignment create/remove/replace → 403

Outlet Catalog:

- [ ] own outlet list → 200
- [ ] own outlet detail → 200
- [ ] availability → 200
- [ ] assignment activate/deactivate → 403
- [ ] reorder → 403
- [ ] another outlet same merchant → 403
- [ ] foreign outlet → 404

## 9.4 Cross-outlet data isolation

Test wajib:

- [ ] Product hanya assigned Outlet A tidak muncul di Outlet B.
- [ ] Product detail outlet B tidak dapat membaca Product yang hanya assigned Outlet A.
- [ ] Reorder hanya menerima product yang assigned ke outlet target.
- [ ] Availability hanya dapat mengubah assignment outlet target.
- [ ] Assignment status hanya mengubah assignment outlet target.

---

# 13. Phase 10 — Regression Test

Setelah perubahan authorization:

- [ ] Existing owner Catalog behavior tetap berjalan.
- [ ] Existing Product Draft owner behavior tetap berjalan.
- [ ] Existing media upload owner behavior tetap berjalan.
- [ ] Existing outlet operations tidak rusak.
- [ ] Existing outlet authorization 403/404 semantics tetap berjalan.
- [ ] Tidak ada perubahan behavior pada module Merchant Operations di luar yang dibutuhkan Catalog.

---

# 14. Phase 11 — API Documentation

Update Scramble/OpenAPI annotations untuk endpoint baru dan endpoint yang berubah secara behavior.

Minimal dokumentasikan:

- endpoint
- purpose
- required capability
- role scope
- path parameters
- filter/query parameters
- response shape
- 403 behavior
- 404 behavior

Dokumentasi harus membedakan:

- Master Catalog
- Outlet Catalog
- Outlet Product Detail
- Outlet Assignment Operations

---

# 15. Phase 12 — Implementation Boundaries

Perubahan **BOLEH** mencakup:

- Catalog authorization service
- Catalog query service
- Catalog controllers
- Catalog resources
- Catalog requests
- Catalog actions bila diperlukan untuk scope enforcement
- route Catalog
- Catalog tests
- capability resolver hanya sejauh capability Catalog
- migration/index hanya bila benar-benar diperlukan oleh query/constraint Catalog

Perubahan **TIDAK BOLEH** mencakup:

- UI `jualantar-merchant`
- module Notifications
- Orders
- Payments
- Delivery
- Merchant Registration
- Merchant Approval
- authentication flow
- unrelated Merchant Operations behavior
- perubahan business rule Product yang tidak berkaitan dengan permission/scope Catalog

---

# 16. Acceptance Criteria

Revisi API Catalog dianggap selesai apabila:

### Authorization

- [ ] Owner tetap memiliki akses penuh terhadap Master Catalog.
- [ ] Manager tidak dapat melakukan master Catalog write.
- [ ] Staff tidak dapat melakukan master Catalog write.
- [ ] Manager dapat melihat Catalog product yang assigned ke outletnya.
- [ ] Staff dapat melihat Catalog product yang assigned ke outletnya.
- [ ] Manager dapat update availability.
- [ ] Staff dapat update availability.
- [ ] Manager dapat update outlet assignment status.
- [ ] Staff tidak dapat update outlet assignment status.
- [ ] Manager dapat reorder outlet Catalog.
- [ ] Staff tidak dapat reorder outlet Catalog.

### Scope isolation

- [ ] Employee tidak dapat melihat product yang tidak assigned ke outletnya melalui Outlet Catalog.
- [ ] Employee tidak dapat mengakses outlet lain di merchant yang sama kecuali assigned.
- [ ] Foreign outlet tetap menghasilkan 404 sesuai contract.
- [ ] Unauthorized capability tetap menghasilkan 403.

### Master / Outlet separation

- [ ] Master Product list tetap owner-only.
- [ ] Master Product detail tetap owner-only.
- [ ] Outlet Product list menggunakan endpoint outlet-scoped.
- [ ] Outlet Product detail menggunakan endpoint outlet-scoped.
- [ ] Master deactivate berbeda dengan outlet assignment deactivate.

### Quality

- [ ] Test matrix role lengkap.
- [ ] Regression test owner lulus.
- [ ] Tidak ada N+1 baru pada Outlet Catalog.
- [ ] OpenAPI/Scramble documentation diperbarui.
- [ ] Tidak ada scope creep ke module lain.

---

# 17. Urutan Implementasi yang Wajib Diikuti

Gunakan urutan ini agar revisi tidak keluar konteks:

1. **Audit baseline Catalog**
2. **Kunci authorization contract**
3. **Pastikan owner-only Master Catalog**
4. **Validasi outlet Catalog capability resolver**
5. **Review/pertahankan outlet Catalog query**
6. **Implement outlet-scoped product detail**
7. **Review resource/data exposure**
8. **Validasi outlet assignment + availability + reorder**
9. **Implement automated authorization tests**
10. **Implement cross-outlet isolation tests**
11. **Run regression test owner**
12. **Update API documentation**
13. **Final scope review**

---

# 18. Definition of Done

PR revisi Catalog API hanya dianggap siap apabila reviewer dapat menyimpulkan:

> **“Merchant/owner mengontrol master catalog; outlet_manager dan outlet_staff hanya melihat dan mengoperasikan catalog yang sudah ditugaskan ke outlet mereka.”**

Dan tidak ada satu pun endpoint API yang memungkinkan employee melewati boundary tersebut hanya dengan memanggil endpoint secara langsung.
