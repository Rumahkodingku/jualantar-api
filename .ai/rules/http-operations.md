---
paths:
  - 'app/Modules/Merchant/Http/Operations/**'
---

# Http Operations

## Authorize outlet endpoints through MerchantOperationsAuthorization
Every outlet-scoped action must call `MerchantOperationsAuthorization::authorizeOutletAction($outletId, $capability)` before running the use case; capabilities resolve from the assignment role via `OutletRoleCapabilityResolver`. The `merchant/operations` routes are guarded by coarse `merchant.context` / `merchant.owner` middleware — do not reintroduce Spatie `permission:` middleware for outlet access. Keep the 403 (`outlet_scope_forbidden` / `outlet_capability_forbidden`) vs 404 (`outlet_not_found`) distinction.
