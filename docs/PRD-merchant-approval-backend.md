# PRD — Merchant Approval Backend API

**Project:** JualAntar  
**Repository:** `jualantar-api`  
**Feature:** Merchant Approval  
**Document:** PRD-merchant-approval-backend.md  
**Version:** 1.0  
**Date:** 15 September 2026

## 1. Overview

Merchant Registration mengumpulkan data merchant. Merchant Approval adalah lifecycle terpisah untuk memeriksa, meminta revisi, menolak, atau menyetujui application yang telah disubmit.

Backend menjadi **single source of truth** untuk workflow approval. `jualantar-admin` tidak boleh mengakses database secara langsung dan seluruh operasi dilakukan melalui REST API.

## 2. Goals

- Memisahkan Registration dan Approval.
- Menyimpan histori application secara immutable.
- Mendukung assignment admin/reviewer.
- Mendukung review per komponen.
- Mendukung revision request.
- Mendukung approve/reject.
- Menyediakan audit timeline.
- Menggunakan RFC 9457 untuk error response.
- Menjaga modular-monolith boundary.

## 3. Non-Goals

- Product/catalog management.
- Driver approval.
- Customer verification.
- Payout verification engine baru.
- Rebuild Merchant Registration dari nol.

## 4. Existing Architecture

Gunakan struktur Merchant module yang sudah ada:

```text
app/Modules/Merchant/
├── Application/
├── Database/
├── Domain/
├── Http/
├── Routes/
└── Tests/
```

Jangan membuat module approval terpisah dari Merchant module.

## 5. Data Model

### 5.1 `merchant_applications`

| Field              | Type        | Rule                       |
| ------------------ | ----------- | -------------------------- |
| id                 | UUID        | PK                         |
| merchant_id        | UUID        | FK → merchants.id, CASCADE |
| application_number | VARCHAR(50) | UNIQUE, NOT NULL           |
| status             | VARCHAR(30) | NOT NULL                   |
| submitted_at       | TIMESTAMPTZ | nullable                   |
| created_at         | TIMESTAMPTZ |                            |
| updated_at         | TIMESTAMPTZ |                            |

Status:

```text
draft
pending
in_review
revision_required
approved
rejected
```

Application history tidak boleh di-reopen setelah rejected/approved. Re-apply membuat application baru.

### 5.2 `merchant_application_snapshots`

| Field          | Type        | Rule      |
| -------------- | ----------- | --------- |
| id             | UUID        | PK        |
| application_id | UUID        | UNIQUE FK |
| snapshot       | JSONB       | NOT NULL  |
| created_at     | TIMESTAMPTZ |           |

Snapshot dibuat ketika application disubmit dan bersifat immutable.

### 5.3 `merchant_approvals`

| Field           | Type        | Rule                 |
| --------------- | ----------- | -------------------- |
| id              | UUID        | PK                   |
| application_id  | UUID        | UNIQUE FK            |
| assigned_to     | UUID        | nullable FK users.id |
| assigned_at     | TIMESTAMPTZ | nullable             |
| started_at      | TIMESTAMPTZ | nullable             |
| completed_at    | TIMESTAMPTZ | nullable             |
| decision        | VARCHAR(20) | nullable             |
| decision_reason | TEXT        | nullable             |
| created_at      | TIMESTAMPTZ |                      |
| updated_at      | TIMESTAMPTZ |                      |

`decision`: `approved` / `rejected`.

Jangan menduplikasi application status pada approval.

### 5.4 `merchant_approval_reviews`

| Field        | Type         | Rule                 |
| ------------ | ------------ | -------------------- |
| id           | UUID         | PK                   |
| approval_id  | UUID         | FK                   |
| component    | VARCHAR(30)  | NOT NULL             |
| subject_type | VARCHAR(100) | NOT NULL             |
| subject_id   | UUID         | NOT NULL             |
| status       | VARCHAR(20)  | NOT NULL             |
| note         | TEXT         | nullable             |
| verified_by  | UUID         | nullable FK users.id |
| verified_at  | TIMESTAMPTZ  | nullable             |
| created_at   | TIMESTAMPTZ  |                      |
| updated_at   | TIMESTAMPTZ  |                      |

Components:

```text
business
identity
legal_entity
service
category
outlet
document
payout
```

Review status:

```text
pending
verified
rejected
```

### 5.5 `merchant_approval_revisions`

| Field        | Type        | Rule        |
| ------------ | ----------- | ----------- |
| id           | UUID        | PK          |
| approval_id  | UUID        | FK          |
| requested_by | UUID        | FK users.id |
| note         | TEXT        | nullable    |
| status       | VARCHAR(20) | NOT NULL    |
| requested_at | TIMESTAMPTZ | NOT NULL    |
| resolved_at  | TIMESTAMPTZ | nullable    |
| created_at   | TIMESTAMPTZ |             |
| updated_at   | TIMESTAMPTZ |             |

Status:

```text
open
resolved
cancelled
```

### 5.6 `merchant_approval_revision_items`

| Field        | Type         | Rule     |
| ------------ | ------------ | -------- |
| id           | UUID         | PK       |
| revision_id  | UUID         | FK       |
| component    | VARCHAR(30)  | NOT NULL |
| subject_type | VARCHAR(100) | NOT NULL |
| subject_id   | UUID         | NOT NULL |
| reason       | TEXT         | NOT NULL |
| resolved_at  | TIMESTAMPTZ  | nullable |
| created_at   | TIMESTAMPTZ  |          |
| updated_at   | TIMESTAMPTZ  |          |

### 5.7 `merchant_approval_events`

| Field       | Type        | Rule                 |
| ----------- | ----------- | -------------------- |
| id          | UUID        | PK                   |
| approval_id | UUID        | FK                   |
| event_type  | VARCHAR(50) | NOT NULL             |
| actor_id    | UUID        | nullable FK users.id |
| metadata    | JSONB       | nullable             |
| created_at  | TIMESTAMPTZ | NOT NULL             |

Event log append-only.

## 6. Existing Tables

Do not duplicate verification state into existing entities.

- `merchants`: operational merchant state.
- `merchant_identities`: identity data.
- `legal_entities`: business legal entity.
- `merchant_categories`: merchant categories.
- `merchant_outlets`: outlet operational data.
- `merchant_documents`: uploaded document metadata.
- `payout.payout_accounts`: payout account and payout verification.

Remove approval-specific legacy fields from `merchants` when migration is performed:

```text
rejection_stage
rejection_reason
reviewed_at
reviewed_by
```

Merchant operational status:

```text
inactive
active
suspended
```

Approval status belongs to application.

## 7. API Contract

Base:

```text
/api/v1
```

Authentication:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Admin endpoints

| Method | Endpoint                                         | Purpose          | Success |
| ------ | ------------------------------------------------ | ---------------- | ------- |
| GET    | `/admin/merchant-approvals/summary`              | Queue summary    | 200     |
| GET    | `/admin/merchant-approvals`                      | Approval queue   | 200     |
| GET    | `/admin/merchant-approvals/{approval}`           | Detail           | 200     |
| POST   | `/admin/merchant-approvals/{approval}/claim`     | Claim            | 200     |
| POST   | `/admin/merchant-approvals/{approval}/release`   | Release          | 200     |
| POST   | `/admin/merchant-approvals/{approval}/reviews`   | Review component | 201     |
| POST   | `/admin/merchant-approvals/{approval}/revision`  | Request revision | 201     |
| POST   | `/admin/merchant-approvals/{approval}/reject`    | Reject           | 200     |
| POST   | `/admin/merchant-approvals/{approval}/approve`   | Approve          | 200     |
| GET    | `/admin/merchant-approvals/{approval}/events`    | Timeline         | 200     |
| GET    | `/admin/merchant-approvals/{approval}/revisions` | Revision history | 200     |

### Existing merchant endpoints

Keep current registration endpoints and adapt submit lifecycle:

```text
POST /api/v1/merchants/registration/submit
GET  /api/v1/merchants/registration
```

After submit:

```text
application.status = pending
```

On revision:

```text
application.status = revision_required
```

On resubmit:

```text
application.status = pending
```

## 8. Request Contracts

### Review

```json
{
    "component": "identity",
    "subject_type": "merchant_identity",
    "subject_id": "uuid",
    "status": "verified",
    "note": "Identity data is valid."
}
```

### Revision

```json
{
    "note": "Please correct the following data.",
    "items": [
        {
            "component": "identity",
            "subject_type": "merchant_identity",
            "subject_id": "uuid",
            "reason": "KTP image is unclear."
        },
        {
            "component": "document",
            "subject_type": "merchant_document",
            "subject_id": "uuid",
            "reason": "Document cannot be read."
        }
    ]
}
```

### Reject

```json
{
    "reason": "Application does not meet requirements."
}
```

### Claim / Release / Approve

No request body is required.

## 9. Response Contract

Success responses use:

```json
{
    "data": {}
}
```

Collections:

```json
{
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 100
    }
}
```

## 10. RFC 9457 Error Contract

Content-Type:

```text
application/problem+json
```

Standard:

```json
{
    "type": "https://api.jualantar.id/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "detail": "The submitted data is invalid.",
    "instance": "/api/v1/admin/merchant-approvals/uuid",
    "code": "VALIDATION_ERROR",
    "errors": {
        "business_name": ["The business name field is required."]
    }
}
```

Recommended problem types:

```text
validation-error
authentication-required
forbidden
not-found
conflict
invalid-state-transition
business-rule-violation
rate-limit
internal-server-error
service-unavailable
```

HTTP mapping:

| HTTP | Meaning                      |
| ---- | ---------------------------- |
| 400  | Malformed request            |
| 401  | Unauthenticated              |
| 403  | Permission denied            |
| 404  | Resource not found           |
| 409  | Lifecycle/conflict           |
| 422  | Validation/domain validation |
| 429  | Rate limit                   |
| 500  | Unexpected server error      |
| 503  | Dependency unavailable       |

## 11. Permissions

```text
merchant.approval.view
merchant.approval.claim
merchant.approval.review
merchant.approval.revision
merchant.approval.reject
merchant.approval.approve
```

Use existing Spatie permission conventions.

## 12. Business Rules

1. Only `pending` application can enter `in_review`.
2. Claim changes application to `in_review`.
3. Only assigned reviewer can perform review actions unless an explicit override permission is introduced.
4. Revision changes application to `revision_required`.
5. Merchant can edit registration while `revision_required`.
6. Resubmit changes it back to `pending`.
7. Approve is allowed only from `in_review`.
8. Reject is allowed only from `in_review`.
9. Approve changes merchant operational status to `active`.
10. Reject leaves merchant `inactive`.
11. Approved/rejected applications are immutable.
12. Re-apply creates a new application.
13. Every important transition creates an approval event.
14. Payout verification remains owned by Payout module.
15. Outlet active/inactive remains operational state, not approval state.

## 13. Transaction Boundaries

Approval commands must execute inside database transactions.

Approve:

```text
validate state
→ validate required components
→ mark application approved
→ mark merchant active
→ store decision
→ create event
→ dispatch notification
```

Reject:

```text
validate state
→ mark application rejected
→ store reason
→ create event
→ dispatch notification
```

Revision:

```text
validate state
→ create revision
→ create revision items
→ mark application revision_required
→ create event
→ dispatch notification
```

## 14. Testing Requirements

Feature tests must cover:

- authorization/permissions
- queue listing/filtering
- summary
- detail
- claim
- release
- review component
- revision
- resubmit
- reject
- approve
- invalid state transitions
- ownership/assignment rules
- snapshot creation
- event creation
- merchant activation after approval
- RFC 9457 errors
- transaction rollback

## 15. Acceptance Criteria

Backend is complete when:

- All endpoint contracts are implemented.
- State transitions are enforced in domain/application layer.
- No approval logic exists in controllers.
- RFC 9457 is consistent across approval endpoints.
- Audit events are immutable.
- Application snapshot is immutable.
- Approval cannot bypass required reviews.
- Approval activates the merchant atomically.
- Rejected application cannot be reopened.
- Re-apply creates a new application.
- Existing Merchant Registration tests remain green.
- New Merchant Approval tests are green.

## 16. Implementation Order

```text
1. Enums/domain state
2. Migrations
3. Models/relations
4. Application commands
5. Queries
6. Policies/permissions
7. Controllers
8. API resources
9. RFC 9457 exception mapping
10. Routes
11. Notifications/events
12. Feature tests
13. Refactor legacy reopen/rejection fields
```

## 17. Definition of Done

The backend provides a stable contract that can be consumed independently by `jualantar-admin` and `jualantar-merchant`, with no frontend-specific business logic leaking into the API.
