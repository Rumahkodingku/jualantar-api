---
paths:
    - "app/Modules/Merchant/Application/Operations/**"
---

# Operations

## Outlet roles are contextual, never global

`outlet_manager` / `outlet_staff` live only on `merchant.merchant_outlet_users.role`. Never grant them as Spatie roles and never compare role strings as the authorization mechanism. Owner is `merchants.user_id`, is never stored in `merchant_outlet_users`, and is rejected as an employee.

## Authorize outlet actions through the contextual service

Every outlet-scoped operation goes through `MerchantOperationsAuthorization::authorizeOutletAction($outletId, $capability)`, which resolves the assignment role and maps it to capabilities via `OutletRoleCapabilityResolver`. Keep the single capability map in the resolver; do not gate outlet access on global Spatie permissions or ad-hoc role checks. Owner bypass is explicit inside `authorizeOutletAction()`.
