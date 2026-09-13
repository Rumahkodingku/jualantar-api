---
paths:
  - 'app/Modules/Payout/**'
---

# Payout

## Payout ownership is opaque; never depend on Merchant
`payout_accounts` uses polymorphic `owner_type` + `owner_id` with no FK. `PayoutAccountLookup` exposes owner type as a primitive string (not the Payout enum) so consumers like Merchant never import Payout domain. Payout must not depend on Merchant (arch test enforces it).
