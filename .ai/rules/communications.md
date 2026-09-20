---
paths:
  - 'app/Modules/Communications/**'
---

# Communications

## Communications module: delivery boundary, encryption, and retry semantics
Generic platform module with depends_on: []. Other modules may import only App\Modules\Communications\Contracts\*; Domain/Application/Infrastructure are private (tests/Arch/ModuleBoundaryTest.php). Email is delivered by wrapping Laravel Mailer inside Infrastructure\Providers\Smtp\SmtpEmailProvider; Application/Domain/Contracts must never reference Illuminate\Mail/Notifications or Symfony Mailer/Mime. The communication `payload` column is TEXT and encrypted via the `encrypted:array` cast (not JSONB) so OTP/tokens are never plaintext at rest and never placed on the queue (job carries only the communication ID). Delivery stops at `sent`; `delivered` is reserved for future provider webhooks. Idempotency is a global partial unique index on idempotency_key WHERE NOT NULL. Permanent provider failures mark the record failed without retry; transient failures rethrow for queue retry.

## Contract-exposed enums live under Contracts\Enums
Enums exposed by the public contract (CommunicationChannel, CommunicationStatus) live under App\Modules\Communications\Contracts\Enums, NOT Domain\Enums. Reason: consumers (IdentityAccess, Merchant) may only import Communications\Contracts; any Domain enum referenced by a Contracts DTO would violate tests/Arch/ModuleBoundaryTest.php. DeliveryAttemptStatus stays in Domain because it is internal. When adding a new field to SendCommunicationData/CommunicationResult that is an enum, put the enum under Contracts\Enums.
