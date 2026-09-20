<?php

namespace App\Modules\Communications\Application\Services;

use App\Modules\Communications\Contracts\Channels\ProviderSendResult;
use App\Modules\Communications\Contracts\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Enums\DeliveryAttemptStatus;
use App\Modules\Communications\Domain\Models\Communication;
use App\Modules\Communications\Domain\Models\DeliveryAttempt;
use App\Shared\Exceptions\ApiException;
use Throwable;

/**
 * Owns the delivery status lifecycle and delivery-attempt audit trail.
 */
final class DeliveryStatusService
{
    public function markQueued(Communication $communication): void
    {
        $communication->update([
            'status' => CommunicationStatus::Queued,
            'queued_at' => now(),
        ]);
    }

    public function markProcessing(Communication $communication): void
    {
        $communication->update(['status' => CommunicationStatus::Processing]);
    }

    public function startAttempt(Communication $communication, string $provider): DeliveryAttempt
    {
        $nextNumber = (int) $communication->attempts()->max('attempt_number') + 1;

        return $communication->attempts()->create([
            'attempt_number' => $nextNumber,
            'status' => DeliveryAttemptStatus::Started,
            'provider' => $provider,
            'started_at' => now(),
        ]);
    }

    public function markAttemptSucceeded(DeliveryAttempt $attempt, ProviderSendResult $result): void
    {
        $attempt->update([
            'status' => DeliveryAttemptStatus::Succeeded,
            'provider_message_id' => $result->providerMessageId,
            'finished_at' => now(),
        ]);
    }

    public function markAttemptFailed(DeliveryAttempt $attempt, ProviderSendResult $result): void
    {
        $attempt->update([
            'status' => DeliveryAttemptStatus::Failed,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'finished_at' => now(),
        ]);
    }

    public function markSent(Communication $communication, ProviderSendResult $result): void
    {
        if ($this->isTerminal($communication)) {
            return;
        }

        $communication->update([
            'status' => CommunicationStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function markFailed(Communication $communication, ProviderSendResult $result): void
    {
        if ($this->isTerminal($communication)) {
            return;
        }

        $communication->update([
            'status' => CommunicationStatus::Failed,
            'failed_at' => now(),
            'last_error_code' => $result->errorCode,
            'last_error_message' => $result->errorMessage,
        ]);
    }

    public function markFailedFromThrowable(Communication $communication, ?Throwable $exception): void
    {
        if ($this->isTerminal($communication)) {
            return;
        }

        $communication->update([
            'status' => CommunicationStatus::Failed,
            'failed_at' => now(),
            'last_error_code' => $exception instanceof ApiException
                ? $exception->problemCode()
                : 'communication_delivery_failed',
            'last_error_message' => $exception?->getMessage() ?: 'The communication could not be delivered.',
        ]);
    }

    private function isTerminal(Communication $communication): bool
    {
        return in_array($communication->status, [
            CommunicationStatus::Sent,
            CommunicationStatus::Delivered,
            CommunicationStatus::Failed,
        ], true);
    }
}
