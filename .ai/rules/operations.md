---
paths:
  - 'app/Modules/Merchant/Application/Operations/**'
---

# Operations

## Outlet employee roles go through the Authorization contract
Assigning/removing `outlet_manager` / `outlet_staff` Spatie roles must use `App\Modules\IdentityAccess\Contracts\Authorization::assignRole()/removeRole()` — the Merchant module never imports Spatie or IdentityAccess Domain. Owner is `merchants.user_id` and is never stored in `merchant_outlet_users`; owner assignment is rejected.
