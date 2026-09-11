---
paths:
  - 'app/Modules/Customer/**'
---

# Customer

## Customer module owns customer auth orchestration; depends one-way on IdentityAccess
Customer registration/login/email-verification orchestration lives in the Customer module (Customer → IdentityAccess only). IdentityAccess owns User/Sanctum/RBAC/verification primitives and must never import Customer, to avoid a circular dependency (asserted in tests/Arch/ModuleBoundaryTest.php). Customer uses its own `customer` schema (`customer.customers`).
