# PRD — Notifications Module

**Status:** Draft / Implementation Specification  
**Project:** JualAntar API  
**Module:** Notifications  
**Scope:** In-app notification management only

---

## 1. Executive Summary

JualAntar API follows a Laravel Modular Monolith architecture with business capabilities isolated under `app/Modules/*` and generic technical concerns under `app/Shared/*`.

The current repository already contains notification-related classes inside `IdentityAccess` and `Merchant`, but those classes currently focus on outbound mail notifications. This PRD introduces a dedicated `Notifications` module whose first responsibility is persistent, recipient-scoped **in-app notifications**.

This phase must remain standalone:

- no integration with Merchant;
- no integration with Customer;
- no integration with IdentityAccess domain models;
- no integration with Payout;
- no integration with Ordering, Ride, Food, Mart, Driver, or other future modules;
- no business-domain event listeners;
- no email/push/SMS/WhatsApp delivery;
- no changes to business workflows.

Future modules will integrate through a stable Notifications contract after this module is complete.

---

## 2. Problem Statement

Notification behavior is currently distributed across existing modules, for example:

- `app/Modules/IdentityAccess/Notifications`
- `app/Modules/Merchant/Notifications`

Those implementations are primarily outbound email notifications. They do not provide one consistent domain for persistent user inbox notifications.

Without a dedicated Notifications module:

1. Business modules may create their own notification persistence strategy.
2. Read/unread semantics may be duplicated.
3. Recipient ownership rules may diverge.
4. Future notification channels may become coupled to business logic.
5. Future cross-module integrations may import implementation details instead of a stable contract.

The Notifications module becomes the single owner of persistent in-app notification state.

---

## 3. Product Goal

Build a reusable notification inbox capability that can:

- persist notifications;
- query notifications for the authenticated recipient;
- distinguish read and unread state;
- mark one or all notifications as read;
- delete notifications;
- provide unread counts;
- expose a small contract for future producers.

The module must work independently from all current business modules.

---

## 4. Current Codebase Findings

The analyzed repository shows the following architectural characteristics.

### Existing module architecture

The main modules include capabilities such as:

- IdentityAccess
- Customer
- Merchant
- Geography
- BankDirectory
- Service
- Storage
- Payout

Business code is organized under `app/Modules/{Module}`.

Typical module concerns include:

- `Application`
- `Domain`
- `Infrastructure`
- `Http`
- `Database`
- `Routes`
- `Tests`
- `Contracts`

### Shared Kernel

The repository already provides shared infrastructure under `app/Shared`, including HTTP response handling, Result patterns, database concerns, exceptions, observability, and OpenAPI support.

Notifications should reuse those existing facilities rather than introduce another response or error framework.

### Authentication

IdentityAccess owns user identities and Sanctum authentication.

The User model belongs to:

`App\Modules\IdentityAccess\Domain\Models\User`

Other modules are not supposed to import that model directly. Cross-module access is performed through contracts.

### Database

The project uses PostgreSQL with a dedicated database schema per module.

Existing migrations use the shared `CreatesSchema` concern.

Cross-module identifiers are stored as plain identifiers without physical foreign keys.

### Routing

Module routes follow the versioned API convention:

```php
Route::middleware('api')
    ->prefix('api/v1')
    ->name('api.v1.');
```

Authenticated routes use `auth:sanctum`.

### Existing notification implementation

Merchant currently contains notification classes for merchant approval outcomes, while IdentityAccess contains an email verification notification.

These should **not** be moved into the new Notifications module as part of this PRD.

They represent outbound email behavior, whereas the new Notifications module represents persistent in-app notification state.

---

## 5. Architectural Decision

Create:

```text
app/Modules/Notifications/
```

as a **generic platform module**.

The module must have:

```json
"depends_on": []
```

It must not depend on IdentityAccess, Merchant, Customer, Payout, or other business modules.

The module owns:

- notification persistence;
- notification read state;
- recipient-scoped queries;
- notification lifecycle;
- future producer contract.

Future modules may import only:

```text
App\Modules\Notifications\Contracts\*
```

and must never import Notifications Domain models directly.

---

## 6. Scope

### 6.1 In Scope

- Persistent notification records.
- Recipient-scoped inbox.
- Pagination.
- Unread filtering.
- Notification type filtering.
- Priority filtering.
- Notification detail retrieval.
- Mark one notification as read.
- Mark all notifications as read.
- Unread count.
- Delete notification.
- Optional expiration.
- Optional deduplication key.
- Application-level notification creation contract.
- Unit tests.
- Feature tests.
- Architecture/boundary tests.
- API documentation.

### 6.2 Out of Scope

This phase must not implement:

- Email delivery.
- Push notification delivery.
- SMS.
- WhatsApp.
- Firebase/FCM.
- OneSignal.
- External notification providers.
- WebSocket/realtime broadcasting.
- Device token management.
- Notification preferences.
- Business-domain event listeners.
- Merchant integration.
- Customer integration.
- Ordering integration.
- Ride integration.
- Food integration.
- Mart integration.
- Driver integration.
- Payout integration.
- Notification template management.
- Admin notification composer.
- Marketing/broadcast campaigns.
- Scheduled retention cleanup.

---

## 7. Design Principles

### 7.1 Notifications owns notification state

The Notifications module owns the lifecycle of persistent notification records.

### 7.2 Producers own business meaning

A future business module decides **when** a notification should exist and what it means.

Notifications only stores and serves that notification.

### 7.3 No business-domain coupling

Notifications must not import:

```text
App\Modules\Merchant\Domain\*
App\Modules\Customer\Domain\*
App\Modules\Payout\Domain\*
```

or other business module internals.

### 7.4 No physical FK to IdentityAccess

`recipient_id` may logically refer to an IdentityAccess user, but the database must not create a foreign key to `identity_access.users`.

This follows the repository's cross-module boundary rules.

### 7.5 Stable contract

Future producers interact through a Notifications contract instead of the Eloquent model.

### 7.6 Payload is opaque

The `data` JSON payload is opaque to Notifications.

Notifications should not interpret business-specific keys such as:

- `merchant_id`
- `application_id`
- `order_id`
- `payout_id`

unless a future generic requirement explicitly introduces such semantics.

---

## 8. Terminology

| Term              | Definition                                                                         |
| ----------------- | ---------------------------------------------------------------------------------- |
| Recipient         | The user identity that owns a notification in the inbox.                           |
| Notification      | A persistent in-app message intended for one recipient.                            |
| Notification Type | Stable machine-readable identifier describing the kind of notification.            |
| Payload           | Structured JSON metadata associated with a notification.                           |
| Read              | Notification has a non-null `read_at`.                                             |
| Unread            | Notification has a null `read_at`.                                                 |
| Action URL        | Optional frontend destination associated with the notification.                    |
| Deduplication Key | Optional producer-provided key used to prevent accidental duplicate notifications. |
| Expired           | Notification whose `expires_at` has passed.                                        |

---

## 9. Proposed Module Structure

Recommended structure:

```text
app/Modules/Notifications/
├── Application/
│   ├── Actions/
│   │   ├── CreateNotification.php
│   │   ├── MarkNotificationAsRead.php
│   │   ├── MarkAllNotificationsAsRead.php
│   │   ├── DeleteNotification.php
│   │   └── GetUnreadNotificationCount.php
│   └── Services/
│       └── NotificationQueryService.php
│
├── Contracts/
│   ├── Notifications.php
│   └── DataTransferObjects/
│       ├── CreateNotificationData.php
│       └── NotificationData.php
│
├── Database/
│   ├── Factories/
│   │   └── NotificationFactory.php
│   └── Migrations/
│       └── *_create_notifications_table.php
│
├── Domain/
│   ├── Enums/
│   │   └── NotificationPriority.php
│   ├── Exceptions/
│   │   └── NotificationNotFoundException.php
│   └── Models/
│       └── Notification.php
│
├── Http/
│   ├── Controllers/
│   │   └── NotificationController.php
│   ├── Requests/
│   │   └── ListNotificationsRequest.php
│   └── Resources/
│       └── NotificationResource.php
│
├── Routes/
│   └── api.php
│
├── Tests/
│   ├── Feature/
│   │   └── NotificationControllerTest.php
│   └── Unit/
│       ├── CreateNotificationTest.php
│       └── NotificationQueryTest.php
│
├── NotificationsServiceProvider.php
└── module.json
```

The exact number of classes may be reduced if the implementation can follow existing repository conventions more cleanly. The architectural boundaries are more important than the file count.

---

## 10. Database Design

### 10.1 PostgreSQL Schema

Create a dedicated PostgreSQL schema:

```text
notifications
```

The migration must call:

```php
$this->ensureSchema('notifications');
```

using:

```php
App\Shared\Database\Concerns\CreatesSchema
```

### 10.2 Main Table

Table:

```text
notifications.notifications
```

Recommended columns:

| Column            | Type         | Nullable | Description                                |
| ----------------- | ------------ | -------: | ------------------------------------------ |
| id                | UUID         |       No | Primary key                                |
| recipient_id      | UUID         |       No | Logical recipient identity; no physical FK |
| type              | VARCHAR(100) |       No | Stable machine-readable notification type  |
| title             | VARCHAR(255) |       No | Notification title                         |
| body              | TEXT         |       No | Notification body                          |
| action_url        | TEXT         |      Yes | Optional frontend destination              |
| priority          | VARCHAR(20)  |       No | `low`, `normal`, `high`, `urgent`          |
| data              | JSONB        |      Yes | Opaque structured metadata                 |
| deduplication_key | VARCHAR(255) |      Yes | Optional idempotency key                   |
| read_at           | TIMESTAMPTZ  |      Yes | Time first marked as read                  |
| expires_at        | TIMESTAMPTZ  |      Yes | Optional expiration                        |
| created_at        | TIMESTAMPTZ  |       No | Creation timestamp                         |
| updated_at        | TIMESTAMPTZ  |       No | Last update timestamp                      |

No physical foreign key must be added from `recipient_id` to `identity_access.users`.

### 10.3 Indexes

Minimum recommended indexes:

1. `(recipient_id, created_at)`
2. `(recipient_id, read_at, created_at)`
3. `(recipient_id, type, created_at)`
4. `(expires_at)` if expiration is included in normal query predicates.

The primary inbox query should optimize:

```sql
WHERE recipient_id = ?
ORDER BY created_at DESC
```

Unread count should optimize:

```sql
WHERE recipient_id = ?
  AND read_at IS NULL
```

### 10.4 Deduplication Constraint

Do not make `deduplication_key` globally unique.

A future producer may intentionally use the same key for different recipients.

Prefer recipient-scoped uniqueness:

```text
(recipient_id, deduplication_key)
```

Because the field is nullable, implementation should use a PostgreSQL-compatible nullable uniqueness strategy.

If strict database uniqueness is deferred, application-level idempotency must still be clearly documented and tested.

---

## 11. Domain Model

Model:

```text
App\Modules\Notifications\Domain\Models\Notification
```

Requirements:

- UUID primary key.
- PostgreSQL schema-qualified table.
- Use `#[Table('notifications.notifications')]`.
- Use `#[UseFactory(NotificationFactory::class)]`.
- Cast `data` to array/JSON.
- Cast `priority` to `NotificationPriority`.
- Cast timestamps appropriately.
- Contain only notification-domain behavior.

The model must not define relations to:

- IdentityAccess User;
- Merchant;
- Customer;
- Payout;
- Order;
- Ride;
- Food;
- Mart;
- Driver.

Do not define:

```php
belongsTo(User::class)
```

Do not introduce polymorphic business relations in this phase.

---

## 12. Notification Priority

Recommended enum:

```php
enum NotificationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
```

Default:

```text
normal
```

Priority is presentation metadata only.

It must not trigger delivery behavior.

---

## 13. Notification Type

Use a string instead of a hardcoded enum.

Rules:

- lowercase;
- maximum 100 characters;
- machine-readable;
- allowed characters: `a-z`, `0-9`, `.`, `_`, `-`.

Examples for future integrations:

```text
merchant.application.approved
merchant.application.revision_required
order.created
order.completed
payout.completed
```

These are examples only.

The Notifications module must not implement those business scenarios in this phase.

---

## 14. Contract Design

### 14.1 Public Contract

Create:

```text
app/Modules/Notifications/Contracts/Notifications.php
```

The contract must not expose Eloquent models, query builders, or persistence details.

Recommended interface:

```php
interface Notifications
{
    public function create(
        CreateNotificationData $data
    ): NotificationData;

    public function markAsRead(
        string $notificationId,
        string $recipientId
    ): NotificationData;

    public function markAllAsRead(
        string $recipientId
    ): int;

    public function unreadCount(
        string $recipientId
    ): int;

    public function delete(
        string $notificationId,
        string $recipientId
    ): void;
}
```

The implementation may use a concrete class under `Application` or `Infrastructure`.

Other modules must depend only on the contract.

### 14.2 CreateNotificationData

Recommended:

```php
final readonly class CreateNotificationData
{
    public function __construct(
        public string $recipientId,
        public string $type,
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public NotificationPriority $priority = NotificationPriority::Normal,
        public ?array $data = null,
        public ?string $deduplicationKey = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {}
}
```

### 14.3 NotificationData

Recommended fields:

- `id`
- `type`
- `title`
- `body`
- `actionUrl`
- `priority`
- `data`
- `readAt`
- `expiresAt`
- `createdAt`

Do not expose the Eloquent model.

---

## 15. API Endpoints

All routes must use:

```php
Route::middleware('api')
    ->prefix('api/v1')
    ->name('api.v1.');
```

Recipient-facing routes must use:

```text
auth:sanctum
```

### 15.1 List Notifications

```http
GET /api/v1/notifications
```

Query parameters:

| Parameter       | Type    | Description                 |
| --------------- | ------- | --------------------------- |
| page            | integer | Pagination page             |
| per_page        | integer | Page size                   |
| unread          | boolean | Return unread only          |
| type            | string  | Filter by notification type |
| priority        | string  | Filter by priority          |
| include_expired | boolean | Default false               |

Defaults:

```text
per_page = 20
maximum = 100
```

Rules:

- recipient comes only from authentication context;
- newest notifications first;
- expired records excluded by default;
- no arbitrary recipient ID parameter.

Response should use the repository's existing `ApiResponse::paginated()` convention.

### 15.2 Notification Detail

```http
GET /api/v1/notifications/{notification}
```

Requirements:

- authenticated;
- recipient scoped;
- 404 when inaccessible or missing;
- must not expose whether an ID belongs to another user.

### 15.3 Mark One as Read

```http
PATCH /api/v1/notifications/{notification}/read
```

Rules:

- set `read_at` only when currently null;
- repeated calls are idempotent;
- preserve original first-read timestamp.

### 15.4 Mark All as Read

```http
PATCH /api/v1/notifications/read-all
```

Rules:

- only authenticated recipient's notifications;
- only unread active notifications;
- use a set-based database update;
- return affected count where appropriate.

### 15.5 Unread Count

```http
GET /api/v1/notifications/unread-count
```

Recommended response:

```json
{
    "data": {
        "count": 7
    }
}
```

Expired notifications must not contribute to the unread count.

### 15.6 Delete

```http
DELETE /api/v1/notifications/{notification}
```

Rules:

- recipient scoped;
- only own notification can be deleted;
- hard delete for MVP.

---

## 16. No Public Create Endpoint

Do not provide a normal client-facing endpoint:

```http
POST /api/v1/notifications
```

in this phase.

Notification creation should happen through the application contract from trusted application code.

Reason:

Allowing arbitrary clients to specify recipient, type, title, body, and metadata creates an unnecessary security and abuse boundary.

Tests can create notifications through the application layer.

---

## 17. Ownership and Authorization

The authenticated principal is the only source of truth for recipient scope.

The controller must derive recipient ID from authentication context.

Never trust:

```text
recipient_id
```

from request body or query parameters for recipient-facing operations.

Every read, update, count, and delete operation must be scoped by recipient ID.

The Notifications module must not import the IdentityAccess User model to perform authorization.

---

## 18. Expiration Semantics

If `expires_at` exists and is in the past:

- exclude from normal listing;
- exclude from unread count;
- do not automatically delete during normal API requests.

Physical cleanup or archival is future work.

---

## 19. Read-State Semantics

Unread:

```sql
read_at IS NULL
```

Read:

```sql
read_at IS NOT NULL
```

Rules:

- first mark-read sets timestamp;
- subsequent mark-read calls do not modify timestamp;
- mark-unread is not included in MVP.

---

## 20. Deletion Semantics

For MVP, deleting a notification means removing it from the recipient's inbox.

Recommended implementation:

```text
hard delete
```

Do not introduce soft deletes only for hypothetical future requirements.

Archival can be designed separately later.

---

## 21. Idempotency and Deduplication

The optional `deduplication_key` allows future producers to make creation idempotent.

Example:

```text
merchant.application.approved:{application-id}
```

Create behavior:

1. No key supplied → create a new notification.
2. Key supplied and recipient/key already exists → return existing notification.
3. Key supplied and no match → create notification.

The exact database implementation may use recipient-scoped unique constraints or transactional application-level checks.

Concurrency behavior must be tested if deduplication is implemented at application level.

---

## 22. Error Handling

Reuse the project's existing:

- `App\Shared\Http\ApiResponse`;
- `App\Shared\Result`;
- RFC 9457 / Problem Details conventions.

Recommended application error code:

```text
notification_not_found
```

For another user's notification, return the same not-found behavior rather than exposing ownership information.

Validation failures should use HTTP 422.

---

## 23. Application Layer

Recommended actions:

### CreateNotification

Responsibilities:

- validate application invariants;
- apply defaults;
- enforce deduplication;
- persist notification;
- return DTO.

### MarkNotificationAsRead

Responsibilities:

- load notification within recipient scope;
- set `read_at` only once;
- return DTO.

### MarkAllNotificationsAsRead

Responsibilities:

- update unread active notifications in one query;
- return affected count.

### GetUnreadNotificationCount

Responsibilities:

- count active unread notifications for one recipient.

### DeleteNotification

Responsibilities:

- delete one recipient-owned notification.

A repository abstraction should only be introduced if query complexity actually requires it.

---

## 24. Query Strategy

Notification queries remain private to the Notifications module.

Do not expose:

- Eloquent models;
- query builders;
- Eloquent relationships;
- query callbacks.

Other modules must never call:

```php
Notification::query()
```

directly.

---

## 25. Service Provider

Create:

```text
app/Modules/Notifications/NotificationsServiceProvider.php
```

Responsibilities:

- bind the Notifications contract;
- load migrations;
- load versioned routes.

Register manually in:

```text
bootstrap/providers.php
```

Do not use module auto-discovery.

---

## 26. module.json

Recommended:

```json
{
    "name": "Notifications",
    "description": "Persistent in-app notifications and recipient inbox management.",
    "classification": "generic",
    "depends_on": [],
    "exposes_contracts": [
        "App\\Modules\\Notifications\\Contracts\\Notifications"
    ]
}
```

The module must not declare a dependency on IdentityAccess merely to access the User model.

---

## 27. Route Design

Recommended routes:

```text
GET    /api/v1/notifications
GET    /api/v1/notifications/unread-count
GET    /api/v1/notifications/{notification}
PATCH  /api/v1/notifications/{notification}/read
PATCH  /api/v1/notifications/read-all
DELETE /api/v1/notifications/{notification}
```

Recommended route names:

```text
api.v1.notifications.index
api.v1.notifications.unread_count
api.v1.notifications.show
api.v1.notifications.read
api.v1.notifications.read_all
api.v1.notifications.destroy
```

Only the Notifications model should be used for route model binding.

---

## 28. API Resource

Create:

```text
NotificationResource
```

Recommended response:

```json
{
    "id": "uuid",
    "type": "merchant.application.approved",
    "title": "Pengajuan merchant disetujui",
    "body": "Pengajuan merchant Anda telah disetujui.",
    "action_url": "/app/merchant",
    "priority": "normal",
    "data": {},
    "read_at": null,
    "expires_at": null,
    "created_at": "2026-09-18T12:00:00Z"
}
```

Do not expose:

- database schema names;
- Eloquent class names;
- internal implementation metadata;
- recipient ID unless a future explicit requirement needs it.

---

## 29. Security Requirements

### Authorization

Every recipient-scoped operation must constrain by authenticated recipient ID.

### Enumeration resistance

A user must not be able to determine whether a notification ID belongs to another recipient.

### Validation

Validate:

- title length;
- body presence and length;
- type format;
- priority;
- action URL;
- metadata size;
- pagination.

### Payload size

Recommended initial maximum:

```text
64 KB
```

### Content handling

Notification title/body/data must be treated as data.

Do not execute user-controlled notification content as HTML or script.

---

## 30. Performance Requirements

The module should provide:

- indexed recipient + creation-time inbox queries;
- indexed unread count queries;
- set-based mark-all-read;
- bounded pagination;
- no cross-module joins;
- no unrelated relation loading.

The expected common list query is:

```sql
WHERE recipient_id = ?
ORDER BY created_at DESC
LIMIT ?
```

---

## 31. Queue / Async Strategy

Notification persistence should be synchronous in this phase.

`CreateNotification` should persist immediately when called through the contract.

Future producers may invoke it from:

- normal application flow;
- domain event listener;
- queued job.

That decision belongs to the producer.

Notifications itself must remain agnostic to execution mode.

---

## 32. Channel Strategy

This module is specifically for:

```text
In-App Notification
```

Future channels may include:

```text
Email
Push
SMS
WhatsApp
```

Do not create a generic channel framework in this phase.

Do not add fields such as:

- `channel`;
- `delivery_status`;
- `provider_message_id`;
- `delivery_attempts`;

to the main notification table unless a separate delivery model is designed later.

An inbox record and a delivery attempt are different concepts.

---

## 33. Relationship to Laravel Notification System

The repository already uses Laravel's Notification system for outbound mail.

The existing mail notification classes should remain separate from the new Notifications module.

Future architecture may look like:

```text
Business Event
      |
      +----> Notifications Contract
      |             |
      |             +----> In-app notification
      |
      +----> Laravel Notification
                    |
                    +----> Email
```

This orchestration is explicitly outside this PRD.

---

## 34. Testing Strategy

### 34.1 Unit Tests

Location:

```text
app/Modules/Notifications/Tests/Unit
```

Cover:

- notification creation;
- default priority;
- notification type validation;
- deduplication;
- mark-read;
- repeated mark-read idempotency;
- mark-all-read;
- unread count;
- expiration;
- DTO mapping.

### 34.2 Feature Tests

Location:

```text
app/Modules/Notifications/Tests/Feature
```

Cover:

- unauthenticated access rejected;
- authenticated user can list own notifications;
- pagination;
- unread filter;
- type filter;
- priority filter;
- detail retrieval;
- cross-user protection;
- mark one read;
- mark all read;
- unread count;
- delete.

### 34.3 Architecture Tests

Add Notifications to module boundary tests.

Enforce:

- Notifications Domain only used within Notifications;
- other modules may import only Notifications Contracts;
- Notifications cannot import another module's Domain/Application/Infrastructure;
- Shared cannot depend on Notifications.

---

## 35. Architecture Boundary Rules

### Rule A — Notifications is isolated

Internal code may use:

```text
App\Modules\Notifications\*
```

inside the module.

### Rule B — Other modules only use Contracts

Allowed:

```php
use App\Modules\Notifications\Contracts\Notifications;
```

Prohibited:

```php
use App\Modules\Notifications\Domain\Models\Notification;
```

### Rule C — No reverse dependency

Notifications must not import Merchant, Customer, Payout, Ordering, Ride, Food, Mart, or Driver internals.

### Rule D — No cross-module FK

```text
notifications.recipient_id
```

must remain a plain UUID.

### Rule E — Shared stays generic

Nothing under:

```text
app/Shared/
```

may depend on Notifications.

---

## 36. Future Integration Contract

This PRD deliberately ends the Notifications implementation before business integration.

A future integration can use:

```text
Business Module
      |
      | application/domain event
      v
Notifications Contract
      |
      v
notifications.notifications
```

Example future producer call:

```php
$notifications->create(
    new CreateNotificationData(
        recipientId: $merchantOwnerId,
        type: 'merchant.application.approved',
        title: 'Pengajuan merchant disetujui',
        body: 'Pengajuan merchant Anda telah disetujui.',
        actionUrl: '/app/merchant',
        priority: NotificationPriority::Normal,
        data: [
            'application_id' => $applicationId,
        ],
        deduplicationKey: 'merchant.application.approved:'.$applicationId,
    ),
);
```

This is architectural guidance only and must **not** be implemented in this phase.

---

## 37. Database Search Path

The project's PostgreSQL configuration must include the new:

```text
notifications
```

schema in the database search path, following existing module schema conventions.

Laravel validation rules must follow the project's existing rule that schema-qualified names can be interpreted as connection names.

Where appropriate, use unqualified table names in validation rules and rely on the configured PostgreSQL search path.

---

## 38. Migration and Deployment Requirements

Implementation must include:

- Notifications module;
- `module.json`;
- ServiceProvider;
- provider registration;
- PostgreSQL schema;
- notification table;
- indexes;
- model;
- enum;
- factory;
- DTOs;
- contract;
- application actions;
- HTTP layer;
- routes;
- API resources;
- tests;
- architecture boundary updates;
- database search-path configuration.

Do not modify Merchant, Customer, IdentityAccess, Payout, or other business migrations to create notification tables.

---

## 39. API Documentation

Use the project's existing Scramble conventions.

Document:

- authentication requirements;
- success responses;
- pagination;
- filters;
- validation failures;
- not-found behavior;
- response schema.

For endpoints implemented with `ApiResponse::fromResult`, follow the repository's existing response annotation pattern.

---

## 40. Observability

Use structured logs only for exceptional conditions.

Avoid logging:

- complete notification bodies;
- complete metadata payloads;
- unnecessary personal recipient information.

Useful technical context:

- notification ID;
- notification type;
- operation.

Metrics are optional for MVP.

Potential future metrics:

- notifications created;
- notifications read;
- creation failures;
- unread count requests.

---

## 41. Acceptance Criteria

### Architecture

- [ ] `app/Modules/Notifications` exists.
- [ ] Module follows repository module conventions.
- [ ] `NotificationsServiceProvider` is manually registered.
- [ ] `module.json` exists.
- [ ] `depends_on` is empty.
- [ ] No business-module dependency exists.
- [ ] Notifications Contract exists.
- [ ] No cross-module FK exists.

### Database

- [ ] `notifications` PostgreSQL schema exists.
- [ ] `notifications.notifications` table exists.
- [ ] UUID primary key exists.
- [ ] `recipient_id` has no physical FK.
- [ ] `data` uses JSONB.
- [ ] `read_at` is nullable.
- [ ] `expires_at` is nullable.
- [ ] required indexes exist.
- [ ] PostgreSQL search path includes `notifications`.

### Application

- [ ] Notification creation works through the contract.
- [ ] Deduplication works when requested.
- [ ] Mark one read is idempotent.
- [ ] Mark all read is set-based.
- [ ] Unread count works.
- [ ] Delete is recipient scoped.
- [ ] Expired notifications are excluded from normal unread counts.

### API

- [ ] List endpoint works.
- [ ] Pagination works.
- [ ] Unread filter works.
- [ ] Type filter works.
- [ ] Priority filter works.
- [ ] Detail endpoint is recipient scoped.
- [ ] Mark-read endpoint works.
- [ ] Mark-all-read endpoint works.
- [ ] Unread-count endpoint works.
- [ ] Delete endpoint works.
- [ ] Responses follow existing API conventions.

### Security

- [ ] Cross-user access is prevented.
- [ ] Recipient cannot be overridden by request input.
- [ ] Notification type is validated.
- [ ] Pagination is bounded.
- [ ] Payload size is bounded.
- [ ] Notification content is not executed as HTML/script.
- [ ] Sensitive content is not unnecessarily logged.

### Testing

- [ ] Unit tests pass.
- [ ] Feature tests pass.
- [ ] Architecture boundary tests pass.
- [ ] Existing project test suite remains green.

---

## 42. Recommended Implementation Order

1. Create the Notifications module.
2. Create `module.json`.
3. Create `NotificationsServiceProvider`.
4. Register the provider in `bootstrap/providers.php`.
5. Add `notifications` to PostgreSQL search-path configuration.
6. Create the schema migration.
7. Create the notification table and indexes.
8. Create the Domain model.
9. Create `NotificationPriority`.
10. Create the factory.
11. Create DTOs.
12. Create the Notifications Contract.
13. Implement the contract.
14. Implement application actions.
15. Implement notification query logic.
16. Implement HTTP requests.
17. Implement `NotificationResource`.
18. Implement `NotificationController`.
19. Implement routes.
20. Add unit tests.
21. Add feature tests.
22. Add architecture boundary tests.
23. Run formatter.
24. Run Notifications tests.
25. Run the complete test suite.
26. Verify that no business-module integration was introduced.

---

## 43. Non-Goals Guardrail

During implementation, reject changes that introduce:

- Merchant-specific notification logic;
- Customer-specific notification logic;
- Order/Ride/Food/Mart/Driver-specific logic;
- email sending;
- push provider configuration;
- device tokens;
- notification preferences;
- business event listeners;
- imports of another module's Domain models;
- physical cross-module foreign keys;
- a generic delivery framework without a concrete requirement.

The module should first be evaluated as a clean, reusable **notification inbox capability**.

---

## 44. Future Work

Separate PRDs should handle:

1. Merchant notification producers.
2. Customer/order notification producers.
3. Driver/Ride notification producers.
4. Food/Mart notification producers.
5. Payout notification producers.
6. Email channel.
7. Push channel.
8. Notification preferences.
9. Realtime notification delivery.
10. Notification retention and cleanup.
11. Delivery attempt tracking.
12. Admin/system broadcast notifications.

---

## 45. Final Architectural Decision

The Notifications module is a standalone generic bounded context whose first responsibility is:

> **Persist, query, read, and manage recipient-scoped in-app notifications.**

It must remain independent from every business module.

Future business modules will integrate through the Notifications Contract.

The purpose of this phase is to establish a clean notification foundation so future integrations can be added without restructuring the module or coupling it to current business-domain assumptions.
