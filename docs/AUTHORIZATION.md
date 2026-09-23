# Authorization Model (Merchant Operations)

Dokumen ini menjelaskan model authorization `jualantar-api` setelah pemisahan
**global/account role** dari **outlet-scoped role**. Frontend boleh menyembunyikan
menu, tetapi API tetap menjadi security boundary.

## 1. Dua jenis role

```text
User
│
├── Global / Account Role  (Spatie, guard `sanctum`)
│      └── merchant · customer · driver · super-admin
│
└── Merchant Outlet Assignments  (merchant.merchant_outlet_users.role)
       ├── Outlet A → outlet_manager
       └── Outlet B → outlet_staff
```

`outlet_manager` dan `outlet_staff` **tidak pernah** diberikan sebagai Spatie role
global. Role tersebut hanya hidup di `merchant.merchant_outlet_users.role`, karena
artinya adalah `user + outlet`, bukan `user`.

## 2. Source of truth

| Informasi                         | Sumber                                                   |
| --------------------------------- | -------------------------------------------------------- |
| Account/global role               | Spatie `roles` (guard `sanctum`)                         |
| Membership user ↔ outlet          | `merchant.merchant_outlet_users`                         |
| Role user pada outlet             | `merchant.merchant_outlet_users.role` (`OutletUserRole`) |
| Capability per outlet role        | `OutletRoleCapabilityResolver`                           |
| Authorization terhadap outlet     | `MerchantOperationsAuthorization`                        |

## 3. Capability resolver

`App\Modules\Merchant\Domain\Authorization\OutletRoleCapabilityResolver` adalah satu-satunya
source of truth mapping `OutletUserRole → capability`. Capability memakai string
permission `merchant.operations.*`. Jangan menduplikasi mapping ini di controller,
policy, middleware, service, atau frontend.

## 4. Aturan developer: endpoint outlet

Setiap endpoint outlet-scoped wajib mengikuti alur:

```text
1. Resolve outlet dari route  → string $outlet
2. authorizeOutletAction($outlet, $capability)
3. Jika Result error → problem response (403/404)
4. Eksekusi use case
5. Render resource
```

`MerchantOperationsAuthorization::authorizeOutletAction()` menangani seluruh alur:
resolve outlet → cek membership → bypass owner eksplisit → resolve capability dari
role assignment. **Jangan** membuat pengecekan authorization ad-hoc di controller.

Route grup `merchant/operations` dilindungi guard coarse `merchant.context`
(punya merchant context) dan `merchant.owner` (hanya owner). Capability spesifik
tidak lagi ditegakkan lewat middleware Spatie `permission:`.

## 5. Kontrak error

| Situasi                                                    | Status | `code`                        |
| ---------------------------------------------------------- | ------ | ----------------------------- |
| Belum authenticated                                        | 401    | `unauthenticated`             |
| Tidak punya merchant context                               | 403    | `forbidden`                   |
| Bukan owner pada aksi owner-only                           | 403    | `forbidden`                   |
| Assigned ke merchant yang sama, tapi bukan outlet ini      | 403    | `outlet_scope_forbidden`      |
| Assigned ke outlet ini, tapi role tidak punya capability   | 403    | `outlet_capability_forbidden` |
| Outlet milik merchant lain / tidak ada                     | 404    | `outlet_not_found`            |

Perbedaan 403 (same-merchant, di luar assignment) vs 404 (merchant asing)
dipertahankan agar keberadaan resource merchant lain tidak bocor.

## 6. Kontrak `/auth/me`

`UserResource` mengembalikan `roles`, `permissions` (global), dan
`outlet_assignments`:

```json
{
  "id": "...",
  "email": "...",
  "roles": ["merchant"],
  "permissions": [],
  "outlet_assignments": [
    { "outlet_id": "...", "role": "outlet_manager" },
    { "outlet_id": "...", "role": "outlet_staff" }
  ]
}
```

`outlet_assignments` diisi oleh `OutletAssignmentsContributor` (modul Merchant)
lewat kontrak `IdentityAccess\Contracts\UserContextContributor`. IdentityAccess
adalah modul *generic*, jadi tidak boleh bergantung ke modul bisnis; dependency
dibalik melalui kontrak tersebut.

Frontend **tidak boleh** menebak permission outlet dari daftar `permissions`
global. Source of authorization tetap `outlet_assignments` + capability resolver.

## 7. Yang tidak boleh dilakukan

- ❌ Memberi `outlet_manager`/`outlet_staff` sebagai Spatie role global.
- ❌ Menjadikan `permissions` global sebagai representasi akses outlet.
- ❌ Mengganti role assignment dengan perbandingan string `=== 'outlet_manager'`.
- ❌ Menjadikan owner sebagai `outlet_manager` secara implisit.
- ❌ Menegakkan akses outlet di frontend saja.
