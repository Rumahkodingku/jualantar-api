# PLAN.md — Revisi Product Detail Resource API

## 1. Document Information

- **Project:** JualAntar API
- **Module:** `App\Modules\Merchant`
- **Feature:** Product Detail Resource
- **Primary Resource:** `app/Modules/Merchant/Http/Resources/ProductDetailResource.php`
- **Related Resource:** `app/Modules/Merchant/Http/Resources/ProductResource.php`
- **Primary Controller:** `app/Modules/Merchant/Http/Catalog/ProductController.php`
- **Consumer:** `jualantar-merchant`, module `app/modules/catalogs`
- **Status:** Planning
- **Objective:** Menyediakan contract `Product Detail` yang lebih siap digunakan oleh UI Product Detail baru tanpa mengubah kontrak child-resource yang sudah digunakan oleh frontend.

---

## 2. Background

Saat ini endpoint detail product sudah menggunakan `ProductDetailResource`.

Flow backend saat ini:

```text
ProductController@show
        │
        ├── authorization->product()
        │
        ├── load category
        ├── load variants
        ├── load media
        ├── load modifierGroups
        ├── load modifierGroups.modifiers
        ├── hydrate media URLs
        │
        └── ProductDetailResource
```

`ProductDetailResource` saat ini mewarisi `ProductResource` dan menambahkan:

- `category`
- `variants`
- `media`
- `modifier_groups`

Sedangkan `ProductResource` sudah mempunyai aggregate fields yang digunakan oleh Product Listing:

- `variants_count`
- `min_price`
- `media_count`
- `modifier_groups_count`

Namun controller detail saat ini tidak secara eksplisit meminta aggregate tersebut. Resource juga mendokumentasikan bahwa aggregate tersebut ditujukan untuk listing/card.

Akibatnya, frontend Product Detail masih harus melakukan sebagian derivasi sendiri, terutama:

- menentukan harga minimum dari variants;
- menentukan jumlah child resources untuk kebutuhan navigasi/detail UI;
- mengambil Outlet Assignment secara terpisah ketika tab Outlet dibuka.

---

# 3. Problem Statement

UI Product Detail yang akan direvisi membutuhkan informasi summary yang konsisten untuk:

```text
Product Header
├── Product identity
├── Status
├── Category / product type
└── Price summary

Detail Navigation
├── Ringkasan
├── Variant <count>
├── Customization <count>
├── Media <count>
└── Outlet <count>
```

Contract saat ini belum mempunyai object `summary` khusus untuk Product Detail.

Selain itu:

1. harga variable masih dihitung oleh frontend dari `variants`;
2. count resource masih bergantung pada panjang array yang tersedia di frontend;
3. Outlet Detail tetap sebaiknya lazy-loaded agar Product Detail tidak membawa seluruh assignment outlet;
4. `ProductResource` dan `ProductDetailResource` memiliki kebutuhan aggregate yang berbeda sehingga perlu dipisahkan dengan jelas;
5. perubahan tidak boleh merusak contract Product Listing.

---

# 4. Goals

## 4.1 Primary Goals

1. Menambahkan `summary` khusus pada `ProductDetailResource`.
2. Menyediakan price summary yang sudah siap ditampilkan UI.
3. Menyediakan count untuk:
    - variants;
    - customization groups;
    - media;
    - outlet assignments.
4. Mempertahankan child resource yang sudah ada.
5. Mempertahankan endpoint Outlet Assignment terpisah.
6. Menghindari perhitungan business-derived data di frontend.
7. Meminimalkan perubahan pada contract Product Listing.
8. Memastikan query detail tetap terkontrol dan tidak menimbulkan N+1 query.

## 4.2 Secondary Goals

- Contract lebih mudah dipetakan ke TypeScript `ProductDetail`.
- UI Product Detail dapat dibuat tanpa mengetahui detail query/database.
- OpenAPI/Scramble documentation tetap menggambarkan response baru.
- Test resource dapat memverifikasi contract secara eksplisit.

---

# 5. Non-Goals

Perubahan ini TIDAK mencakup:

- perubahan database schema;
- perubahan model domain Product;
- perubahan endpoint CRUD Product;
- perubahan endpoint Variant;
- perubahan endpoint Media;
- perubahan endpoint Modifier;
- perubahan endpoint Product Outlet;
- perubahan authorization;
- perubahan upload media;
- perubahan business rule Product;
- memasukkan seluruh Outlet Assignment ke Product Detail;
- redesign frontend Product Detail;
- perubahan Product Listing contract kecuali refactor internal yang diperlukan untuk memisahkan summary listing dan detail.

---

# 6. Existing API Contract

## 6.1 ProductResource

`ProductResource` saat ini menyediakan:

```json
{
    "id": "...",
    "category_id": "...",
    "category": {
        "id": "...",
        "name": "...",
        "status": "active"
    },
    "name": "...",
    "description": "...",
    "product_type": "simple",
    "price": 10000,
    "status": "active",
    "display_order": 0,
    "primary_media": {
        "url": "...",
        "alt_text": "..."
    },
    "variants_count": 3,
    "min_price": 10000,
    "media_count": 4,
    "modifier_groups_count": 2,
    "created_at": "...",
    "updated_at": "..."
}
```

Aggregate tersebut digunakan untuk kebutuhan Product Listing/Card.

## 6.2 ProductDetailResource

Saat ini:

```json
{
    "...ProductResource fields": "...",
    "category": {},
    "variants": [],
    "media": [],
    "modifier_groups": []
}
```

Detail endpoint melakukan eager loading terhadap:

```php
[
    'category',
    'variants',
    'media',
    'modifierGroups',
    'modifierGroups.modifiers',
]
```

dan melakukan hydration temporary media URL.

---

# 7. Target Product Detail Contract

Target response:

```json
{
    "data": {
        "id": "product-id",
        "category_id": "category-id",
        "category": {
            "id": "category-id",
            "name": "Makanan",
            "description": null,
            "status": "active",
            "display_order": 0,
            "created_at": "...",
            "updated_at": "..."
        },
        "name": "Nasi Goreng",
        "description": "Nasi goreng spesial",
        "product_type": "variable",
        "price": null,
        "status": "active",
        "display_order": 0,
        "primary_media": {
            "url": "...",
            "alt_text": "Nasi Goreng"
        },

        "summary": {
            "price": {
                "type": "from",
                "value": 15000
            },
            "variants_count": 3,
            "customization_groups_count": 2,
            "media_count": 4,
            "outlets_count": 2
        },

        "variants": [],
        "media": [],
        "modifier_groups": [],

        "created_at": "...",
        "updated_at": "..."
    }
}
```

> `currency` tidak ditambahkan sebagai field baru pada fase ini karena codebase yang dianalisis belum menunjukkan bahwa Product Detail Resource memiliki currency contract tersendiri. Formatting currency tetap menjadi concern frontend sampai ada domain requirement untuk multi-currency.

---

# 8. Summary Contract

## 8.1 `summary.price`

```json
{
    "type": "fixed",
    "value": 15000
}
```

untuk simple product.

Untuk variable product:

```json
{
    "type": "from",
    "value": 15000
}
```

`value` merupakan harga minimum dari variant aktif.

### Rules

#### Simple Product

```text
product_type = simple
→ summary.price.type = fixed
→ summary.price.value = product.price
```

#### Variable Product

```text
product_type = variable
→ summary.price.type = from
→ summary.price.value = minimum price variant aktif
```

Jika variable product tidak memiliki variant aktif:

```json
{
    "type": "from",
    "value": null
}
```

Frontend dapat menampilkan state seperti `Belum ada harga`.

### Important

Harga minimum harus dihitung pada backend/query/resource layer, bukan oleh React component.

---

# 9. Summary Counts

## 9.1 `variants_count`

Mengikuti semantics Product Listing saat ini:

```text
variants_count = active variants
```

Implementasi harus konsisten dengan aggregate existing:

```php
variants as variants_count
where status = active
```

Jangan mengubah semantics Product Listing secara diam-diam.

---

## 9.2 `customization_groups_count`

Mengikuti aggregate Product Listing saat ini:

```text
modifier_groups_count
```

Untuk contract detail, expose dengan nama yang lebih eksplisit:

```json
"customization_groups_count": 2
```

Nilai berasal dari jumlah modifier groups product.

### Naming decision

Frontend menggunakan istilah `Customization`, sedangkan domain model/resource menggunakan `modifierGroups`.

API detail menggunakan:

```text
customization_groups_count
```

agar contract mudah dipetakan ke UI.

---

## 9.3 `media_count`

Jumlah seluruh media product.

```json
"media_count": 4
```

Tidak perlu memasukkan media object tambahan ke summary.

---

## 9.4 `outlets_count`

Jumlah Product Outlet Assignment untuk product.

```json
"outlets_count": 2
```

Fase pertama menggunakan jumlah assignment yang dimiliki product.

### Important

`outlets_count` tidak berarti:

- jumlah outlet aktif;
- jumlah outlet available;
- jumlah outlet online.

Itu adalah jumlah assignment product → outlet.

Jika business rule di masa depan membutuhkan `active_outlets_count` atau `available_outlets_count`, field tersebut harus ditambahkan sebagai contract terpisah dan tidak mengubah semantics `outlets_count` secara implisit.

---

# 10. Outlet Strategy

## 10.1 Jangan memasukkan `outlets[]` ke ProductDetail

Product Detail **tidak** perlu mengembalikan:

```json
"outlets": []
```

pada fase ini.

Alasannya:

1. assignment outlet sudah mempunyai endpoint tersendiri;
2. assignment dapat bertambah;
3. frontend saat ini sudah lazy-load Outlet;
4. Product Detail tidak perlu membawa seluruh assignment hanya untuk header/count;
5. mengurangi payload awal.

---

## 10.2 Tetap gunakan endpoint Product Outlet

Flow tetap:

```text
GET /products/{product}/outlets
```

ketika user membuka tab Outlet.

Product Detail hanya menyediakan:

```json
"summary": {
  "outlets_count": 2
}
```

---

# 11. Resource Architecture

Target architecture:

```text
ProductResource
│
├── Base product fields
├── Listing aggregates
└── Listing-specific response

ProductDetailResource
│
├── Base product fields
├── Detail summary
├── category
├── variants
├── media
└── modifier_groups
```

Jangan membuat:

```text
ProductDetailVariantResource
ProductDetailMediaResource
ProductDetailModifierResource
```

karena child resources yang sekarang sudah sesuai dengan kebutuhan frontend.

Gunakan:

```text
ProductVariantResource
ProductMediaResource
ProductModifierGroupResource
ProductModifierResource
CatalogCategoryResource
```

---

# 12. Required Backend Refactor

## 12.1 Pisahkan base fields dari aggregate fields

Saat ini `ProductDetailResource` mewarisi `ProductResource`.

Masalahnya:

```php
ProductDetailResource
    extends ProductResource
```

sementara `ProductResource` membawa aggregate listing fields.

Detail membutuhkan summary dengan semantics berbeda.

### Target

Refactor `ProductResource` agar aggregate dapat dikontrol.

Contoh pendekatan:

```php
protected function summaryFields(): array
{
    return [
        // listing-specific aggregate fields
    ];
}
```

atau mekanisme protected method sejenis.

`ProductDetailResource` kemudian dapat:

```php
protected function summaryFields(): array
{
    return [];
}
```

dan menambahkan:

```php
'summary' => $this->detailSummary(),
```

Tujuannya adalah menghindari response seperti:

```json
{
    "variants_count": 3,
    "min_price": 15000,
    "media_count": 4,
    "modifier_groups_count": 2,
    "summary": {
        "variants_count": 3,
        "media_count": 4
    }
}
```

### Tidak boleh ada duplicate aggregate contract.

---

# 13. Recommended Resource Design

Target konseptual:

```php
class ProductDetailResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),

            'summary' => $this->detailSummary(),

            'category' => new CatalogCategoryResource(
                $this->whenLoaded('category')
            ),

            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),

            'media' => ProductMediaResource::collection(
                $this->whenLoaded('media')
            ),

            'modifier_groups' => ProductModifierGroupResource::collection(
                $this->whenLoaded('modifierGroups')
            ),
        ];
    }
}
```

`detailSummary()` sebaiknya tidak melakukan arbitrary lazy queries dari resource.

---

# 14. Query Strategy

## 14.1 Current Detail Query

Saat ini controller:

```php
$model->load([
    'category',
    'variants',
    'media',
    'modifierGroups' => fn ($relation) => $this->ordered($relation),
    'modifierGroups.modifiers' => fn ($relation) => $this->ordered($relation),
]);
```

Target tetap mempertahankan eager loading tersebut.

---

## 14.2 Aggregate Query

Tambahkan aggregate yang dibutuhkan detail.

Target data:

```text
variants_count
media_count
modifier_groups_count
outlets_count
min_price
```

Namun query harus mempertimbangkan bahwa detail sudah mengambil child collections.

Jangan mengandalkan collection PHP untuk count jika count dapat diperoleh langsung dari database secara efisien.

---

# 15. Recommended Controller Query

Konsep target:

```php
$model = Product::query()
    ->with([
        'category',
        'variants',
        'media',
        'modifierGroups' => fn ($relation) => $this->ordered($relation),
        'modifierGroups.modifiers' => fn ($relation) => $this->ordered($relation),
    ])
    ->withCount([
        'variants as variants_count' => fn ($relation) =>
            $this->activeVariants($relation),

        'media as media_count',

        'modifierGroups as modifier_groups_count',

        'outletProducts as outlets_count',
    ])
    ->withMin(
        [
            'variants as min_price' => fn ($relation) =>
                $this->activeVariants($relation),
        ],
        'price',
    )
    ->findOrFail(...);
```

### Important

Implementasi final harus tetap menggunakan authorization flow existing:

```php
$this->authorization->product($product)
```

Jangan mengganti authorization dengan query manual yang bypass existing policy/service.

Jika aggregate query perlu dipasang pada model yang sudah di-resolve oleh authorization, gunakan query/load strategy yang kompatibel dengan architecture tersebut.

---

# 16. Price Summary Calculation

Resource tidak boleh menjalankan query database.

Gunakan attribute hasil query:

```php
$this->resource->min_price
```

dan:

```php
$this->resource->product_type
$this->resource->price
```

Konsep:

```php
private function detailPrice(): array
{
    if ($this->product_type === ProductType::Simple) {
        return [
            'type' => 'fixed',
            'value' => $this->price,
        ];
    }

    return [
        'type' => 'from',
        'value' => $this->min_price,
    ];
}
```

Enum `ProductType` harus digunakan sesuai domain yang sudah ada.

---

# 17. Null and Empty Rules

## 17.1 Category

Tetap mengikuti behavior existing:

```json
"category": null
```

jika relation tidak tersedia/tidak ada.

## 17.2 Primary Media

Tetap:

```json
"primary_media": null
```

jika tidak ada primary media.

## 17.3 Variable Product Without Variant

```json
"summary": {
  "price": {
    "type": "from",
    "value": null
  },
  "variants_count": 0
}
```

## 17.4 No Customization

```json
"customization_groups_count": 0
```

## 17.5 No Media

```json
"media_count": 0
```

## 17.6 No Outlet Assignment

```json
"outlets_count": 0
```

---

# 18. Child Resource Contract

Tidak ada perubahan contract pada:

```text
ProductVariantResource
ProductMediaResource
ProductModifierGroupResource
ProductModifierResource
CatalogCategoryResource
OutletProductAssignmentResource
```

Khusus Outlet:

```text
OutletProductAssignmentResource
```

tetap menjadi contract untuk endpoint Product Outlet.

---

# 19. Frontend Integration Target

Frontend `catalog.types.ts` dapat diarahkan menjadi:

```ts
export interface ProductDetailSummary {
    price: {
        type: "fixed" | "from";
        value: number | null;
    };
    variants_count: number;
    customization_groups_count: number;
    media_count: number;
    outlets_count: number;
}

export interface ProductDetail extends Product {
    category?: CatalogCategory;
    summary?: ProductDetailSummary;
    variants?: ProductVariant[];
    media?: ProductMedia[];
    modifier_groups?: ProductModifierGroup[];
}
```

`outlets` tidak perlu ditambahkan ke `ProductDetail`.

Frontend tetap menggunakan query:

```ts
useProductAssignments(productId);
```

untuk tab Outlet.

---

# 20. Frontend Mapping

Target UI:

```text
Product Detail
│
├── Header
│   ├── name
│   ├── status
│   ├── category
│   ├── product_type
│   └── summary.price
│
├── Navigation
│   ├── Ringkasan
│   ├── Variant       ← summary.variants_count
│   ├── Customization ← summary.customization_groups_count
│   ├── Media         ← summary.media_count
│   └── Outlet        ← summary.outlets_count
│
└── Content
    ├── variants
    ├── modifier_groups
    ├── media
    └── lazy-loaded outlet assignments
```

---

# 21. API Documentation / Scramble

Update `ProductController@show` documentation agar response type mencerminkan field `summary`.

Jangan membiarkan documentation hanya menyatakan:

```php
ProductDetailResource
```

tanpa contract detail yang jelas jika project convention memungkinkan typed array documentation.

Target documentation harus menjelaskan minimal:

```text
summary.price
summary.variants_count
summary.customization_groups_count
summary.media_count
summary.outlets_count
```

---

# 22. Testing Plan

## 22.1 Resource Unit Test

Buat/ubah test untuk memastikan:

### Simple Product

- `summary.price.type === fixed`
- `summary.price.value === product.price`

### Variable Product

- `summary.price.type === from`
- `summary.price.value === minimum active variant price`

### No Active Variant

- `summary.price.value === null`
- `summary.variants_count === 0`

### Counts

- variants count benar;
- customization group count benar;
- media count benar;
- outlet assignment count benar.

---

# 23. Controller Feature Test

Test endpoint:

```text
GET /api/v1/merchant/catalog/products/{product}
```

Pastikan:

1. response `200`;
2. `data.summary` tersedia;
3. summary fields memiliki tipe yang benar;
4. child resources tetap tersedia;
5. modifier groups tetap mempunyai modifiers;
6. media URL tetap ter-hydrate;
7. authorization existing tetap berlaku.

---

# 24. Regression Test Product Listing

Pastikan perubahan ProductResource tidak merusak:

```text
GET /api/v1/merchant/catalog/products
```

Validasi:

```text
variants_count
min_price
media_count
modifier_groups_count
```

tetap tersedia dan semantics-nya tidak berubah.

---

# 25. Regression Test Product Write Endpoints

Pastikan response berikut tidak berubah secara tidak sengaja:

```text
POST   /products
PATCH  /products/{product}
DELETE /products/{product}
POST   /products/{product}/activate
POST   /products/{product}/deactivate
```

Terutama karena semuanya masih menggunakan `ProductResource`.

---

# 26. Query / Performance Requirements

## Must

- Tidak ada N+1 query dari Resource.
- Resource tidak melakukan query database.
- Child relations tetap eager-loaded.
- Outlet assignments tidak di-load sebagai collection pada Product Detail.
- Media URL hydration tetap dilakukan satu kali pada detail media collection.
- Aggregate count menggunakan database aggregate bila memungkinkan.

## Must Not

Jangan melakukan:

```php
$product->variants()->count()
```

di dalam `ProductDetailResource`.

Jangan melakukan:

```php
$product->outletProducts()->count()
```

di dalam Resource.

Jangan melakukan query baru untuk setiap field summary.

---

# 27. Naming Rules

Gunakan naming berikut:

| Purpose                   | API Field                            |
| ------------------------- | ------------------------------------ |
| Harga detail              | `summary.price`                      |
| Price mode                | `summary.price.type`                 |
| Price value               | `summary.price.value`                |
| Variant count             | `summary.variants_count`             |
| Customization group count | `summary.customization_groups_count` |
| Media count               | `summary.media_count`                |
| Outlet assignment count   | `summary.outlets_count`              |

Jangan menggunakan:

```text
customizations_count
variant_count
photos_count
outlet_count
```

untuk contract ini.

---

# 28. Files Expected To Change

## Primary

```text
app/Modules/Merchant/Http/Resources/ProductDetailResource.php
```

## Related

```text
app/Modules/Merchant/Http/Resources/ProductResource.php
app/Modules/Merchant/Http/Catalog/ProductController.php
```

## Tests

Lokasi test mengikuti struktur existing project untuk:

```text
ProductController
ProductResource
ProductDetailResource
```

Jangan membuat struktur test baru jika project sudah memiliki lokasi test yang sesuai.

---

# 29. Implementation Sequence

## Step 1 — Freeze Existing Contract

Dokumentasikan contract `ProductResource` saat ini.

Pastikan Product Listing tetap memiliki:

```text
variants_count
min_price
media_count
modifier_groups_count
```

## Step 2 — Refactor ProductResource Internally

Pisahkan:

```text
base fields
```

dan:

```text
listing aggregates
```

tanpa mengubah output Product Listing.

## Step 3 — Add Detail Aggregate Query

Tambahkan aggregate yang dibutuhkan Product Detail:

```text
variants_count
media_count
modifier_groups_count
outlets_count
min_price
```

## Step 4 — Implement `ProductDetailResource::summary`

Tambahkan:

```text
summary.price
summary.variants_count
summary.customization_groups_count
summary.media_count
summary.outlets_count
```

## Step 5 — Prevent Duplicate Fields

Pastikan Product Detail tidak mengembalikan:

```text
variants_count
min_price
media_count
modifier_groups_count
```

di root jika fields tersebut sudah dipindahkan ke:

```text
summary
```

## Step 6 — Update API Documentation

Update Scramble/OpenAPI annotations.

## Step 7 — Add Tests

Test:

- simple;
- variable;
- empty variants;
- counts;
- outlet count;
- child resource;
- authorization;
- media URL.

## Step 8 — Regression Test

Pastikan Product Listing dan write endpoints tidak rusak.

---

# 30. Definition of Done

Implementasi dianggap selesai jika:

- [ ] `ProductDetailResource` mempunyai `summary`.
- [ ] `summary.price` tersedia.
- [ ] Simple product menggunakan `fixed`.
- [ ] Variable product menggunakan `from`.
- [ ] Minimum price berasal dari active variants.
- [ ] Variable product tanpa active variant menghasilkan `value: null`.
- [ ] `variants_count` tersedia.
- [ ] `customization_groups_count` tersedia.
- [ ] `media_count` tersedia.
- [ ] `outlets_count` tersedia.
- [ ] `outlets[]` tidak dimasukkan ke Product Detail.
- [ ] Child resources existing tetap digunakan.
- [ ] Product Listing contract tidak berubah.
- [ ] Product write response tidak berubah secara tidak sengaja.
- [ ] Tidak ada N+1 query baru.
- [ ] Resource tidak menjalankan database query.
- [ ] Media URL hydration tetap berjalan.
- [ ] OpenAPI/Scramble documentation diperbarui.
- [ ] Unit/resource tests tersedia.
- [ ] Feature/controller tests tersedia.
- [ ] Regression tests Product Listing lulus.

---

# 31. Final Architecture

```text
                         Product Detail API
                                │
                                ▼
                     ProductController@show
                                │
             ┌──────────────────┼──────────────────┐
             │                  │                  │
             ▼                  ▼                  ▼
        Authorization      Eager Loading       Aggregates
             │                  │                  │
             │          ┌───────┼────────┐         │
             │          │       │        │         │
             │       category variants media       │
             │                  │        │         │
             │          modifierGroups              │
             │          + modifiers                 │
             │                                       │
             │          variants_count              │
             │          min_price                    │
             │          media_count                 │
             │          modifier_groups_count       │
             │          outlets_count               │
             │                  │                    │
             └──────────────────┼────────────────────┘
                                ▼
                     ProductDetailResource
                                │
             ┌──────────────────┼──────────────────┐
             │                  │                  │
             ▼                  ▼                  ▼
          Product            summary          Child Resources
          fields                                 │
             │                         ┌──────────┼──────────┐
             │                         │          │          │
             │                     variants     media   modifier_groups
             │
             └───────────────────────────────────────────────┐
                                                             │
                                                             ▼
                                                    Frontend Product Detail
                                                             │
                                  ┌──────────────────────────┼─────────────────┐
                                  │                          │                 │
                               Header                    Navigation         Content
                                  │                          │                 │
                                  │                 counts from summary       │
                                  │                                            │
                                  │                                      Outlet tab
                                  │                                            │
                                  │                                            ▼
                                  │                               GET product/{id}/outlets
                                  │
                                  ▼
                            summary.price
```

---

# 32. Final Implementation Principle

> **Product Detail Resource harus menyediakan data yang memang menjadi kebutuhan presentation layer, tetapi tidak mengambil alih seluruh child-resource management.**

Dengan prinsip tersebut:

```text
Product Detail API
= product identity
+ product summary
+ eagerly required child data
```

sedangkan:

```text
Product Outlet API
= outlet assignment management
```

Tetap dipisahkan.

Perubahan ini ditujukan untuk membuat UI Product Detail dapat membaca data secara langsung dari contract API, tanpa memindahkan business calculation ke React dan tanpa memperbesar payload dengan seluruh Outlet Assignment.
