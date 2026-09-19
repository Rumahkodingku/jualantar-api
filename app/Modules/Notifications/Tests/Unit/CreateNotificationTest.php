<?php

use App\Modules\Notifications\Application\Actions\CreateNotification;
use App\Modules\Notifications\Contracts\DataTransferObjects\CreateNotificationData;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function notificationPayload(array $overrides = []): CreateNotificationData
{
    return new CreateNotificationData(...array_merge([
        'recipientId' => (string) Str::uuid(),
        'type' => 'general.information',
        'title' => 'Judul notifikasi',
        'body' => 'Isi notifikasi.',
    ], $overrides));
}

it('creates a notification with default priority and state', function () {
    $recipientId = (string) Str::uuid();

    $result = app(CreateNotification::class)(notificationPayload(['recipientId' => $recipientId]));

    expect($result->isOk())->toBeTrue();

    $data = $result->unwrap();

    expect($data->priority)->toBe('normal')
        ->and($data->readAt)->toBeNull()
        ->and($data->expiresAt)->toBeNull()
        ->and(Notification::query()->whereKey($data->id)->where('recipient_id', $recipientId)->exists())->toBeTrue();
});

it('creates a notification through the public contract', function () {
    $data = app(Notifications::class)->create(new CreateNotificationData(
        recipientId: (string) Str::uuid(),
        type: 'order.created',
        title: 'Pesanan dibuat',
        body: 'Pesanan Anda telah dibuat.',
    ));

    expect($data->id)->not->toBeEmpty()
        ->and($data->type)->toBe('order.created')
        ->and($data->priority)->toBe('normal');
});

it('returns the existing notification for a repeated deduplication key', function () {
    $recipientId = (string) Str::uuid();
    $action = app(CreateNotification::class);
    $key = 'merchant.application.approved:1';

    $first = $action(notificationPayload(['recipientId' => $recipientId, 'deduplicationKey' => $key]))->unwrap();
    $second = $action(notificationPayload(['recipientId' => $recipientId, 'deduplicationKey' => $key]))->unwrap();

    expect($second->id)->toBe($first->id)
        ->and(Notification::query()->count())->toBe(1);
});

it('scopes deduplication to the recipient', function () {
    $action = app(CreateNotification::class);
    $key = 'order.created:99';

    $first = $action(notificationPayload(['recipientId' => (string) Str::uuid(), 'deduplicationKey' => $key]))->unwrap();
    $second = $action(notificationPayload(['recipientId' => (string) Str::uuid(), 'deduplicationKey' => $key]))->unwrap();

    expect($first->id)->not->toBe($second->id)
        ->and(Notification::query()->count())->toBe(2);
});

it('rejects an invalid notification type', function () {
    $result = app(CreateNotification::class)(notificationPayload(['type' => 'Invalid Type!']));

    expect($result->isErr())->toBeTrue()
        ->and($result->error()->code)->toBe('unprocessable_entity');
});

it('rejects an action url that is not a valid url', function () {
    $result = app(CreateNotification::class)(notificationPayload(['actionUrl' => 'not-a-url']));

    expect($result->isErr())->toBeTrue();
});

it('rejects a payload larger than 64 KB', function () {
    $result = app(CreateNotification::class)(notificationPayload([
        'data' => ['blob' => str_repeat('a', 70000)],
    ]));

    expect($result->isErr())->toBeTrue();
});
