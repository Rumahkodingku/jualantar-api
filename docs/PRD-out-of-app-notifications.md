# PRD — Out-of-App Notifications / Communications Module

**Status:** Draft / Implementation Specification  
**Project:** JualAntar API  
**Module:** Communications  
**Scope:** Out-of-app transactional message delivery only

---

## 1. Executive Summary

JualAntar already has a dedicated `Notifications` module whose responsibility is persistent, recipient-scoped **in-app notification state**. The implemented baseline keeps that module independent from email, SMS, WhatsApp, push providers, and other delivery mechanisms.

This PRD introduces a separate generic module for **out-of-app transactional communication delivery**. For clarity in the codebase, the proposed module name is `Communications`, while the product concept is **Out-of-App Notifications**.

The module is responsible for taking a delivery intent and sending it through an external communication channel such as:

- Email
- SMS
- WhatsApp

The first implementation should prioritize **Email**, because JualAntar already has SMTP configuration and an existing email-verification notification in `IdentityAccess`.

OTP is included in this PRD as a **security/verification message use case**, but OTP generation, validation, attempt limits, and authentication policy remain owned by the appropriate security/identity capability. Communications only delivers an already-authorized message.

The module must remain generic and independent from Merchant, Customer, IdentityAccess domain models, Payout, Ordering, Ride, Food, Mart, Driver, or other business modules.

---

## 2. Baseline and Relationship to Notifications Module

The existing Notifications PRD explicitly defines the Notifications module as an in-app notification inbox capability and excludes email, push, SMS, WhatsApp, device tokens, provider integrations, and delivery tracking.

The implemented baseline therefore establishes this separation:

```text
                           JualAntar API
                                |
                 +--------------+--------------+
                 |                             |
           Notifications                  Communications
                 |                             |
              In-App                    Out-of-App Delivery
                 |                             |
          persistent inbox       +------+-------+-------+
                                 |      |       |
                               Email    SMS   WhatsApp
```

The two modules must not share a single "notification" persistence model merely because both use the word notification.

### Notifications owns

- in-app notification records;
- recipient-scoped inbox;
- read/unread state;
- in-app notification lifecycle.

### Communications owns

- outbound delivery intent;
- channel selection;
- message dispatch;
- provider abstraction;
- delivery lifecycle;
- retryable delivery execution;
- provider response/error normalization;
- delivery observability.

This distinction is essential:

> An in-app notification is a user-facing inbox record. An out-of-app communication is a delivery operation to an external channel.

---

## 3. Problem Statement

JualAntar needs to communicate with users outside the application for transactional and security-sensitive scenarios, including:

- email verification links;
- password reset links;
- OTP delivery;
- account security messages;
- transactional email;
- future SMS messages;
- future WhatsApp messages.

The repository already contains outbound email behavior under IdentityAccess and Merchant, while the newly implemented Notifications module deliberately excludes outbound delivery.

Without a dedicated Communications module:

1. Each business module may implement its own mail/SMS/WhatsApp integration.
2. Provider-specific code may leak into domain workflows.
3. Retry and failure handling may be duplicated.
4. Message templates may become inconsistent.
5. Delivery history becomes difficult to audit.
6. Switching providers becomes expensive.
7. Security-sensitive delivery behavior can become tightly coupled to authentication or business workflows.

Communications becomes the single technical owner of outbound transactional message delivery.

---

## 4. Product Goal

Build a reusable outbound communication capability that can:

- accept a typed delivery request;
- resolve the target delivery address;
- render a message template or message payload;
- dispatch through a selected channel;
- execute asynchronously where appropriate;
- track delivery state;
- normalize provider errors;
- retry transient failures safely;
- expose a stable contract to internal application code;
- support Email first and additional channels later without changing business-domain code.

The module must be useful without integrating business workflows in the first implementation.

---

## 5. Scope

### 5.1 In Scope

- Generic Communications module.
- Email delivery capability.
- Provider abstraction for email.
- SMTP configuration support.
- Transactional message sending.
- Template-based email messages.
- Plain-text fallback for emails.
- HTML email support.
- Delivery request contract.
- Delivery attempt persistence.
- Delivery status lifecycle.
- Retry handling for transient failures.
- Idempotency support.
- Queue-based asynchronous dispatch.
- Provider error normalization.
- Structured observability.
- Email delivery testing.
- Local development support with the repository's SMTP-compatible Mailpit setup.
- Architecture boundary tests.
- API/application documentation for internal contracts.

### 5.2 Future-Ready, But Not Required in MVP

The design should not block:

- SMS;
- WhatsApp;
- additional email providers;
- provider failover;
- provider-specific templates;
- delivery callbacks/webhooks;
- delivery analytics.

### 5.3 Out of Scope

Do not implement in the first phase:

- FCM/mobile push notifications;
- browser push;
- device token registration;
- notification inbox UI;
- in-app notification persistence;
- notification preferences;
- marketing campaigns;
- bulk campaign management;
- admin broadcast composer;
- newsletter management;
- contact segmentation;
- business event listeners;
- Merchant-specific workflows;
- Customer-specific workflows;
- Order/Ride/Food/Mart/Driver-specific workflows;
- OTP generation;
- OTP verification;
- OTP attempt counting;
- OTP authentication policy;
- password-reset token generation;
- user identity lookup through direct domain-model imports;
- hard-coded business templates in Communications;
- provider-specific code inside business modules.

---

## 6. Architectural Decision

Create:

```text
app/Modules/Communications/
```

as a **generic platform module**.

Recommended module metadata:

```json
{
    "name": "Communications",
    "description": "Transactional out-of-app communication delivery.",
    "classification": "generic",
    "depends_on": [],
    "exposes_contracts": [
        "App\\Modules\\Communications\\Contracts\\Communications"
    ]
}
```

The module must not depend on:

- IdentityAccess Domain;
- Merchant Domain;
- Customer Domain;
- Payout Domain;
- Ordering Domain;
- Ride Domain;
- Food Domain;
- Mart Domain;
- Driver Domain.

Business modules must communicate with Communications only through stable Contracts.

---

## 7. Core Design Principles

### 7.1 Communications owns delivery, not business meaning

A producer decides that a message must be sent.

Communications decides how to deliver it.

For example:

```text
IdentityAccess
    |
    | "Send email verification"
    v
Communications Contract
    |
    v
Email Channel
    |
    v
SMTP Provider
```

Communications must not know why a verification email is required.

### 7.2 No direct access to IdentityAccess users

The Communications module must not import:

```text
App\Modules\IdentityAccess\Domain\Models\User
```

or query user records directly.

The producer must provide the destination or use a future recipient-resolution contract.

### 7.3 Channel-neutral contract

The public contract should represent communication intent rather than SMTP implementation details.

### 7.4 Provider-neutral implementation

Business code must not contain:

```text
Mailer::...
Symfony Mailer transport...
Resend...
Mailgun...
Twilio...
WhatsApp provider SDK...
```

The provider implementation stays behind a Communications channel boundary.

### 7.5 Delivery is not guaranteed synchronously

Email/SMS/WhatsApp delivery depends on external infrastructure.

The default production path should therefore be queue-based.

### 7.6 Security-sensitive data must be treated carefully

OTP values, verification tokens, reset links, and provider credentials must never be unnecessarily written to logs.

---

## 8. Terminology

| Term                  | Definition                                                      |
| --------------------- | --------------------------------------------------------------- |
| Communication         | An outbound message intended for an external channel.           |
| Channel               | A delivery medium such as Email, SMS, or WhatsApp.              |
| Provider              | External infrastructure used by a channel to deliver a message. |
| Recipient             | Destination address or phone number for delivery.               |
| Message Template      | Reusable content definition for a communication type.           |
| Delivery              | One requested outbound communication.                           |
| Delivery Attempt      | One actual attempt to submit a delivery to a provider.          |
| Delivery Status       | Current state of a delivery lifecycle.                          |
| Idempotency Key       | Stable key preventing accidental duplicate deliveries.          |
| Transactional Message | System-generated message triggered by application behavior.     |
| Transient Failure     | Failure that may succeed when retried.                          |
| Permanent Failure     | Failure that should not be retried automatically.               |

---

## 9. Recommended Module Structure

```text
app/Modules/Communications/
├── Application/
│   ├── Actions/
│   │   ├── SendCommunication.php
│   │   └── ProcessCommunicationDelivery.php
│   ├── Jobs/
│   │   └── DeliverCommunication.php
│   └── Services/
│       ├── CommunicationDispatcher.php
│       ├── CommunicationRenderer.php
│       └── DeliveryStatusService.php
│
├── Contracts/
│   ├── Communications.php
│   ├── Channels/
│   │   └── CommunicationChannel.php
│   └── DataTransferObjects/
│       ├── SendCommunicationData.php
│       └── CommunicationResult.php
│
├── Database/
│   ├── Factories/
│   │   ├── CommunicationFactory.php
│   │   └── DeliveryAttemptFactory.php
│   └── Migrations/
│       ├── *_create_communications_table.php
│       └── *_create_communication_delivery_attempts_table.php
│
├── Domain/
│   ├── Enums/
│   │   ├── CommunicationChannel.php
│   │   ├── CommunicationStatus.php
│   │   └── DeliveryAttemptStatus.php
│   ├── Exceptions/
│   │   ├── CommunicationException.php
│   │   └── PermanentDeliveryFailureException.php
│   └── Models/
│       ├── Communication.php
│       └── DeliveryAttempt.php
│
├── Infrastructure/
│   ├── Channels/
│   │   └── Email/
│   │       ├── EmailChannel.php
│   │       ├── EmailMessage.php
│   │       └── EmailProvider.php
│   ├── Providers/
│   │   └── Smtp/
│   │       └── SmtpEmailProvider.php
│   └── Templates/
│       └── ...
│
├── Routes/
│   └── api.php
│
├── Tests/
│   ├── Feature/
│   ├── Unit/
│   └── Architecture/
│
├── CommunicationsServiceProvider.php
└── module.json
```

The exact class count may be reduced when existing Laravel abstractions already provide the required behavior. The boundaries matter more than matching this exact tree.

---

## 10. Channel Strategy

Use a channel enum:

```php
enum CommunicationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
```

MVP implementation:

```text
Email
```

Future channels:

```text
SMS
WhatsApp
```

Do not implement SMS or WhatsApp merely to create an abstraction. The abstraction exists so their later implementation does not require rewriting business contracts.

---

## 11. Email Architecture

The first concrete channel is Email.

Recommended boundary:

```text
Communications
    |
    +-- EmailChannel
           |
           +-- EmailProvider
                  |
                  +-- SMTP
```

The `EmailChannel` is responsible for converting a generic communication into an email message.

The `EmailProvider` is responsible for communicating with the external mail transport.

Business modules must not directly invoke the SMTP provider.

### Existing repository support

The repository currently contains:

```text
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_FROM_ADDRESS="no-reply@jualantar.local"
MAIL_FROM_NAME="JualAntar"
```

and:

```text
QUEUE_CONNECTION=database
```

Local development already uses an SMTP-compatible Mailpit setup.

The PRD therefore assumes these existing facilities can be reused rather than introducing a second queue or mail configuration system.

---

## 12. Communication Data Model

### 12.1 Communications Table

Create a dedicated PostgreSQL schema:

```text
communications
```

Main table:

```text
communications.communications
```

Recommended columns:

| Column             | Type         | Nullable | Description                                           |
| ------------------ | ------------ | -------: | ----------------------------------------------------- |
| id                 | UUID         |       No | Primary key                                           |
| channel            | VARCHAR(20)  |       No | email/sms/whatsapp                                    |
| type               | VARCHAR(100) |       No | Machine-readable communication type                   |
| recipient_address  | TEXT         |       No | Email address or future channel destination           |
| subject            | VARCHAR(255) |      Yes | Email subject; null for channels that do not use one  |
| template           | VARCHAR(150) |      Yes | Template identifier                                   |
| payload            | JSONB        |      Yes | Template variables/message metadata                   |
| idempotency_key    | VARCHAR(255) |      Yes | Prevents duplicate sends                              |
| status             | VARCHAR(30)  |       No | Delivery lifecycle state                              |
| queued_at          | TIMESTAMPTZ  |      Yes | Queue dispatch timestamp                              |
| sent_at            | TIMESTAMPTZ  |      Yes | Provider accepted timestamp                           |
| delivered_at       | TIMESTAMPTZ  |      Yes | Provider-confirmed delivery timestamp, when available |
| failed_at          | TIMESTAMPTZ  |      Yes | Final failure timestamp                               |
| last_error_code    | VARCHAR(100) |      Yes | Normalized error code                                 |
| last_error_message | TEXT         |      Yes | Sanitized error detail                                |
| metadata           | JSONB        |      Yes | Non-sensitive technical metadata                      |
| created_at         | TIMESTAMPTZ  |       No | Creation timestamp                                    |
| updated_at         | TIMESTAMPTZ  |       No | Last update timestamp                                 |

Do not store authentication secrets, raw provider credentials, or sensitive token material in `payload` or `metadata`.

### 12.2 Delivery Attempts Table

Create:

```text
communications.communication_delivery_attempts
```

Recommended columns:

| Column              | Type         | Nullable | Description                |
| ------------------- | ------------ | -------: | -------------------------- |
| id                  | UUID         |       No | Primary key                |
| communication_id    | UUID         |       No | Parent communication ID    |
| attempt_number      | INTEGER      |       No | 1, 2, 3...                 |
| status              | VARCHAR(30)  |       No | attempted/succeeded/failed |
| provider            | VARCHAR(100) |       No | Provider identifier        |
| provider_message_id | VARCHAR(255) |      Yes | Provider reference         |
| error_code          | VARCHAR(100) |      Yes | Normalized provider error  |
| error_message       | TEXT         |      Yes | Sanitized error            |
| started_at          | TIMESTAMPTZ  |       No | Attempt start              |
| finished_at         | TIMESTAMPTZ  |      Yes | Attempt end                |
| created_at          | TIMESTAMPTZ  |       No | Record creation            |

A physical foreign key may be used between two tables inside the same Communications module.

No cross-module foreign key is permitted.

---

## 13. Delivery Status

Recommended state machine:

```text
pending
   |
   v
queued
   |
   v
processing
   |
   +---------> sent
   |             |
   |             v
   |         delivered
   |
   +---------> failed
```

Suggested enum:

```php
enum CommunicationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
```

Important distinction:

- `sent` means the provider accepted the message;
- `delivered` means the provider confirmed delivery, when that confirmation exists;
- some providers may never expose true delivery confirmation.

Do not falsely mark a message as `delivered` when the provider only confirms acceptance.

---

## 14. Delivery Attempt Status

Recommended:

```php
enum DeliveryAttemptStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
```

Every actual provider submission should create a delivery attempt.

This provides an audit trail for retry behavior without polluting the main Communications record.

---

## 15. Communication Type

Use a string, not an enum, so new transactional communication types can be introduced without modifying the module.

Rules:

- lowercase;
- maximum 100 characters;
- machine-readable;
- allowed characters: `a-z`, `0-9`, `.`, `_`, `-`.

Examples:

```text
identity.email_verification
identity.password_reset
identity.login_otp
identity.phone_otp
merchant.account_security
order.receipt
order.status_update
```

These are examples of future producers only.

Communications must not contain business-specific decision logic for those scenarios.

---

## 16. Public Contract

Create:

```text
app/Modules/Communications/Contracts/Communications.php
```

Recommended interface:

```php
interface Communications
{
    public function send(
        SendCommunicationData $data
    ): CommunicationResult;
}
```

The contract must not expose:

- Eloquent models;
- query builders;
- provider SDK types;
- Symfony Mailer types;
- Laravel Mail objects;
- database implementation details.

---

## 17. SendCommunicationData

Recommended:

```php
final readonly class SendCommunicationData
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public CommunicationChannel $channel,
        public string $type,
        public string $recipientAddress,
        public ?string $subject = null,
        public ?string $template = null,
        public ?array $payload = null,
        public ?string $idempotencyKey = null,
    ) {}
}
```

The payload contains template variables and communication-specific data.

For example:

```php
new SendCommunicationData(
    channel: CommunicationChannel::Email,
    type: 'identity.email_verification',
    recipientAddress: $email,
    subject: 'Verify your JualAntar email',
    template: 'identity.email-verification',
    payload: [
        'verification_url' => $verificationUrl,
    ],
    idempotencyKey: 'identity.email_verification:'.$verificationAttemptId,
);
```

This example documents contract usage only. The IdentityAccess integration is outside this implementation phase.

---

## 18. CommunicationResult

Recommended result fields:

- `communicationId`
- `status`
- `queued`
- `providerMessageId` when available
- `acceptedAt` when available

Do not expose provider-specific response objects.

The result must be serializable and safe to log at the appropriate level.

---

## 19. Message Templates

Transactional communications should use named templates rather than large HTML strings embedded inside business code.

Recommended:

```text
Infrastructure/Templates/
├── email/
│   ├── identity/
│   │   ├── email-verification.blade.php
│   │   └── password-reset.blade.php
│   └── layouts/
│       └── email.blade.php
```

However, template naming must remain generic at the Communications boundary.

The module must support:

- subject;
- HTML body;
- text fallback.

Templates should not contain business decisions.

---

## 20. Email Verification

Email verification is an intended consumer of Communications.

Flow:

```text
IdentityAccess
      |
      | generate verification URL
      |
      v
Communications Contract
      |
      v
Email Channel
      |
      v
SMTP Provider
      |
      v
User Inbox
```

Communications must not generate verification tokens.

IdentityAccess remains responsible for:

- creating/verifying the token;
- token expiry;
- verification state;
- determining whether a verification message should be sent.

Communications only sends the resulting communication.

---

## 21. OTP

OTP delivery is supported as a communication use case, but the OTP itself belongs to the security/identity workflow.

Correct boundary:

```text
IdentityAccess / Security
        |
        +-- generate OTP
        +-- persist/track OTP
        +-- validate OTP
        +-- enforce expiry
        +-- enforce attempt limits
        |
        v
Communications
        |
        +-- Email
        +-- future SMS
        +-- future WhatsApp
```

Communications must not:

- generate OTP codes;
- decide OTP length;
- validate OTP codes;
- decide how many attempts are allowed;
- determine authentication success;
- persist OTP state as its own security domain.

An OTP message may receive:

```php
payload: [
    'otp' => $otp,
    'expires_in_seconds' => 300,
]
```

but the implementation must treat this as security-sensitive and must not log it.

For SMS/WhatsApp, the same communication contract can later be used without changing the OTP domain behavior.

---

## 22. Queue Strategy

Out-of-app delivery should default to asynchronous processing.

Recommended flow:

```text
Application
    |
    v
Communications::send()
    |
    v
Create communication record
    |
    v
Dispatch DeliverCommunication job
    |
    v
Queue
    |
    v
EmailChannel
    |
    v
Provider
```

The call to `send()` should create the delivery intent and queue the work.

It should not require the HTTP request to remain open while waiting for an external provider.

### Why asynchronous?

- external network latency;
- provider outages;
- retries;
- resilience;
- predictable API response latency.

---

## 23. Queue Failure and Retry Policy

Transient provider failures should be retried.

Permanent failures should move the communication to `failed` without endless retries.

Potential transient failures:

- connection timeout;
- DNS/network error;
- rate limiting;
- provider temporary outage;
- HTTP 5xx provider responses.

Potential permanent failures:

- invalid recipient;
- malformed email address;
- provider rejected sender;
- blocked destination;
- invalid authentication configuration.

The exact provider error mapping should be implemented by the provider adapter, not by business modules.

Recommended initial retry policy:

```text
attempt 1
attempt 2
attempt 3
then final failure
```

Use backoff between attempts.

The exact backoff values should follow the repository's queue conventions and production observations rather than being hardcoded as business rules.

---

## 24. Idempotency

Out-of-app delivery must support an idempotency key.

Examples:

```text
identity.email_verification:{verification-id}
identity.password_reset:{reset-token-id}
identity.login_otp:{challenge-id}
```

Behavior:

1. No key → a new communication may be created.
2. Existing key → do not create another logical communication.
3. Existing queued/processing/sent communication → return the existing record or its current state.
4. Failed communication may be explicitly re-requested using a new key or a future retry/replay operation.

Idempotency must be enforced with a database-safe strategy.

Do not rely only on:

```text
SELECT then INSERT
```

without concurrency protection.

---

## 25. Recipient Address

Communications should store the delivery destination that was requested at send time.

For Email:

```text
recipient_address = user@example.com
```

This creates an immutable delivery target for that communication.

Do not resolve the current user email during a later queued job because the user's address may have changed between enqueue time and processing time.

---

## 26. Address Validation

For Email:

- validate standard email syntax;
- reject empty recipient;
- enforce reasonable maximum length;
- normalize where safe;
- do not silently modify user-entered addresses in unexpected ways.

Do not rely on business modules to validate email before calling Communications.

The Communications module must defend its own contract boundary.

---

## 27. API Exposure

The MVP must **not** expose a general public endpoint such as:

```http
POST /api/v1/communications
```

for arbitrary authenticated users.

Outbound communication is an internal application capability, not a user-generated resource.

A future privileged administration API may be introduced separately for operational tooling, but that is outside this PRD.

---

## 28. Internal Management / Observability Endpoints

No user-facing delivery history API is required in MVP.

Operations teams may later require a read-only internal view of:

- communication ID;
- status;
- channel;
- type;
- timestamps;
- provider;
- failure reason.

That should be handled by a future operational/audit PRD rather than exposing internal delivery data now.

---

## 29. Provider Abstraction

Recommended interface:

```php
interface EmailProvider
{
    public function send(EmailMessage $message): ProviderSendResult;
}
```

Example implementation:

```text
Infrastructure/
└── Providers/
    └── Smtp/
        └── SmtpEmailProvider.php
```

The provider interface should support:

- provider message ID;
- accepted timestamp;
- normalized provider errors.

Avoid returning framework-specific mail objects outside the Infrastructure layer.

---

## 30. Configuration

Configuration belongs to the Communications module or an appropriate shared configuration layer.

Email-related configuration should reuse the existing Laravel mail configuration rather than introducing duplicate variables.

Existing configuration includes:

```text
MAIL_MAILER
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
```

Provider secrets must come from environment/configuration and must never be persisted in the Communications database.

Future providers may introduce provider-specific environment variables.

---

## 31. Local Development

The current repository already uses:

```text
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

and has a Mailpit-compatible setup.

The Communications module should provide tests and local development instructions that work with this existing infrastructure.

For development:

```text
Application
   |
Communications
   |
SMTP
   |
Mailpit
```

No external provider account should be required for unit or feature tests.

---

## 32. Delivery Persistence

A communication record must be persisted before queue dispatch.

Recommended ordering:

```text
1. Validate request.
2. Check idempotency key.
3. Create communication as pending.
4. Dispatch delivery job.
5. Mark communication as queued.
```

Failure between steps must not create an invisible delivery.

Use a transaction/after-commit strategy where appropriate so jobs do not run against uncommitted records.

---

## 33. Transaction Boundaries

When the communication is requested inside another database transaction, the job must not process the delivery before the outer transaction commits if it depends on committed application state.

Recommended behavior:

```text
Database transaction
    |
    +-- create Communication
    |
    +-- commit
         |
         +-- dispatch/execute job
```

Do not send external messages for database work that is subsequently rolled back.

---

## 34. Delivery Consistency

External email providers are outside the database transaction.

Therefore, Communications must explicitly accept at-least-once delivery semantics.

This means:

- duplicate provider submission is possible after a worker crash;
- idempotency reduces duplicate logical messages;
- the provider adapter should use provider-supported idempotency capabilities where available;
- delivery attempt tracking must make replay behavior observable.

Do not claim exactly-once external delivery.

---

## 35. Error Handling

Reuse the repository's existing:

- `App\Shared\Http\ApiResponse`;
- `App\Shared\Result`;
- RFC 9457 / Problem Details conventions.

Recommended error categories:

```text
communication_invalid_recipient
communication_template_not_found
communication_provider_unavailable
communication_provider_rejected
communication_configuration_error
communication_duplicate
communication_delivery_failed
```

Avoid exposing provider internals to end users.

Sanitize provider errors before persistence/logging.

---

## 36. Observability

Structured logs should contain technical context such as:

- communication ID;
- channel;
- type;
- provider;
- attempt number;
- normalized error code;
- duration.

Do not log:

- OTP values;
- verification tokens;
- password-reset tokens;
- full email bodies;
- full sensitive payloads;
- provider API keys;
- SMTP credentials.

Potential metrics:

```text
communications.created
communications.queued
communications.sent
communications.delivered
communications.failed
communications.retry
communications.provider_errors
communications.delivery_duration
```

---

## 37. Security Requirements

### 37.1 Sensitive payload protection

Payloads may contain security-sensitive values.

The system should either:

- avoid persisting those values where possible; or
- encrypt them when persistent storage is necessary.

For MVP, security-sensitive values such as OTPs and verification tokens should preferably be rendered directly into the queued job payload or short-lived message context only when the repository's queue/security design can support it safely.

The implementation must explicitly document the chosen approach.

### 37.2 Credential protection

Provider credentials remain in environment/configuration.

Never store:

```text
SMTP password
API key
provider secret
OAuth client secret
```

in the database.

### 37.3 Recipient protection

Recipient addresses are sensitive operational data.

Access to communication records should be restricted to internal application code and future authorized operational tooling.

### 37.4 Content safety

Templates must be trusted application content.

Do not allow arbitrary users to submit HTML email templates in MVP.

---

## 38. Rate Limiting and Abuse Protection

The Communications module should expose hooks for future rate limiting but should not own user-facing authentication throttling logic.

Examples of producer-owned policy:

- maximum OTP requests per user;
- maximum password-reset requests per address;
- cooldown between verification emails.

Communications may enforce technical provider safety limits such as:

- provider rate limits;
- queue backpressure;
- global throughput safeguards.

Business/security throttling remains outside Communications.

---

## 39. Email Content Requirements

Transactional email should include:

- recognizable JualAntar sender identity;
- clear subject;
- concise message body;
- plain-text fallback;
- safe action URL where applicable;
- support/contact information when appropriate.

Do not place raw internal IDs in user-facing messages unless explicitly required.

---

## 40. Link Handling

For verification/password reset links:

- the producer owns token generation;
- the producer owns destination route semantics;
- Communications only transports the URL.

The email template should not generate URLs dynamically from domain knowledge.

Instead, pass a complete URL:

```text
verification_url
```

This keeps frontend/domain routing outside Communications.

---

## 41. Retry vs Business Replay

Automatic retry:

```text
same communication
same intent
new delivery attempt
```

Business replay:

```text
new application request
new communication
new idempotency key
```

Do not conflate these two concepts.

A failed email delivery attempt should not automatically create a second logical business message.

---

## 42. Delivery History and Auditability

The module should preserve enough information to answer:

- what was requested;
- through which channel;
- to which destination;
- when it was created;
- how many attempts occurred;
- which provider handled it;
- whether the provider accepted it;
- whether it failed;
- why it failed in normalized terms.

The full message body should not be treated as the primary audit record.

---

## 43. API / Contract Versioning

Internal Contracts should be designed as stable seams.

Do not leak:

```text
SMTP
Mailpit
Laravel Mail
provider SDK
provider-specific payload
```

into:

```text
SendCommunicationData
CommunicationResult
```

New channels should be additive.

Example:

```text
Communications::send(email)
Communications::send(sms)
Communications::send(whatsapp)
```

without forcing business modules to know how each channel operates.

---

## 44. Relationship to Laravel Notification System

Laravel's built-in Notification system may remain useful for implementation of individual channels, especially email.

However, Laravel Notification classes must remain an infrastructure/detail concern.

The business-facing contract should remain:

```text
App\Modules\Communications\Contracts\Communications
```

Business modules should not be required to depend directly on Laravel's:

```text
Illuminate\Notifications\Notification
```

This prevents framework-level delivery abstractions from becoming the public application boundary.

---

## 45. Relationship to In-App Notifications

An application may eventually perform both:

```text
Business Event
   |
   +----> Notifications Contract
   |          |
   |          +----> In-app
   |
   +----> Communications Contract
              |
              +----> Email
              +----> SMS
              +----> WhatsApp
```

This is a future orchestration concern.

Neither module should directly call the other in its own domain logic.

---

## 46. Integration Guardrail

This PRD is for building the Communications capability itself.

Do not integrate it into:

- IdentityAccess;
- Merchant;
- Customer;
- Payout;
- Ordering;
- Ride;
- Food;
- Mart;
- Driver.

Existing outbound email implementations may remain where they already exist until a separate migration/integration task is approved.

The implementation phase ends when Communications can independently accept and deliver a test transactional email.

---

## 47. Testing Strategy

### 47.1 Unit Tests

Cover:

- communication DTO validation;
- channel selection;
- email message rendering;
- template rendering;
- subject handling;
- plain-text fallback;
- provider result mapping;
- transient error classification;
- permanent error classification;
- status transitions;
- idempotency decisions;
- retry policy;
- sensitive-data logging redaction.

### 47.2 Feature Tests

Cover:

- communication creation;
- queue dispatch;
- email delivery through a fake provider;
- successful status transition;
- failed delivery;
- retry behavior;
- duplicate idempotency request;
- invalid recipient;
- missing template;
- provider rejection;
- provider temporary failure.

### 47.3 Integration Tests

An optional Mailpit-backed integration test should verify:

```text
Communications
   |
SMTP
   |
Mailpit
```

and assert:

- recipient;
- subject;
- body;
- sender;
- successful submission.

### 47.4 Architecture Tests

Enforce:

- Communications can use Shared infrastructure.
- Communications cannot import business Domain/Application internals.
- Business modules may depend only on Communications Contracts.
- Shared cannot depend on Communications.
- Provider SDKs exist only in Communications Infrastructure.
- Laravel Mail implementation details do not leak through Contracts.

---

## 48. Acceptance Criteria

### Architecture

- [ ] `app/Modules/Communications` exists.
- [ ] `module.json` exists.
- [ ] `CommunicationsServiceProvider` exists and is manually registered.
- [ ] Module has no business-module dependency.
- [ ] Public contract exists.
- [ ] Provider implementations are isolated.
- [ ] Business modules do not depend on provider classes.

### Database

- [ ] `communications` PostgreSQL schema exists.
- [ ] `communications.communications` exists.
- [ ] `communications.communication_delivery_attempts` exists.
- [ ] UUID primary keys exist.
- [ ] Idempotency strategy is database-safe.
- [ ] Required delivery indexes exist.
- [ ] No cross-module foreign key exists.
- [ ] Sensitive provider credentials are not persisted.

### Application

- [ ] `Communications::send()` works.
- [ ] Email channel works.
- [ ] Email provider abstraction works.
- [ ] Templates can be rendered.
- [ ] Plain-text email is supported.
- [ ] Queue dispatch works.
- [ ] Delivery status is persisted.
- [ ] Delivery attempts are persisted.
- [ ] Transient failure retries.
- [ ] Permanent failure does not retry indefinitely.
- [ ] Idempotency prevents duplicate logical communications.

### Security

- [ ] OTP values are not logged.
- [ ] Verification/reset tokens are not logged.
- [ ] Provider credentials are protected.
- [ ] Recipient data is not unnecessarily exposed.
- [ ] Arbitrary user HTML templates are not supported.
- [ ] Sensitive payload handling is documented.

### Testing

- [ ] Unit tests pass.
- [ ] Feature tests pass.
- [ ] Architecture tests pass.
- [ ] Mailpit/integration test passes when enabled.
- [ ] Existing project test suite remains green.

---

## 49. Recommended Implementation Order

1. Create `Communications` module.
2. Create `module.json`.
3. Create ServiceProvider.
4. Register ServiceProvider.
5. Add PostgreSQL `communications` schema.
6. Create communications table.
7. Create delivery attempts table.
8. Create enums.
9. Create domain models.
10. Create DTOs.
11. Create public contract.
12. Create communication dispatcher.
13. Create email channel contract.
14. Create SMTP email provider.
15. Create email renderer/template layer.
16. Create delivery job.
17. Implement queue dispatch.
18. Implement idempotency.
19. Implement retry classification.
20. Implement delivery status transitions.
21. Implement observability/redaction.
22. Add unit tests.
23. Add feature tests.
24. Add architecture tests.
25. Add optional Mailpit integration test.
26. Run formatter.
27. Run Communications tests.
28. Run complete project test suite.
29. Verify no business-module integration was introduced.

---

## 50. Non-Goals Guardrail

Reject changes that introduce:

- Merchant-specific business logic;
- Customer-specific business logic;
- order/ride/food/mart/driver workflows;
- direct User-model imports from Communications;
- OTP generation/validation;
- password-reset token generation;
- notification inbox functionality;
- push notification implementation;
- device token management;
- marketing campaigns;
- admin broadcast tooling;
- arbitrary user-authored HTML templates;
- provider credentials in database;
- provider SDK usage outside Communications Infrastructure;
- public arbitrary send endpoints;
- coupling Contracts to Laravel Mail or provider SDK classes.

---

## 51. Future Work

Separate PRDs should handle:

1. IdentityAccess integration for email verification.
2. IdentityAccess integration for OTP generation and verification.
3. Password reset communications.
4. SMS channel.
5. WhatsApp channel.
6. Push notification channel.
7. Provider webhooks for delivery confirmation.
8. Provider failover.
9. Notification/communication preferences.
10. Delivery analytics.
11. Operational delivery history.
12. Admin communication tooling.
13. Bulk messaging/campaigns.
14. Retention and archival.
15. Provider-specific rate limiting.
16. Communication localization.

---

## 52. Final Architectural Decision

JualAntar will use two separate generic capabilities:

```text
Notifications
    =
    persistent in-app notification inbox

Communications
    =
    transactional out-of-app message delivery
```

The first concrete Communications channel is **Email**.

The module must support future SMS and WhatsApp delivery without requiring business modules to know provider implementations.

The key boundary is:

```text
Business/Security capability
        |
        | communication intent
        v
Communications Contract
        |
        +-- Email
        +-- future SMS
        +-- future WhatsApp
```

For security-sensitive use cases such as OTP and email verification:

```text
Identity/Security capability
        |
        +-- generate token/OTP
        +-- validate token/OTP
        +-- own security state
        |
        v
Communications
        |
        +-- deliver message
```

Communications is therefore a **delivery bounded context**, not an authentication context and not an in-app notification inbox.

The implementation should end with a clean, provider-neutral Email delivery foundation that can be integrated into IdentityAccess and other business capabilities through separate, controlled integration PRDs.
