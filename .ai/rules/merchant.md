---
paths:
  - 'app/Modules/Merchant/**'
---

# Merchant

## Canonical operating_hours format
`merchant_outlets.operating_hours` is `{day: {is_open, open, close}}` (single window per day, closed days have no times). Legacy `{day: [{open, close}]}` was backfilled; registration rules, factory and tests all use the canonical shape. Validate via `Application\Operations\Services\OperatingHoursValidator` (rejects unknown days, overnight and close <= open).

## Service area reuses outlet address columns
P0 service area stores only `service_area_type` + `service_radius_km`. For non-radius types the service area is the outlet's own region, so the submitted `{level}_id` must equal the outlet's address column at that level; irrelevant region/radius fields are rejected with `invalid_service_area`. Availability is derived (never persisted) and resolved in the `merchant.operations.timezone` (default Asia/Jakarta).

## One product draft per merchant, expired drafts purged on access
`merchant.product_drafts` holds the resumable state of the catalog "add product" wizard. A merchant has at most one draft (unique index on `merchant_id`); `GET`/`PUT /merchant/catalog/product-draft` return 204 / create it, `DELETE` discards it idempotently. There is no scheduled job: `ManagesProductDrafts::purgeExpiredDrafts()` deletes expired drafts and their objects on the next read or save, and every save pushes `expires_at` to now + `merchant.product_draft.ttl_days`. Writes carry `expected_version` and are refused with 409 `product_draft_version_conflict` when the draft moved on, so the unique index race also surfaces as a 409 rather than a 500.

## Draft media is uploaded before the product exists
Wizard photos are uploaded immediately via `POST /product-draft/media/upload-url` (which opens the draft on demand) and live under `merchants/{merchant_id}/drafts/{uuid}.{ext}`. Because the product does not exist yet, `ManagesProductMedia::isOwnedObjectKey()` also accepts a key that sits under the merchant's draft prefix *and* is recorded on their active draft; the prefix alone is forgeable, the stored payload entry is not. Deleting a draft only removes objects no `product_media` row references, so photos already claimed by a product survive. Removing one photo must also edit the draft payload (it is the ownership proof) and promote the next entry to `is_primary`.
