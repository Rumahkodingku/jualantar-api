---
paths:
  - 'app/Modules/Notifications/**'
---

# Notifications

## Notifications module boundary and contract seam
Generic platform module with depends_on: []. Other modules may import only App\Modules\Notifications\Contracts\*; never Domain/Application/Infrastructure (enforced in tests/Arch/ModuleBoundaryTest.php). recipient_id is a plain UUID with no FK. Expired notifications are treated as not-found (404 notification_not_found) in all recipient-facing operations; include_expired only affects listing. HTTP errors flow through Result + ApiResponse::fromResult; the public contract throws ApiException. {notification} is resolved by a scoped Route::bind in NotificationsServiceProvider so missing/expired/foreign IDs all return the same error.
