---
paths:
  - 'app/Modules/IdentityAccess/**'
---

# Identity Access

## IdentityAccess owns all authentication; expose Contracts to business modules
IdentityAccess owns identity, credential authentication, Sanctum tokens, logout, email verification lifecycle, RBAC, and the current-user endpoint (`/api/v1/auth/*`, `verification.verify`). It must never import a business module. Cross-module surfaces are Contracts: `UserProvisioning::create()` and `EmailVerification::send()`, bound to Infrastructure implementations in IdentityAccessServiceProvider. Business modules orchestrate their own registration but call these contracts instead of `Hash`/`createToken` directly.
