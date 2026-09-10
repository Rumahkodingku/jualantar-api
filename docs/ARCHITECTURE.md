# ARCHITECTURE.md

## Blueprint Standar: Laravel Modular Monolith

|                   |                                                                                               |
| ----------------- | --------------------------------------------------------------------------------------------- |
| **Status**        | Living document — wajib direview tiap ada modul baru yang lahir                               |
| **Berlaku untuk** | Semua project Laravel baru maupun existing yang bermigrasi ke Modular Monolith                |
| **Studi kasus**   | JualAntar API (lihat Bab 7) — dipakai **hanya sebagai contoh penerapan**, bukan sumber aturan |
| **Audiens**       | Tech Lead, Software Architect, Backend Engineer                                               |

> **Cara memakai dokumen ini:** Bab 1–6 adalah **aturan absolut** yang berlaku untuk _project Laravel apa pun_, kapan pun dibuat, siapa pun yang membuat. Bab 7 adalah _contoh penerapan_ pada satu codebase nyata. Jangan pernah membalik urutan itu — jika suatu saat Bab 7 terasa "bertentangan" dengan Bab 1–6, maka Bab 7 yang salah dan harus direvisi, bukan sebaliknya.

---

## Daftar Isi

1. [Philosophy & Goals](#1-philosophy--goals)
2. [Directory Structure Standard](#2-directory-structure-standard)
3. [Bounded Context Guide](#3-bounded-context-guide)
4. [Inter-Module Communication](#4-inter-module-communication)
5. [Shared Kernel / Core](#5-shared-kernel--core)
6. [Database Architecture](#6-database-architecture)
7. [Migration Guide — Studi Kasus JualAntar API](#7-migration-guide--studi-kasus-jualantar-api)
8. [Testing Strategy](#8-testing-strategy)
9. [Module Governance & Lifecycle](#9-module-governance--lifecycle)
10. [Anti-Pattern Checklist](#10-anti-pattern-checklist)
11. [Appendix](#11-appendix)

---

## 1. Philosophy & Goals

### 1.1 Mengapa Modular Monolith?

Ada tiga jalan umum yang ditawarkan industri, dan ketiganya punya jebakan:

| Pendekatan                                          | Masalah                                                                                                                                                                                                                                                                             |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Monolith biasa** (`app/Http`, `app/Models` datar) | Cepat di awal, tapi tanpa batas eksplisit, semua orang menambah kode ke tempat yang sama → _Big Ball of Mud_ dalam 12–18 bulan.                                                                                                                                                     |
| **Microservices sejak hari pertama**                | Menambahkan biaya operasional (network, observability, distributed transaction) sebelum domain bisnis benar-benar dipahami. Kebanyakan tim membayar kompleksitas ini padahal belum butuh skalanya.                                                                                  |
| **Modular Monolith**                                | Satu unit deploy (murah secara operasional), tapi kode dipaksa punya **batas modul yang eksplisit dan dapat diuji** sejak awal. Ketika suatu modul benar-benar butuh diskalakan/dipisah sendiri, ia sudah punya kontrak yang jelas → ekstraksi menjadi _refactor_, bukan _rewrite_. |

Modular Monolith bukan tujuan akhir — ia adalah **optionality**. Kita membayar sedikit disiplin di awal supaya kelak punya pilihan (extract to service atau tetap monolith) tanpa dipaksa oleh utang teknis.

### 1.2 Prinsip Utama (Non-Negotiable)

1. **High Cohesion** — kode yang berubah untuk alasan bisnis yang sama harus hidup di tempat yang sama (satu modul).
2. **Loose Coupling** — modul A boleh _tahu_ modul B ada, tapi tidak boleh tahu _bagaimana_ modul B bekerja di dalam. Komunikasi hanya lewat kontrak publik (Bab 4).
3. **Explicit Public API per Module** — setiap modul punya "pintu depan" (`Contracts/`) yang jelas. Apa pun di luar itu dianggap privat/internal, meski secara teknis PHP mengizinkan import lintas namespace.
4. **Screaming Architecture** — membuka folder `app/Modules` harus langsung "berteriak" tentang bisnis apa yang dijalankan aplikasi ini (`Ordering`, `Logistics`, `Payment`), bukan tentang framework yang dipakai (`Controllers`, `Models`).
5. **Independently Testable** — setiap modul harus bisa diuji (unit + feature) tanpa harus menjalankan seluruh aplikasi lintas modul lain, kecuali lewat kontrak/fake yang disediakan modul tersebut.
6. **Dependency Rule searah** — dependensi hanya boleh mengalir dari modul yang lebih spesifik ke modul yang lebih generik (lihat klasifikasi Core/Supporting/Generic di Bab 3), tidak pernah melingkar (circular).
7. **Database per Module secara logis** — satu database fisik boleh dipakai bersama, tapi kepemilikan tabel per modul harus eksplisit dan tidak saling menembus lewat _foreign key_ fisik lintas modul (Bab 6).
8. **Evolvable by Design** — setiap modul ditulis seolah-olah suatu hari akan diekstraksi menjadi service terpisah, walau hari itu mungkin tidak pernah datang.

### 1.3 Non-Goals

Dokumen ini **tidak** mengharuskan:

- Microservices atau multi-database dari awal.
- Setiap modul punya bounded context yang "sempurna" secara akademis DDD sejak hari pertama — boundary boleh direvisi seiring pemahaman domain bertambah (itulah gunanya `Contracts/` sebagai lapisan pelindung refactor).
- CQRS penuh atau Event Sourcing. Pola tersebut boleh dipakai di modul tertentu yang butuh, tapi bukan syarat wajib arsitektur ini.

---

## 2. Directory Structure Standard

### 2.1 Struktur Root Project

```
app/
├── Modules/                     # Semua kode bisnis hidup di sini
│   ├── IdentityAccess/
│   ├── Geography/
│   ├── BankDirectory/
│   ├── Catalog/
│   ├── Ordering/
│   ├── Logistics/
│   └── Payment/
│
├── Shared/                      # Shared Kernel — lihat Bab 5
│   ├── Http/
│   ├── Result/
│   ├── Exceptions/
│   ├── Observability/
│   ├── Middleware/
│   └── Support/
│
├── Providers/
│   └── AppServiceProvider.php   # Hanya bootstrap framework-level, TIDAK boleh berisi wiring bisnis
│
└── Console/
```

**Keputusan desain:** modul diletakkan di `app/Modules/*` (namespace `App\Modules\*`), **bukan** di root `Modules/` atau `src/Modules/` terpisah. Alasannya:

- Tetap memakai autoload PSR-4 bawaan Laravel (`"App\\": "app/"`), tanpa entri composer tambahan.
- Tim baru yang paham konvensi Laravel tetap langsung familiar; hanya level foldernya yang berbeda.
- Menghindari dependency package pihak ketiga (mis. `nwidart/laravel-modules`) yang menambah _magic_ (auto-discovery, config caching) yang justru menyembunyikan batas modul yang seharusnya eksplisit.

> Jika tim memang ingin modul benar-benar independen dari framework Laravel (misal untuk disiapkan jadi package composer privat), gunakan alternatif `packages/{module}` dengan `composer.json` sendiri per modul dan path repository. Itu valid, tapi merupakan level maturity yang lebih tinggi — jangan mulai dari sana.

### 2.2 Anatomi Satu Modul

Setiap modul **wajib** mengikuti skeleton yang sama, agar navigasi antar modul terasa konsisten:

```
app/Modules/{ModuleName}/
├── module.json                       # Manifest: nama, deskripsi, dependency ke modul lain
├── {ModuleName}ServiceProvider.php   # Satu-satunya titik registrasi modul ini ke framework
│
├── Contracts/                        # 🚪 PUBLIC API MODUL — satu-satunya yang boleh diimpor modul lain
│   ├── {ModuleName}Facade.php        # Interface use-case tingkat tinggi
│   └── DataTransferObjects/
│       └── {Something}Data.php       # DTO, bukan Eloquent Model, untuk lintas modul
│
├── Domain/                           # Inti bisnis, tanpa dependency ke Laravel sebisa mungkin
│   ├── Models/                       # Eloquent Model — PRIVAT untuk modul ini
│   ├── ValueObjects/
│   ├── Enums/
│   ├── Events/                       # Domain Event milik modul ini
│   └── Exceptions/                   # extends App\Shared\Exceptions\ApiException
│
├── Application/                      # Orkestrasi use-case
│   ├── Actions/                      # Satu class = satu use-case (invokable)
│   ├── Services/
│   └── Listeners/                    # Listener untuk event dari modul lain
│
├── Infrastructure/                   # Implementasi konkret dari Contracts/interfaces internal
│   ├── Repositories/
│   └── ExternalServices/
│
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/                   # Middleware spesifik modul (jarang, biasanya di Shared)
│
├── Database/
│   ├── Migrations/
│   ├── Factories/
│   └── Seeders/
│
├── Routes/
│   └── api.php                       # Didaftarkan oleh {ModuleName}ServiceProvider
│
└── Tests/
    ├── Feature/
    └── Unit/
```

**Aturan folder:**

- **`Contracts/`** adalah satu-satunya folder yang boleh diketahui/diimpor oleh modul lain. Semua isi `Domain/`, `Application/`, `Infrastructure/` dianggap `@internal` meskipun `public` secara visibility PHP.
- **`Domain/Models`** berisi Eloquent Model. Model ini **tidak boleh** diekspos langsung ke modul lain — yang diekspos adalah DTO di `Contracts/DataTransferObjects`.
- Setiap modul mendaftarkan migration, route, dan factory-nya sendiri lewat `{ModuleName}ServiceProvider`, didaftarkan secara eksplisit di `bootstrap/providers.php`. Tidak ada auto-discovery ajaib.

```php
// app/Modules/BankDirectory/BankDirectoryServiceProvider.php
namespace App\Modules\BankDirectory;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\BankDirectory\Infrastructure\Repositories\EloquentBankLookup;
use Illuminate\Support\ServiceProvider;

final class BankDirectoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BankLookup::class, EloquentBankLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->loadFactoriesFrom(__DIR__.'/Database/Factories');
        }
    }
}
```

---

## 3. Bounded Context Guide

### 3.1 Cara Menemukan Batas Modul

Jangan mulai dari tabel database atau diagram ER. Mulai dari **kapabilitas bisnis**:

1. **Kumpulkan kosakata bisnis** (ubiquitous language) dari stakeholder: kata benda dan kata kerja apa yang mereka pakai sehari-hari? ("pesanan", "kurir", "rekening tujuan", "wilayah pengiriman").
2. **Kelompokkan kosakata** yang punya makna konsisten dan berubah bersama menjadi satu modul. Jika kata yang sama punya arti berbeda di dua tim/proses (mis. "Alamat" versi checkout vs "Alamat" versi KYC), itu sinyal dua bounded context berbeda meski nama sama.
3. **Uji dengan pertanyaan "siapa pemilik data ini?"** — jika dua tim/fitur mengklaim kepemilikan yang sama atas satu tabel, tabel itu perlu dipecah atau salah satu pihak harus mengaksesnya lewat kontrak, bukan tabel langsung.

### 3.2 Klasifikasi Strategis (wajib dilakukan di awal project)

Setiap modul dikategorikan menjadi salah satu dari tiga jenis, karena ini menentukan **berapa banyak investasi rekayasa** yang pantas dikeluarkan untuknya:

| Jenis                 | Definisi                                                                    | Contoh Perlakuan                                                                                                           |
| --------------------- | --------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| **Core Domain**       | Alasan bisnis ini ada / pembeda kompetitif.                                 | Investasi desain paling tinggi, test coverage paling ketat, tim terbaik ditaruh di sini.                                   |
| **Supporting Domain** | Perlu ada, mendukung Core, tapi bukan pembeda.                              | Cukup baik, tidak perlu over-engineered.                                                                                   |
| **Generic Domain**    | Solved problem, tersedia di mana-mana (auth, data wilayah, direktori bank). | Prioritaskan pakai _package_ pihak ketiga/layanan eksternal bila memungkinkan; jika ditulis sendiri, jaga tetap sederhana. |

### 3.3 Kriteria "Layak Jadi Modul Sendiri"

Sebuah kapabilitas layak jadi modul terpisah jika **semua** ini benar:

- Ia punya siklus hidup data sendiri (create/update/delete tidak bergantung transaksi modul lain).
- Ia bisa dijelaskan dalam satu kalimat tanpa kata "dan" (jika perlu "dan", mungkin itu dua modul).
- Perubahan padanya jarang memaksa perubahan pada modul lain di hari yang sama (low change coupling).

Jika sebuah "modul" hanya berisi satu Model dan tidak pernah dipanggil modul lain, ia **boleh** tetap sesederhana itu — jangan memaksakan semua sub-layer (`Domain/Application/Infrastructure`) untuk modul yang trivial. Skeleton di Bab 2 adalah _maksimum_ yang tersedia, bukan minimum yang wajib diisi semua.

---

## 4. Inter-Module Communication

Ini adalah bab dengan aturan paling ketat, karena pelanggarannya adalah cara paling umum sebuah Modular Monolith diam-diam berubah kembali menjadi Big Ball of Mud.

### 4.1 Aturan Mutlak

> **Tidak ada relasi Eloquent lintas modul. Titik.**

Artinya dilarang:

```php
// ❌ DILARANG — Order (modul Ordering) punya relasi langsung ke Model modul lain
class Order extends Model
{
    public function bank(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\BankDirectory\Domain\Models\Bank::class);
    }
}
```

```php
// ❌ DILARANG — controller/service satu modul query Model modul lain secara langsung
$village = \App\Modules\Geography\Domain\Models\Village::find($id);
```

### 4.2 Tiga Kanal Komunikasi yang Diizinkan

**A. Contract Interface + DTO (synchronous, in-process)** — dipakai ketika modul pemanggil butuh jawaban _segera_ untuk melanjutkan use case-nya.

```php
// app/Modules/Geography/Contracts/GeographyLookup.php
namespace App\Modules\Geography\Contracts;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;

interface GeographyLookup
{
    public function villageExists(int $villageId): bool;

    public function addressLabel(int $villageId): AddressLabelData;
}
```

```php
// Dipakai dari modul lain (mis. Logistics), TANPA tahu implementasinya Eloquent atau bukan
final class ScheduleDelivery
{
    public function __construct(private readonly GeographyLookup $geography) {}

    public function __invoke(int $villageId): void
    {
        if (! $this->geography->villageExists($villageId)) {
            throw new InvalidDestinationException();
        }
        // ...
    }
}
```

**B. Domain Events (asynchronous / decoupled reaction)** — dipakai ketika modul pemanggil **tidak perlu** menunggu hasil, cukup memberi tahu "sesuatu telah terjadi".

```php
// Modul Payment memancarkan event, tanpa tahu siapa yang mendengarkan
event(new BankAccountVerified(bankAccountId: $account->id, ownerId: $account->owner_id));
```

```php
// Modul Notification mendaftarkan listener-nya sendiri di NotificationServiceProvider
Event::listen(BankAccountVerified::class, SendBankVerifiedNotification::class);
```

**C. Application Action sebagai pintu masuk** — controller modul A tidak boleh langsung memanggil Service _internal_ modul B; ia hanya boleh memanggil `Contracts/` milik modul B.

### 4.3 Aturan Arah Dependency

Dependency **hanya boleh mengalir** dari modul yang lebih spesifik (Core) ke yang lebih generik (Supporting → Generic), tidak pernah sebaliknya, dan tidak pernah melingkar:

```
Core Domain  ──depends on──▶  Supporting Domain  ──depends on──▶  Generic Domain
(Ordering,                     (Payment,                          (IdentityAccess,
 Logistics)                     Notification)                      Geography, BankDirectory)
```

Modul Generic **tidak boleh** mengenal/mengimpor apa pun dari modul Core atau Supporting. Jika terasa perlu, itu tanda modul tersebut salah klasifikasi.

### 4.4 Menegakkan Aturan secara Otomatis

Aturan di atas percuma jika hanya hidup di dokumen. Tegakkan dengan Pest Arch Test yang jalan di CI:

```php
// tests/Arch/ModuleBoundaryTest.php
$modules = ['IdentityAccess', 'Geography', 'BankDirectory', 'Catalog', 'Ordering', 'Logistics', 'Payment'];

foreach ($modules as $module) {
    arch("modul {$module}: hanya Contracts/ yang boleh dipakai modul lain")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");

    arch("modul {$module}: internal Application layer tidak bocor keluar")
        ->expect("App\\Modules\\{$module}\\Application")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");
}
```

---

## 5. Shared Kernel / Core

### 5.1 Apa yang Boleh Masuk Shared Kernel

Shared Kernel (`app/Shared/*`) hanya untuk kode yang **benar-benar generik lintas domain bisnis** — kode yang jika dihapus, tidak ada satu pun cerita bisnis yang rusak, hanya _plumbing_ teknis yang rusak:

- Response envelope (`ApiResponse`), RFC 9457 Problem Details (`ProblemDetails`, `ProblemDetailsFactory`).
- Result pattern (`Result`, `ResultError`) untuk error-as-value pada Application layer.
- Exception dasar (`ApiException` abstract) yang tiap modul turunkan untuk exception domain-nya sendiri.
- Middleware lintas request (request-id/tracing, request logging, rate limiting umum).
- Observability generik (exception reporter, metrics recorder) yang tidak tahu apa-apa tentang domain bisnis.
- Base test case, base factory helper, trait teknis (mis. `HasUuid`).

### 5.2 Apa yang **Tidak Boleh** Masuk Shared Kernel

- Model bisnis apa pun (`User`, `Order`, `Bank`, dsb.) — semua itu milik modulnya masing-masing.
- Enum atau Value Object yang punya makna bisnis spesifik (mis. `OrderStatus` adalah milik modul Ordering, bukan Shared).
- Helper "serba guna" yang sebenarnya hanya dipakai satu modul — itu tandanya salah taruh, bukan alasan untuk membuatnya "shared".

### 5.3 Kenapa Ini Penting

Shared Kernel adalah titik dengan **fan-in tertinggi** di seluruh codebase — semua modul bergantung padanya. Konsekuensinya:

1. Setiap perubahan pada Shared Kernel berpotensi memengaruhi _semua_ modul → wajib direview oleh lebih dari satu tim/tech lead, dan wajib dilindungi test yang sangat kuat (lihat `tests/Arch/ArchTest.php` yang mewajibkan `Result`, `ResultError`, `ProblemDetails` bersifat `final readonly` — itu bentuk proteksi yang benar).
2. Karena biaya perubahannya mahal, isinya harus dijaga **sekecil dan sestabil mungkin**. Kalau Shared Kernel mulai punya banyak method spesifik untuk satu-dua modul saja, itu sinyal ia sudah menjadi "God Module" (lihat Bab 10).

---

## 6. Database Architecture

### 6.1 Prinsip: Satu Database Fisik, Kepemilikan Logis per Modul

Modular Monolith tidak mengharuskan database terpisah per modul (itu baru wajib kalau sudah jadi microservices). Yang wajib adalah **isolasi logis** yang tegas:

- **PostgreSQL (direkomendasikan):** gunakan satu **schema per modul** dalam satu database fisik.

    ```sql
    CREATE SCHEMA identity_access;
    CREATE SCHEMA geography;
    CREATE SCHEMA bank_directory;
    CREATE SCHEMA ordering;
    CREATE SCHEMA logistics;
    CREATE SCHEMA payment;
    ```

    Migration tiap modul menulis ke schema-nya sendiri, misal `Schema::create('geography.provinces', ...)`. Ini memberi batas fisik yang kuat, memudahkan audit "siapa memiliki tabel apa", dan menjadi langkah pertama yang natural jika suatu hari modul tersebut perlu diekstrak ke database/service terpisah.

- **MySQL/SQLite (jika schema tidak praktis):** gunakan **prefix nama tabel** sesuai modul sebagai konvensi wajib, misal `geo_provinces`, `geo_regencies`, `pay_bank_accounts`, `ord_orders`. Ini bukan pengganti sempurna schema, tapi menjaga _keterbacaan kepemilikan_ di level nama tabel.

### 6.2 Larangan Foreign Key Fisik Lintas Modul

> **Di dalam satu modul:** foreign key constraint (`->constrained()`) boleh dan dianjurkan dipakai bebas — itu domain yang sama, konsistensi referensial memang harus dijaga database.
>
> **Lintas modul:** foreign key constraint **dilarang**. Simpan ID sebagai kolom biasa (`unsignedBigInteger`, tanpa `->constrained()`), dan validasi keberadaannya di **application layer** lewat Contract modul pemilik data (Bab 4).

```php
// ✅ Migration modul Logistics — menyimpan referensi ke Geography TANPA FK fisik
Schema::create('logistics.deliveries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('ordering.orders'); // ✅ dalam satu alur transaksi yang sama, boleh dipertimbangkan kasus per kasus
    $table->unsignedBigInteger('destination_village_id');          // ❌ TIDAK ->constrained() ke schema geography
    $table->timestamps();
});
```

Alasan: FK fisik lintas modul menciptakan _coupling_ pada level migration order, membuat modul tidak bisa lagi di-deploy/di-test/diekstrak secara independen, dan diam-diam membangun kembali "satu skema database raksasa" yang justru ingin kita hindari.

### 6.3 Data yang Dibutuhkan Bersama (Read Model / Proyeksi)

Jika satu modul sering butuh data gabungan dari modul lain untuk ditampilkan (bukan untuk transaksi), jangan JOIN lintas schema. Pilihannya:

1. Panggil Contract modul pemilik data secara sinkron dan gabungkan di Application layer (baik untuk kebutuhan ringan/real-time).
2. Bangun _read model_/proyeksi lokal yang di-_update_ lewat Domain Event dari modul pemilik (baik untuk kebutuhan laporan/listing berat, menghindari N+1 pemanggilan Contract).

---

## 7. Migration Guide — Studi Kasus JualAntar API

> Bab ini menerapkan Bab 1–6 di atas ke codebase **JualAntar API** sebagai contoh konkret. Perlu digarisbawahi: codebase ini **belum berupa "Big Ball of Mud"** — sebaliknya, fondasinya sudah rapi (Result pattern, RFC 9457 Problem Details, arch test, Request-ID tracing sudah ada sejak awal). Justru karena itu, **ini momen paling murah untuk menegakkan batas modul** — sebelum fitur inti bisnis (jual & antar barang) ditambahkan ke struktur `app/Http`, `app/Models` yang datar dan akan sulit dipecah nanti.

### 7.1 Kondisi Saat Ini

Struktur saat ini adalah struktur Laravel default (flat):

```
app/
├── Exceptions/        (ApiException, BadRequestException, ...)
├── Http/
│   ├── Controllers/   (BankController, UserController, MetricsController, ...)
│   ├── Middleware/    (RequestIdMiddleware, RequestLoggingMiddleware)
│   ├── Requests/      (StoreBankRequest, UpdateBankRequest)
│   └── Resources/     (BankResource, UserResource)
├── Models/             (Bank, User, Province, Regency, District, Village)
├── Providers/
├── Services/           (BankService)
└── Support/
    ├── Http/           (ApiResponse, ProblemDetails, ProblemDetailsFactory)
    ├── Observability/  (ExceptionReporter)
    └── Result/         (Result, ResultError)
```

Kapabilitas bisnis yang sudah teridentifikasi dari kode: **identitas pengguna (User + Sanctum)**, **data wilayah administratif Indonesia (Province → Regency → District → Village)**, dan **direktori bank** (untuk kebutuhan rekening/pembayaran). Nama produk "JualAntar" (jual + antar) mengisyaratkan roadmap ke depan berupa marketplace dengan pengiriman/logistik sendiri — Bab 7.4 menyiapkan tempatnya sejak sekarang agar tidak perlu dibongkar ulang nanti.

### 7.2 Klasifikasi Strategis untuk JualAntar

| Modul                      | Klasifikasi        | Alasan                                                                                       |
| -------------------------- | ------------------ | -------------------------------------------------------------------------------------------- |
| `IdentityAccess`           | Generic            | Autentikasi bukan pembeda kompetitif JualAntar.                                              |
| `Geography`                | Generic            | Data wilayah adalah reference data publik, tidak spesifik ke bisnis JualAntar.               |
| `BankDirectory`            | Generic/Supporting | Direktori bank adalah data referensi untuk kebutuhan rekening tujuan pembayaran.             |
| `Catalog` _(roadmap)_      | Supporting         | Daftar barang yang dijual — penting tapi bukan pembeda utama dibanding kompetitor.           |
| `Ordering` _(roadmap)_     | **Core**           | Proses "Jual" — transaksi antara penjual & pembeli adalah jantung bisnis.                    |
| `Logistics` _(roadmap)_    | **Core**           | Proses "Antar" — pencocokan & pengiriman adalah pembeda kompetitif utama sesuai nama produk. |
| `Payment` _(roadmap)_      | Supporting         | Menggunakan `BankDirectory` sebagai referensi, menangani pencatatan pembayaran/payout.       |
| `Notification` _(roadmap)_ | Supporting         | Bereaksi terhadap event dari modul lain.                                                     |

### 7.3 Peta Perpindahan File (Concrete Mapping)

| Path Saat Ini                                                  | Menjadi                                                                                                 | Catatan                                                                                                |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| `app/Models/User.php`                                          | `app/Modules/IdentityAccess/Domain/Models/User.php`                                                     |                                                                                                        |
| `app/Http/Controllers/UserController.php`                      | `app/Modules/IdentityAccess/Http/Controllers/UserController.php`                                        |                                                                                                        |
| `app/Http/Resources/UserResource.php`                          | `app/Modules/IdentityAccess/Http/Resources/UserResource.php`                                            |                                                                                                        |
| `database/migrations/..._create_users_table.php`               | `app/Modules/IdentityAccess/Database/Migrations/..._create_identity_access.users_table.php`             | Pertimbangkan schema Postgres `identity_access`                                                        |
| `app/Models/{Province,Regency,District,Village}.php`           | `app/Modules/Geography/Domain/Models/*.php`                                                             |                                                                                                        |
| `database/seeders/RegionSeeder.php`, `database/wilayah.sql`    | `app/Modules/Geography/Database/Seeders/RegionSeeder.php`                                               |                                                                                                        |
| _(baru)_                                                       | `app/Modules/Geography/Contracts/GeographyLookup.php`                                                   | Interface publik — lihat contoh Bab 4.2                                                                |
| `app/Models/Bank.php`                                          | `app/Modules/BankDirectory/Domain/Models/Bank.php`                                                      |                                                                                                        |
| `app/Services/BankService.php`                                 | `app/Modules/BankDirectory/Application/Actions/{Store,Update,Deactivate}BankAccount.php`                | Pecah jadi Action per use case (opsional, lihat 7.5)                                                   |
| `app/Http/Controllers/BankController.php`                      | `app/Modules/BankDirectory/Http/Controllers/BankController.php`                                         |                                                                                                        |
| `app/Http/Requests/{Store,Update}BankRequest.php`              | `app/Modules/BankDirectory/Http/Requests/*.php`                                                         |                                                                                                        |
| `app/Http/Resources/BankResource.php`                          | `app/Modules/BankDirectory/Http/Resources/BankResource.php`                                             |                                                                                                        |
| _(baru)_                                                       | `app/Modules/BankDirectory/Contracts/BankLookup.php`                                                    | Dipakai modul `Payment` nanti, bukan lewat `Bank::find()` langsung                                     |
| `app/Support/Http/*.php`                                       | `app/Shared/Http/*.php`                                                                                 | Shared Kernel — dipakai semua modul                                                                    |
| `app/Support/Result/*.php`                                     | `app/Shared/Result/*.php`                                                                               | Shared Kernel                                                                                          |
| `app/Exceptions/ApiException.php` + turunannya                 | `app/Shared/Exceptions/*.php`                                                                           | Shared Kernel — tiap modul boleh menambah exception domain sendiri yang **extends** `ApiException` ini |
| `app/Support/Observability/ExceptionReporter.php`              | `app/Shared/Observability/ExceptionReporter.php`                                                        | Cross-cutting, bukan domain bisnis                                                                     |
| `app/Http/Middleware/{RequestId,RequestLogging}Middleware.php` | `app/Shared/Middleware/*.php`                                                                           | Cross-cutting                                                                                          |
| `app/Http/Controllers/MetricsController.php`                   | `app/Shared/Http/Controllers/MetricsController.php` atau tetap di root sebagai infra endpoint           | Endpoint infra, bukan domain bisnis                                                                    |
| `routes/api.php` (satu file datar)                             | Dipecah: tiap modul punya `Routes/api.php` sendiri, didaftarkan lewat `{Module}ServiceProvider::boot()` | `routes/api.php` di root cukup jadi index/prefix umum jika perlu                                       |

### 7.4 Contoh Konkret: Menghindari Coupling ke `Geography` dari Modul Masa Depan

Bayangkan modul `Logistics` (roadmap "Antar") butuh memastikan alamat tujuan valid sebelum menjadwalkan kurir. **Salah**:

```php
// ❌ Logistics langsung query Model milik modul Geography
$village = \App\Modules\Geography\Domain\Models\Village::findOrFail($request->village_id);
```

**Benar** — Logistics hanya bergantung pada Contract milik Geography:

```php
// app/Modules/Logistics/Application/Actions/ScheduleDelivery.php
namespace App\Modules\Logistics\Application\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

final class ScheduleDelivery
{
    public function __construct(private readonly GeographyLookup $geography) {}

    public function __invoke(int $orderId, int $destinationVillageId): Result
    {
        if (! $this->geography->villageExists($destinationVillageId)) {
            return Result::err(new ResultError(
                code: 'invalid_destination',
                message: 'Wilayah tujuan tidak ditemukan.',
                status: 422,
                title: 'Unprocessable Entity',
            ));
        }

        // lanjutkan proses penjadwalan pengiriman...
        return Result::ok(/* ... */);
    }
}
```

Modul `Logistics` tidak pernah tahu bahwa `GeographyLookup` di baliknya adalah query ke tabel `villages` — bisa saja besok diganti cache Redis atau layanan eksternal tanpa `Logistics` berubah sedikit pun.

### 7.5 Bank Service → Application Actions

`BankService` saat ini sudah mengikuti Result pattern dengan baik — pertahankan pola itu, hanya pindahkan lokasinya dan (opsional, jika tim lebih menyukai _single-purpose class_) pecah tiap method jadi satu Action:

```php
// app/Modules/BankDirectory/Application/Actions/DeactivateBankAccount.php
namespace App\Modules\BankDirectory\Application\Actions;

use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Shared\Result\Result;

final class DeactivateBankAccount
{
    public function __invoke(Bank $bank): Result
    {
        if ($bank->is_active) {
            $bank->update(['is_active' => false]);
        }

        return Result::ok($bank->refresh());
    }
}
```

Ini opsional — mempertahankan `BankService` sebagai satu class dengan beberapa method publik juga tetap valid selama ia hidup di dalam `app/Modules/BankDirectory/Application/Services/`. Yang **wajib**, bukan opsional, adalah lokasinya berada di dalam modul dan hanya dipanggil oleh `Http/Controllers` **di modul yang sama** (arch test di 7.6 menegakkan ini).

### 7.6 Memperluas Arch Test yang Sudah Ada

`tests/Arch/ArchTest.php` yang sudah ada di JualAntar API adalah fondasi bagus — pertahankan, lalu tambahkan aturan batas modul:

```php
// tests/Arch/ModuleBoundaryTest.php
$modules = ['IdentityAccess', 'Geography', 'BankDirectory'];

foreach ($modules as $module) {
    arch("{$module}: Domain hanya dipakai dalam modulnya sendiri")
        ->expect("App\\Modules\\{$module}\\Domain")
        ->toOnlyBeUsedIn("App\\Modules\\{$module}");
}

arch('Shared Kernel tidak boleh bergantung ke modul bisnis mana pun')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');
```

Aturan lama yang sudah ada tetap berlaku dan makin relevan setelah migrasi:

```php
arch('api exception subclasses extend the base ApiException')
    ->expect('App\Shared\Exceptions')      // pindah dari App\Exceptions
    ->toExtend(ApiException::class)
    ->ignoring(ApiException::class);
```

### 7.7 Urutan Migrasi yang Disarankan (Strangler Fig, bukan Big Bang)

1. Buat `app/Shared/*` dan pindahkan `Support/*` + `Exceptions/*` ke sana lebih dulu — ini paling aman karena tidak mengandung logika bisnis, dan tiap modul akan bergantung padanya.
2. Pindahkan `IdentityAccess` (paling generic, dependency-nya paling sedikit).
3. Pindahkan `Geography`, buat `Contracts/GeographyLookup.php`.
4. Pindahkan `BankDirectory`, buat `Contracts/BankLookup.php`.
5. Tambahkan arch test boundary di setiap langkah — jangan tunggu semua modul selesai dipindah baru menambahkan test.
6. Baru setelah fondasi ini solid, bangun modul roadmap (`Catalog`, `Ordering`, `Logistics`, `Payment`) **langsung** di dalam struktur modul sejak commit pertamanya — jangan ditulis dulu di `app/Http` datar dengan niat "dirapikan nanti".

---

## 8. Testing Strategy

- **Unit test**: hidup di `app/Modules/{Module}/Tests/Unit`, menguji `Domain` dan `Application` tanpa HTTP, boleh mock Contract modul lain.
- **Feature test**: hidup di `app/Modules/{Module}/Tests/Feature`, menguji lewat HTTP endpoint modul tersebut. Jika use case melibatkan Contract modul lain, gunakan fake/binding-swap di container (`$this->app->bind(GeographyLookup::class, FakeGeographyLookup::class)`), **jangan** menjalankan modul lain sungguhan hanya untuk test milik modul ini.
- **Arch test**: hidup di `tests/Arch` (level aplikasi, bukan per modul) karena tugasnya menegakkan aturan _antar_ modul — lihat Bab 4.4 dan 7.6.
- **Contract test** (opsional, untuk tim besar): jika suatu Contract dianggap kritikal, tambahkan test yang menjalankan implementasi konkret Contract tersebut untuk memastikan ia tetap memenuhi interface-nya seiring waktu.

---

## 9. Module Governance & Lifecycle

### 9.1 Checklist Membuat Modul Baru

- [ ] `module.json` berisi nama, deskripsi satu-kalimat, dan daftar modul lain yang menjadi dependency-nya.
- [ ] `{ModuleName}ServiceProvider` dibuat dan didaftarkan manual di `bootstrap/providers.php`.
- [ ] Minimal satu Contract publik didefinisikan **sebelum** modul lain diizinkan bergantung padanya.
- [ ] Migration modul memakai schema/prefix sesuai Bab 6.
- [ ] Tidak ada FK fisik ke tabel modul lain.
- [ ] Arch test boundary ditambahkan untuk modul ini di hari yang sama modul dibuat, bukan "nanti".
- [ ] README singkat di root modul menjelaskan: apa tanggung jawabnya, apa yang **bukan** tanggung jawabnya, dan Contract apa saja yang ia ekspos.

### 9.2 Contoh `module.json`

```json
{
    "name": "BankDirectory",
    "description": "Direktori referensi bank untuk kebutuhan rekening tujuan pembayaran.",
    "classification": "generic",
    "depends_on": [],
    "exposes_contracts": ["App\\Modules\\BankDirectory\\Contracts\\BankLookup"]
}
```

### 9.3 Kapan Sebuah Modul Layak Diekstrak Jadi Service Terpisah

Karena batas sudah eksplisit sejak awal (Bab 4 & 6), ekstraksi menjadi lebih murah — tapi tetap harus dipertimbangkan, bukan default. Pertimbangkan ekstraksi hanya jika **salah satu** ini terjadi:

- Modul butuh skala/resource yang jauh berbeda dari modul lain (mis. proses image processing berat vs CRUD ringan).
- Modul perlu siklus deploy independen karena dikelola tim/vendor terpisah.
- Modul perlu bahasa/runtime berbeda karena kebutuhan teknis spesifik.

Langkah ekstraksi: ganti implementasi `Contracts/` dari `EloquentXxx` menjadi `HttpXxx`/`GrpcXxx` yang memanggil service baru, tanpa modul pemanggil (consumer) perlu tahu perubahan ini terjadi.

---

## 10. Anti-Pattern Checklist

Tinjau checklist ini setiap code review yang menyentuh lebih dari satu modul:

- ❌ **God Module** — satu modul (biasanya "Core" atau "Common") mengetahui detail internal banyak modul lain. Solusi: pecah berdasarkan kapabilitas, bukan berdasarkan "tempat naruh yang belum jelas".
- ❌ **Shared Kernel yang membengkak** — method baru di Shared Kernel yang sebenarnya hanya dipakai satu modul. Solusi: pindahkan ke modul yang memakainya.
- ❌ **Chatty synchronous calls** — modul A memanggil Contract modul B berkali-kali dalam satu request untuk membangun satu response (N+1 antar modul). Solusi: sediakan method Contract yang menerima banyak ID sekaligus (`villagesExist(array $ids)`), atau bangun read model.
- ❌ **Distributed Monolith Menyamar** — dua modul saling memanggil Contract satu sama lain secara sinkron dan bergantian (circular call), sehingga tidak ada yang benar-benar bisa jalan sendiri walau kelihatannya "modular". Solusi: tegakkan Dependency Rule di Bab 4.3.
- ❌ **Anemic Boundary** — Contract dibuat, tapi implementasi konkretnya tetap dipanggil langsung di beberapa tempat "karena buru-buru". Solusi: arch test (Bab 4.4) harus mem-block ini secara otomatis, jangan mengandalkan disiplin manual.
- ❌ **Migration lintas modul dalam satu file** — satu file migration membuat tabel untuk dua modul berbeda sekaligus. Solusi: migration selalu berada di dalam folder modul yang memiliki tabel tersebut.

---

## 11. Appendix

### 11.1 Alur Keputusan Cepat: "Kode Ini Taruh di Mana?"

```
Apakah kode ini murni teknis & tidak tahu apa-apa soal bisnis
(mis. response envelope, exception base, middleware tracing)?
│
├── YA  → app/Shared/*
│
└── TIDAK
     │
     Apakah kode ini logika/data milik satu kapabilitas bisnis spesifik?
     │
     ├── YA → app/Modules/{ModuleName}/...
     │         │
     │         Apakah modul LAIN perlu memanggilnya?
     │         ├── YA → taruh interface publiknya di Contracts/
     │         └── TIDAK → taruh di Domain/ atau Application/ (privat)
     │
     └── TIDAK YAKIN → kembali ke Bab 3.1, gali dulu bahasa bisnisnya
```

### 11.2 Glosarium

| Istilah                   | Arti                                                                                                                          |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Bounded Context**       | Batas linguistik & model data di mana satu istilah bisnis punya satu makna konsisten.                                         |
| **Ubiquitous Language**   | Kosakata yang dipakai konsisten oleh developer maupun stakeholder bisnis untuk domain yang sama.                              |
| **Shared Kernel**         | Kode teknis generik yang aman dipakai bersama semua modul.                                                                    |
| **Contract**              | Interface + DTO publik yang menjadi satu-satunya cara modul lain berinteraksi dengan suatu modul.                             |
| **Strangler Fig**         | Strategi migrasi bertahap: bangun struktur baru di sebelah yang lama, pindahkan sedikit demi sedikit, tanpa Big Bang rewrite. |
| **Anti-Corruption Layer** | Lapisan penerjemah yang mencegah model/istilah satu domain "mencemari" domain lain saat berkomunikasi.                        |

### 11.3 Referensi Konsep

- Eric Evans — _Domain-Driven Design_ (Strategic Design: Bounded Context, Context Mapping, Core/Supporting/Generic Subdomain).
- Simon Brown — _Modular Monoliths_ (alasan operasional memilih monolith dengan batas modular sebelum microservices).
- RFC 9457 — _Problem Details for HTTP APIs_ (dasar pola `ProblemDetails` di Shared Kernel).
