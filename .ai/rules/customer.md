---
paths:
  - 'app/Modules/Customer/**'
---

# Customer

## Customer owns registration and profile only; authentication lives in IdentityAccess
Customer owns customer business profile (`customer.customers`) and the `RegisterCustomer` use case (`POST /api/v1/customers/register`). Login, logout, email verification, and resend verification are owned by IdentityAccess (`/api/v1/auth/*`). Customer depends one-way on IdentityAccess and must never contain `Hash::check`, `createToken`, or token revocation itself (asserted in tests/Arch/ModuleBoundaryTest.php and tests/Arch/BusinessAuthBoundaryTest.php). `RegisterCustomer` provisions the user via `IdentityAccess\Contracts\UserProvisioning` and sends verification via `IdentityAccess\Contracts\EmailVerification`.
