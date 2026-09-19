<?php

namespace App\Modules\Communications\Application\Jobs;

use App\Modules\Communications\Application\Services\CommunicationDispatcher;
use App\Modules\Communications\Application\Services\DeliveryStatusService;
use App\Modules\Communications\Domain\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Exceptions\CommunicationException;
use App\Modules\Communications\Domain\Exceptions\PermanentDeliveryFailureException;
use App\Modules\Communications\Domain\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Delivers one communication through its channel.
 *
 * The serialized job carries only the communication ID; sensitive payload
 * values stay encrypted in the database and are never placed on the queue.
 */
final class DeliverCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /**
     * @var array<int, int>
     */
    public array $backoff;

    public function __construct(public readonly string $communicationId)
    {
        $this->tries = (int) config('communications.tries', 3);
        $this->backoff = (array) config('communications.backoff', [60, 300]);
        $this->afterCommit = true;
    }

    public function handle(
        CommunicationDispatcher $dispatcher,
        DeliveryStatusService $deliveryStatus,
    ): void {
        $communication = Communication::query()->find($this->communicationId);

        if ($communication === null) {
            return;
        }

        if (in_array($communication->status, [
            CommunicationStatus::Sent,
            CommunicationStatus::Delivered,
        ], true)) {
            return;
        }

        $deliveryStatus->markProcessing($communication);
        $attempt = $deliveryStatus->startAttempt($communication, $dispatcher->providerName($communication));

        $result = $dispatcher->dispatch($communication);

        if ($result->accepted) {
            $deliveryStatus->markAttemptSucceeded($attempt, $result);
            $deliveryStatus->markSent($communication, $result);

            return;
        }

        $deliveryStatus->markAttemptFailed($attempt, $result);

        if ($result->permanent) {
            $deliveryStatus->markFailed($communication, $result);

            $this->fail(new PermanentDeliveryFailureException(
                $result->errorMessage ?? 'The provider permanently rejected the communication.',
                $result->errorCode ?? 'communication_provider_rejected',
            ));

            return;
        }

        // Transient failure: rethrow so the queue retries with backoff.
        throw CommunicationException::providerUnavailable(
            $result->errorMessage ?? 'The provider is temporarily unavailable.',
        );
    }

    public function failed(?Throwable $exception): void
    {
        $communication = Communication::query()->find($this->communicationId);

        if ($communication === null) {
            return;
        }

        app(DeliveryStatusService::class)->markFailedFromThrowable($communication, $exception);
    }
}
