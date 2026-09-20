<?php

namespace App\Modules\Communications\Application\Actions;

use App\Modules\Communications\Application\Jobs\DeliverCommunication;
use App\Modules\Communications\Application\Services\DeliveryStatusService;
use App\Modules\Communications\Contracts\DataTransferObjects\CommunicationResult;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Contracts\Enums\CommunicationChannel;
use App\Modules\Communications\Contracts\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Models\Communication;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use Illuminate\Database\UniqueConstraintViolationException;

final class SendCommunication
{
    private const MAX_PAYLOAD_BYTES = 65536;

    private const MAX_RECIPIENT_LENGTH = 320;

    public function __construct(private readonly DeliveryStatusService $deliveryStatus) {}

    public function __invoke(SendCommunicationData $data): Result
    {
        if ($invalid = $this->validate($data)) {
            return $invalid;
        }

        if ($data->idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($data->idempotencyKey);

            if ($existing !== null) {
                return Result::ok($this->resultFor($existing));
            }
        }

        try {
            $communication = Communication::query()->create([
                'channel' => $data->channel,
                'type' => $data->type,
                'recipient_address' => $data->recipientAddress,
                'subject' => $data->subject,
                'template' => $data->template,
                'payload' => $data->payload,
                'idempotency_key' => $data->idempotencyKey,
                'status' => CommunicationStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $data->idempotencyKey === null
                ? null
                : $this->findByIdempotencyKey($data->idempotencyKey);

            if ($existing === null) {
                throw $e;
            }

            return Result::ok($this->resultFor($existing));
        }

        // Persist before dispatch so a failure cannot create an invisible
        // delivery; the job defers itself until the outer transaction commits.
        DeliverCommunication::dispatch($communication->id)
            ->onQueue((string) config('communications.queue', 'default'));

        $communication->refresh();

        if ($communication->status === CommunicationStatus::Pending) {
            $this->deliveryStatus->markQueued($communication);
            $communication->refresh();
        }

        return Result::ok($this->resultFor($communication));
    }

    private function findByIdempotencyKey(string $key): ?Communication
    {
        return Communication::query()->where('idempotency_key', $key)->first();
    }

    private function resultFor(Communication $communication): CommunicationResult
    {
        return new CommunicationResult(
            communicationId: $communication->id,
            status: $communication->status,
            queued: $communication->status !== CommunicationStatus::Pending,
        );
    }

    private function validate(SendCommunicationData $data): ?Result
    {
        if ($data->channel !== CommunicationChannel::Email) {
            return $this->invalid('channel', "The [{$data->channel->value}] channel is not supported yet.");
        }

        if ($data->type === '' || strlen($data->type) > 100 || preg_match('/^[a-z0-9._-]+$/', $data->type) !== 1) {
            return $this->invalid(
                'type',
                'The type must be lowercase and contain only a-z, 0-9, dot, underscore or dash.',
            );
        }

        if ($data->recipientAddress === '' || strlen($data->recipientAddress) > self::MAX_RECIPIENT_LENGTH) {
            return $this->invalid(
                'recipient_address',
                'The recipient address is required and may not exceed 320 characters.',
            );
        }

        if (filter_var($data->recipientAddress, FILTER_VALIDATE_EMAIL) === false) {
            return $this->invalid('recipient_address', 'The recipient address must be a valid email address.');
        }

        if ($data->template === null || $data->template === '') {
            return $this->invalid('template', 'A template is required for email communications.');
        }

        if (strlen($data->template) > 150) {
            return $this->invalid('template', 'The template identifier may not exceed 150 characters.');
        }

        if ($data->subject !== null && strlen($data->subject) > 255) {
            return $this->invalid('subject', 'The subject may not exceed 255 characters.');
        }

        if ($data->idempotencyKey !== null && strlen($data->idempotencyKey) > 255) {
            return $this->invalid('idempotency_key', 'The idempotency key may not exceed 255 characters.');
        }

        if ($data->payload !== null && strlen((string) json_encode($data->payload)) > self::MAX_PAYLOAD_BYTES) {
            return $this->invalid('payload', 'The payload may not exceed 64 KB.');
        }

        return null;
    }

    private function invalid(string $field, string $message): Result
    {
        return Result::err(new ResultError(
            code: 'unprocessable_entity',
            message: $message,
            status: 422,
            title: 'Unprocessable Entity',
            fields: [$field => [$message]],
        ));
    }
}
