# PRD-STORAGE-MODULE.md

## 1. Tujuan

Membangun `Storage` module sebagai shared infrastructure/business capability pada codebase JualAntar sehingga seluruh fitur yang membutuhkan file/object storage di masa depan dapat menggunakan satu abstraction yang konsisten, aman, dan mudah diuji.

Target penggunaan awal dan berikutnya:

- Dokumen merchant / KYC
- Dokumen driver
- Logo merchant
- Foto outlet
- Foto produk/katalog
- Lampiran order/delivery
- Bukti pembayaran atau bukti serah terima
- File administratif dan dokumen internal

Storage module **tidak memiliki pengetahuan tentang Merchant, Driver, Payout, Catalog, atau domain bisnis lain**. Domain hanya mengetahui contract storage.

---

## 2. Hasil Analisis Codebase

Repository: `Rumahkodingku/jualantar-api`

Temuan yang menjadi dasar implementasi:

- Project adalah Laravel 13 + PHP 8.3 dan API-only. Tidak ada frontend pada repository ini.
- Authentication menggunakan Sanctum dan database PostgreSQL.
- Arsitektur repository sudah menggunakan modular monolith di bawah `Modules/*`.
- Modul `BankDirectory` sudah menjadi contoh struktur module dengan `Application`, `Contracts`, `Database`, `Domain`, `Http`, `Infrastructure`, `Routes`, dan tests.
- `AppServiceProvider` saat ini dipakai untuk binding infrastructure/shared services.
- `config/filesystems.php` sudah memiliki disk `local`, `public`, dan `s3`.
- Belum ditemukan penggunaan storage/object upload yang menjadi abstraction bersama di seluruh codebase.
- Composer saat ini belum memiliki dependency object-storage tambahan; karena R2 kompatibel dengan S3, integrasi sebaiknya memakai Flysystem/Laravel filesystem abstraction dan konfigurasi S3-compatible terlebih dahulu.
- Project memiliki aturan arsitektur dan coding yang ketat melalui `AGENTS.md`, termasuk penggunaan `Result`, API response envelope, Eloquent Resources, serta feature/architecture tests.

Referensi repository yang dianalisis:

- `AGENTS.md`
- `composer.json`
- `bootstrap/app.php`
- `config/filesystems.php`
- struktur `Modules/BankDirectory`

---

## 3. Keputusan Arsitektur

### 3.1 Storage adalah module tersendiri

Buat:

```text
Modules/
└── Storage/
```

Storage dianggap sebagai **shared technical/business capability**, bukan bagian dari Merchant atau Driver.

Dependency rule:

```text
Merchant ─────┐
Driver ───────┤
Catalog ──────┤──> Storage Contracts
Order ────────┤
Payout ───────┘

Storage
└── Infrastructure -> Laravel Filesystem / S3-compatible / R2
```

Storage tidak boleh mengimpor model/domain dari Merchant, Driver, Catalog, atau module lain.

### 3.2 Gunakan abstraction, bukan `Storage::disk()` di module bisnis

Module bisnis **tidak boleh** melakukan:

```php
Storage::disk('s3')->put(...);
Storage::disk('s3')->temporaryUrl(...);
```

langsung.

Module bisnis harus bergantung pada contract, misalnya:

```php
use Modules\Storage\Contracts\ObjectStorage;

$object = $storage->put(...);
$url = $storage->temporaryUrl(...);
```

Tujuannya agar provider/object storage dapat diganti tanpa mengubah domain consumer.

### 3.3 Gunakan object key, bukan public URL

Database/domain entity hanya menyimpan identifier seperti:

```text
merchants/{merchant_id}/documents/{document_id}
```

Bukan URL permanen.

URL akses dibuat saat diperlukan melalui temporary/presigned URL.

### 3.4 R2/S3-compatible menjadi target production

Cloudflare R2 menyediakan S3-compatible API, sehingga Laravel filesystem abstraction dapat digunakan tanpa membuat client R2 khusus. R2 juga mendukung presigned `GET`, `PUT`, `HEAD`, dan `DELETE`; presigned URL harus diperlakukan sebagai bearer token dan untuk data sensitif sebaiknya berumur pendek.

Laravel 13 mendukung filesystem abstraction, temporary URL, temporary upload URL, dan filesystem S3-compatible.

---

## 4. Scope MVP Storage Module

MVP **tidak** perlu membuat media library besar atau asset-management system.

Storage module cukup menyediakan:

1. Put object
2. Put uploaded file
3. Get object metadata
4. Check existence
5. Delete object
6. Generate temporary download URL
7. Generate temporary upload URL
8. Resolve storage disk/provider
9. Testable fake/in-memory adapter untuk automated tests

Belum perlu:

- Image transformation
- Video transcoding
- CDN management
- Multipart upload orchestration
- Malware scanning engine sendiri
- Media collections
- Asset tagging
- File version history
- Background lifecycle engine

Fitur-fitur tersebut dapat ditambahkan melalui contract/adapter tanpa mengubah consumer.

---

## 5. Struktur Module yang Diusulkan

```text
Modules/Storage/
├── Application/
│   └── Services/
│       └── ObjectStorageService.php
│
├── Contracts/
│   ├── ObjectStorage.php
│   └── DataTransferObjects/
│       ├── StoredObject.php
│       ├── TemporaryUpload.php
│       └── TemporaryDownload.php
│
├── Domain/
│   ├── Enums/
│   │   └── StorageVisibility.php
│   └── Exceptions/
│       └── StorageException.php
│
├── Infrastructure/
│   └── Filesystem/
│       ├── LaravelFilesystemObjectStorage.php
│       └── StorageDiskResolver.php
│
├── StorageServiceProvider.php
│
└── Tests/
    ├── Feature/
    └── Unit/
```

### Catatan struktur

Struktur di atas mengikuti pola modular yang sudah terlihat pada `BankDirectory`, tetapi **tidak membuat HTTP layer terlebih dahulu**.

Storage MVP sebaiknya menjadi infrastructure capability yang dipanggil oleh module lain.

Tidak perlu:

```text
Modules/Storage/Http
Modules/Storage/Routes
Modules/Storage/Http/Controllers
```

pada tahap awal.

Endpoint upload/download milik domain consumer, misalnya Merchant atau Driver, bukan milik generic Storage API.

---

## 6. Contract Utama

Gunakan satu contract utama:

```php
interface ObjectStorage
{
    public function put(
        string $path,
        string $contents,
        string $contentType,
    ): StoredObject;

    public function putFile(
        string $path,
        UploadedFile $file,
    ): StoredObject;

    public function exists(string $path): bool;

    public function metadata(string $path): StoredObject;

    public function delete(string $path): void;

    public function temporaryUrl(
        string $path,
        DateTimeInterface $expiresAt,
        array $options = [],
    ): string;

    public function temporaryUploadUrl(
        string $path,
        DateTimeInterface $expiresAt,
        string $contentType,
        array $options = [],
    ): TemporaryUpload;
}
```

Contract harus stabil dan provider-agnostic.

Jangan masukkan konsep spesifik R2 ke dalam contract seperti:

```php
R2Bucket
R2Object
CloudflareToken
```

---

## 7. DTO

### StoredObject

Minimal:

```text
path
disk
size
mime_type
etag/checksum (nullable)
```

### TemporaryUpload

Minimal:

```text
url
headers
path
expires_at
```

### TemporaryDownload

Untuk download cukup contract mengembalikan URL dan expiration, atau sementara menggunakan `string` untuk menjaga MVP tetap sederhana.

Disarankan eventual DTO:

```text
url
path
expires_at
```

---

## 8. Storage Disk Strategy

Pertahankan konsep Laravel filesystem sebagai adapter.

### Local/private

Untuk local development:

```env
FILESYSTEM_DISK=local
```

### Public

Disk `public` hanya untuk data yang memang aman bersifat public, misalnya asset publik tertentu.

Jangan menggunakan public disk untuk:

- KTP
- NPWP
- NIB
- selfie
- dokumen verifikasi
- dokumen driver
- bukti transaksi sensitif

### Production private object storage

Tambahkan disk S3-compatible khusus private, misalnya:

```php
'private' => [
    'driver' => 's3',
    'key' => env('STORAGE_ACCESS_KEY_ID'),
    'secret' => env('STORAGE_SECRET_ACCESS_KEY'),
    'region' => env('STORAGE_REGION', 'auto'),
    'bucket' => env('STORAGE_BUCKET'),
    'endpoint' => env('STORAGE_ENDPOINT'),
    'use_path_style_endpoint' => false,
    'throw' => true,
],
```

Nama env dibuat provider-neutral agar kode aplikasi tidak terikat Cloudflare:

```env
STORAGE_DISK=private
STORAGE_ACCESS_KEY_ID=
STORAGE_SECRET_ACCESS_KEY=
STORAGE_REGION=auto
STORAGE_BUCKET=
STORAGE_ENDPOINT=
```

Untuk R2, endpoint diarahkan ke endpoint S3-compatible R2.

---

## 9. Disk Resolution

Jangan hard-code:

```php
Storage::disk('private')
```

di seluruh module.

Sediakan resolver:

```php
final class StorageDiskResolver
{
    public function resolve(): FilesystemAdapter
    {
        return Storage::disk(
            config('storage.default_disk')
        );
    }
}
```

Konfigurasi module/application dapat menentukan disk default.

Contoh:

```php
'storage' => [
    'default_disk' => env('STORAGE_DISK', 'local'),
]
```

Dengan demikian:

```text
Development -> local
Staging     -> R2/S3 private bucket
Production  -> R2/S3 private bucket
```

tanpa perubahan pada domain code.

---

## 10. Path Convention

Object key harus dibuat terstruktur dan tidak bergantung pada original filename.

Gunakan format:

```text
{domain}/{owner_uuid}/{resource}/{file_uuid}
```

Contoh merchant:

```text
merchants/
  019.../
    documents/
      019...
```

Contoh driver:

```text
drivers/
  019.../
    documents/
      019...
```

Contoh katalog:

```text
catalog/
  products/
    019.../
      images/
        019...
```

### Aturan

- Gunakan UUID/random identifier.
- Jangan gunakan original filename sebagai object key utama.
- Original filename hanya metadata.
- Hindari menyimpan path yang berisi data pribadi sebagai nama file.
- Jangan menaruh secret/token di object key.
- Konsumen module bertanggung jawab menentukan semantic namespace; Storage hanya menyimpan key.

---

## 11. Public vs Private Object

Tambahkan enum:

```php
enum StorageVisibility: string
{
    case Public = 'public';
    case Private = 'private';
}
```

Namun MVP dapat menetapkan `private` sebagai default.

Default harus:

```text
private
```

Data sensitif tidak boleh menjadi public karena kesalahan konfigurasi.

---

## 12. Upload Flow yang Direkomendasikan

Untuk file sensitif atau ukuran besar, gunakan direct-to-object-storage upload.

Flow:

```text
Client
  |
  | 1. Request upload
  v
Domain API
  |
  | 2. Authenticate + authorize
  | 3. Validate document type
  | 4. Generate object key
  | 5. Generate temporary PUT URL
  v
Client
  |
  | 6. PUT file directly
  v
Private Object Storage
  |
  | 7. Upload complete
  v
Client
  |
  | 8. Confirm upload
  v
Domain API
  |
  | 9. Persist metadata
  v
Database
```

Poin penting:

Storage module hanya menangani storage operation.

Authorization tetap berada di domain consumer.

Contoh:

```text
Merchant policy
    -> boleh upload dokumen merchant A?

Storage module
    -> bagaimana membuat signed upload URL?
```

Storage **tidak** menentukan apakah user berhak mengupload dokumen merchant tertentu.

---

## 13. Download/View Flow

```text
Client
  |
  | GET /merchant/documents/{id}/url
  v
Merchant API
  |
  | authorize document ownership/access
  v
Storage Contract
  |
  | temporaryUrl()
  v
Private Object Storage
```

API tidak mengembalikan URL permanen.

TTL rekomendasi awal:

- sensitive document: 1–5 menit
- internal preview: 5–15 menit
- non-sensitive temporary assets: dapat lebih lama

Durasi harus configurable.

---

## 14. Security Requirements

### Wajib

- Private bucket untuk data sensitif.
- Tidak ada permanent public URL untuk dokumen sensitif.
- Temporary/presigned URL.
- Short expiration.
- HTTPS/TLS.
- Credentials hanya di environment/secret manager.
- Object key random/UUID.
- Validasi MIME type.
- Validasi ukuran.
- Validasi extension.
- Authorization sebelum generate URL.
- Jangan percaya `merchant_id` atau owner ID dari client tanpa authorization.
- Cegah IDOR/BOLA pada endpoint domain.
- Logging jangan mencetak secret atau signed URL lengkap.

### File validation

Domain consumer wajib memvalidasi:

```text
allowed mime types
max file size
expected file category
```

Storage layer tidak boleh menjadi satu-satunya security boundary.

### Future

Tambahkan malware scanning sebelum status dokumen menjadi `approved`/`usable`.

---

## 15. Database

MVP Storage module **tidak harus memiliki tabel database sendiri**.

Alasan:

Metadata file merupakan bagian dari domain pemilik file.

Contoh:

```text
Merchant
└── merchant_documents
    ├── merchant_id
    ├── document_type
    ├── file_name
    ├── object_key
    ├── mime_type
    ├── file_size
    └── verification metadata
```

Storage module tidak perlu membuat:

```text
storage_files
media
attachments
```

terlebih dahulu.

Hindari generic file table sampai kebutuhan benar-benar muncul.

### Prinsip

```text
Storage = physical object handling

Domain = business meaning + metadata
```

Contoh:

`merchant_documents` tahu bahwa file tersebut adalah `KTP`.

Storage hanya tahu:

```text
key = merchants/{id}/documents/{uuid}
content-type = image/jpeg
size = 123456
```

---

## 16. Binding Dependency Injection

`StorageServiceProvider` harus membind:

```php
$this->app->bind(
    ObjectStorage::class,
    LaravelFilesystemObjectStorage::class,
);
```

Jika nantinya ada multiple adapters:

```text
ObjectStorage
    |
    ├── LaravelFilesystemObjectStorage
    ├── FakeObjectStorage
    └── FutureAlternativeProvider
```

Consumer hanya bergantung pada:

```php
ObjectStorage
```

---

## 17. Error Handling

Infrastructure exception harus diterjemahkan menjadi failure yang dapat dikendalikan application service.

Jangan membiarkan error vendor/provider bocor ke HTTP response.

Contoh concern:

```text
S3 error
R2 error
Flysystem error
filesystem permission error
network timeout
```

harus dipetakan ke exception/result yang sesuai.

Jangan expose:

- access key
- endpoint credential
- bucket internals
- raw provider response
- signed URL secret

---

## 18. Testing Strategy

### Unit test

`LaravelFilesystemObjectStorageTest`

Test:

- put
- putFile
- exists
- metadata
- delete
- temporaryUrl
- temporaryUploadUrl
- content type
- path preservation

### Fake adapter

Buat fake/in-memory implementation untuk business-module tests:

```php
FakeObjectStorage
```

Test consumer tanpa benar-benar upload ke S3/R2.

Contoh:

```text
MerchantDocumentService
    -> FakeObjectStorage
```

### Feature tests

Uji endpoint consumer:

```text
authorization succeeds
unauthorized access fails
temporary URL returned
metadata persisted
```

### Architecture tests

Tambahkan rule bahwa:

```text
Storage must not depend on Merchant
Storage must not depend on Driver
Storage must not depend on Catalog
Storage must not depend on HTTP Controllers
```

Dan consumer boleh depend pada:

```text
Modules\Storage\Contracts
```

tetapi bukan:

```text
Modules\Storage\Infrastructure
```

---

## 19. Laravel Filesystem Integration

Gunakan Laravel Filesystem sebagai integration boundary karena repository sudah memiliki konfigurasi `filesystems.php`.

Jangan memperkenalkan custom low-level R2 SDK pada MVP.

Cloudflare R2 menggunakan S3-compatible API, sehingga adapter dapat memanfaatkan filesystem S3-compatible Laravel/Flysystem.

Ketika R2 menjadi production provider:

```text
Domain
  ↓
Storage Contract
  ↓
Laravel Filesystem Adapter
  ↓
S3-compatible endpoint
  ↓
Cloudflare R2
```

---

## 20. Implementasi Bertahap

### Phase 1 — Foundation

- [ ] Buat `Modules/Storage`
- [ ] Buat `StorageServiceProvider`
- [ ] Pastikan module provider ter-load sesuai mekanisme module yang sudah ada.
- [ ] Buat `ObjectStorage` contract.
- [ ] Buat DTO `StoredObject`.
- [ ] Buat DTO `TemporaryUpload`.
- [ ] Buat provider-neutral storage config.
- [ ] Tambahkan disk private S3-compatible.
- [ ] Buat `LaravelFilesystemObjectStorage`.
- [ ] Bind contract melalui service container.

### Phase 2 — Core operations

- [ ] Implement `put`
- [ ] Implement `putFile`
- [ ] Implement `exists`
- [ ] Implement `metadata`
- [ ] Implement `delete`
- [ ] Implement `temporaryUrl`
- [ ] Implement `temporaryUploadUrl`

### Phase 3 — Testing

- [ ] Unit test adapter.
- [ ] Fake storage adapter.
- [ ] Architecture tests.
- [ ] Failure/error tests.
- [ ] Test private/local disk.
- [ ] Test temporary URL expiration behavior.

### Phase 4 — Integration readiness

- [ ] Dokumentasikan contract.
- [ ] Dokumentasikan naming/object-key convention.
- [ ] Dokumentasikan upload/download flow.
- [ ] Dokumentasikan security rules.
- [ ] Tambahkan env example.
- [ ] Pastikan no business module memakai `Storage::disk()` langsung.

### Phase 5 — First consumer

Setelah module stabil, integrasikan ke `Merchant`:

```text
merchant_documents
        ↓
Storage\Contracts\ObjectStorage
        ↓
private object storage
```

Jangan mengembangkan fitur Merchant document upload bersamaan dengan redesign Storage contract. Stabilkan Storage dahulu.

---

## 21. Acceptance Criteria

Storage module dianggap selesai apabila:

### Architecture

- [ ] `Modules/Storage` berdiri sebagai module mandiri.
- [ ] Consumer hanya mengetahui `Contracts`.
- [ ] Storage tidak mengetahui domain consumer.
- [ ] Tidak ada direct `Storage::disk()` di domain consumer.

### Functionality

- [ ] File dapat di-upload.
- [ ] Metadata dapat dibaca.
- [ ] Object existence dapat diperiksa.
- [ ] File dapat dihapus.
- [ ] Temporary download URL dapat dibuat.
- [ ] Temporary upload URL dapat dibuat.

### Security

- [ ] Private storage menjadi default untuk sensitive files.
- [ ] Permanent public URL tidak digunakan untuk sensitive objects.
- [ ] Signed URL memiliki expiration.
- [ ] Credential storage tidak berada di source code.
- [ ] Object key tidak menggunakan original filename.
- [ ] Authorization tetap dilakukan oleh domain consumer.

### Testing

- [ ] Unit tests lulus.
- [ ] Architecture tests lulus.
- [ ] Consumer dapat memakai fake storage.
- [ ] Failure cases memiliki coverage.

---

## 22. Hal yang Tidak Dilakukan pada Task Ini

Task Storage Foundation **tidak** mencakup:

- Merchant document endpoint
- Driver document endpoint
- KYC implementation
- R2 bucket provisioning otomatis
- UI upload
- Catalog image management
- Image processing
- CDN
- Virus scanning
- Generic media library
- File metadata table global

Semua hal tersebut merupakan consumer/future capability.

---

## 23. Proposed Final Architecture

```text
Modules/
├── BankDirectory/
├── Merchant/
├── Driver/
├── Payout/
├── Service/
├── Storage/
│   ├── Application/
│   │   └── Services/
│   │       └── ObjectStorageService.php
│   │
│   ├── Contracts/
│   │   ├── ObjectStorage.php
│   │   └── DataTransferObjects/
│   │       ├── StoredObject.php
│   │       └── TemporaryUpload.php
│   │
│   ├── Domain/
│   │   ├── Enums/
│   │   └── Exceptions/
│   │
│   ├── Infrastructure/
│   │   └── Filesystem/
│   │       ├── LaravelFilesystemObjectStorage.php
│   │       └── StorageDiskResolver.php
│   │
│   ├── StorageServiceProvider.php
│   └── Tests/
└── ...
```

Dependency:

```text
                 ┌───────────────┐
                 │    Merchant   │
                 └───────┬───────┘
                         │
                 ┌───────▼───────┐
                 │    Driver     │
                 └───────┬───────┘
                         │
                 ┌───────▼───────┐
                 │     Catalog   │
                 └───────┬───────┘
                         │
                         ▼
                ┌─────────────────┐
                │ Storage Contract │
                └────────┬────────┘
                         │
                         ▼
                ┌─────────────────┐
                │ Storage Adapter  │
                │ Laravel/Flysystem│
                └────────┬────────┘
                         │
                         ▼
                 ┌───────────────┐
                 │ R2 / S3 / Local│
                 └───────────────┘
```

---

## 24. Rekomendasi Final

Untuk codebase JualAntar saat ini, pilihan paling tepat adalah:

**Storage module tanpa database + contract-first + Laravel Filesystem/Flysystem + private S3-compatible disk + presigned URL + fake adapter untuk testing.**

Jangan membuat generic `storage_files` table dan jangan membuat generic HTTP upload API pada tahap pertama.

Dengan desain ini, saat `MerchantDocument`, `DriverDocument`, `ProductImage`, `OrderAttachment`, dan fitur lain muncul, implementasinya cukup menjadi consumer dari:

```php
Modules\Storage\Contracts\ObjectStorage
```

tanpa mengulang integrasi R2/S3, URL signing, credential handling, atau storage abstraction.
