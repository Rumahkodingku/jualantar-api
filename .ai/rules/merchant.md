---
paths:
  - 'app/Modules/Merchant/**'
---

# Merchant

## Canonical operating_hours format
`merchant_outlets.operating_hours` is `{day: {is_open, open, close}}` (single window per day, closed days have no times). Legacy `{day: [{open, close}]}` was backfilled; registration rules, factory and tests all use the canonical shape. Validate via `Application\Operations\Services\OperatingHoursValidator` (rejects unknown days, overnight and close <= open).

## Service area reuses outlet address columns
P0 service area stores only `service_area_type` + `service_radius_km`. For non-radius types the service area is the outlet's own region, so the submitted `{level}_id` must equal the outlet's address column at that level; irrelevant region/radius fields are rejected with `invalid_service_area`. Availability is derived (never persisted) and resolved in the `merchant.operations.timezone` (default Asia/Jakarta).
