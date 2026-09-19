<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Contracts\DataTransferObjects\CreateNotificationData;
use App\Modules\Notifications\Contracts\DataTransferObjects\NotificationData;
use App\Modules\Notifications\Domain\Models\Notification;
use App\Shared\Result\Result;
use App\Shared\Result\ResultError;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateNotification
{
    private const MAX_DATA_BYTES = 65536;

    public function __invoke(CreateNotificationData $data): Result
    {
        if ($invalid = $this->validate($data)) {
            return $invalid;
        }

        if ($data->deduplicationKey !== null) {
            $existing = $this->findExisting($data->recipientId, $data->deduplicationKey);

            if ($existing !== null) {
                return Result::ok(NotificationData::fromModel($existing));
            }
        }

        try {
            $notification = Notification::query()->create([
                'recipient_id' => $data->recipientId,
                'type' => $data->type,
                'title' => $data->title,
                'body' => $data->body,
                'action_url' => $data->actionUrl,
                'priority' => $data->priority,
                'data' => $data->data,
                'deduplication_key' => $data->deduplicationKey,
                'expires_at' => $data->expiresAt,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            $existing = $data->deduplicationKey === null
                ? null
                : $this->findExisting($data->recipientId, $data->deduplicationKey);

            if ($existing === null) {
                throw $e;
            }

            return Result::ok(NotificationData::fromModel($existing));
        }

        return Result::ok(NotificationData::fromModel($notification));
    }

    private function findExisting(string $recipientId, string $deduplicationKey): ?Notification
    {
        return Notification::query()
            ->forRecipient($recipientId)
            ->where('deduplication_key', $deduplicationKey)
            ->first();
    }

    private function validate(CreateNotificationData $data): ?Result
    {
        if ($data->type === '' || strlen($data->type) > 100 || preg_match('/^[a-z0-9._-]+$/', $data->type) !== 1) {
            return $this->invalid(
                'type',
                'The type must be lowercase and contain only a-z, 0-9, dot, underscore or dash.',
            );
        }

        if ($data->title === '' || strlen($data->title) > 255) {
            return $this->invalid('title', 'The title is required and may not exceed 255 characters.');
        }

        if ($data->body === '') {
            return $this->invalid('body', 'The body is required.');
        }

        if ($data->actionUrl !== null && filter_var($data->actionUrl, FILTER_VALIDATE_URL) === false) {
            return $this->invalid('action_url', 'The action URL must be a valid URL.');
        }

        if ($data->deduplicationKey !== null && strlen($data->deduplicationKey) > 255) {
            return $this->invalid('deduplication_key', 'The deduplication key may not exceed 255 characters.');
        }

        if ($data->data !== null && strlen((string) json_encode($data->data)) > self::MAX_DATA_BYTES) {
            return $this->invalid('data', 'The data payload may not exceed 64 KB.');
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
