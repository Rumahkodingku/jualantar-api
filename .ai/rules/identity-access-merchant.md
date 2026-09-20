---
paths:
  - 'app/Modules/{IdentityAccess,Merchant}/**'
---

# Identity Access Merchant

## Producer integration pattern for Communications
Out-of-app email is sent only through App\Modules\Communications\Contracts\Communications; these modules must not use Illuminate Mail/Notifications directly. Producer owns its own Blade templates (registered via loadViewsFrom + listed in config/communications.php 'templates') and passes a fully-built URL in the payload (IdentityAccess owns verification token/signature via VerificationUrlBuilder). Delivery is best-effort and dispatched AFTER the business transaction commits (never inside DB::transaction), wrapped in try/catch + Log::warning so a send failure never rolls back business state. Idempotency: verification sends omit the key (resend allowed); approval outcomes use a stable key like merchant.application.approved:{applicationId}.
