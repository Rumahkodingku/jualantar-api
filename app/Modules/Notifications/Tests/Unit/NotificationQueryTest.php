<?php

use App\Modules\Notifications\Application\Actions\DeleteNotification;
use App\Modules\Notifications\Application\Actions\GetUnreadNotificationCount;
use App\Modules\Notifications\Application\Actions\MarkAllNotificationsAsRead;
use App\Modules\Notifications\Application\Actions\MarkNotificationAsRead;
use App\Modules\Notifications\Application\Services\NotificationQueryService;
use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function newNotificationRecipient(): string
{
    return (string) Str::uuid();
}

it('lists notifications for the recipient newest first', function () {
    $recipient = newNotificationRecipient();

    $older = Notification::factory()->forRecipient($recipient)->create();
    $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();

    $newer = Notification::factory()->forRecipient($recipient)->create();

    Notification::factory()->forRecipient(newNotificationRecipient())->create();

    $paginator = app(NotificationQueryService::class)->list($recipient, []);

    expect($paginator->total())->toBe(2)
        ->and($paginator->items()[0]->id)->toBe($newer->id)
        ->and($paginator->items()[1]->id)->toBe($older->id);
});

it('excludes expired notifications by default and can include them', function () {
    $recipient = newNotificationRecipient();
    Notification::factory()->forRecipient($recipient)->create();
    Notification::factory()->forRecipient($recipient)->expired()->create();

    $service = app(NotificationQueryService::class);

    expect($service->list($recipient, [])->total())->toBe(1)
        ->and($service->list($recipient, ['include_expired' => 'true'])->total())->toBe(2);
});

it('filters by unread, type and priority', function () {
    $recipient = newNotificationRecipient();

    Notification::factory()->forRecipient($recipient)->read()
        ->type('order.created')->priority(NotificationPriority::High)->create();

    $target = Notification::factory()->forRecipient($recipient)
        ->type('order.created')->priority(NotificationPriority::Low)->create();

    Notification::factory()->forRecipient($recipient)->type('payout.completed')->create();

    $service = app(NotificationQueryService::class);

    expect($service->list($recipient, ['unread' => 'true'])->total())->toBe(2)
        ->and($service->list($recipient, ['type' => 'order.created'])->total())->toBe(2)
        ->and($service->list($recipient, ['priority' => 'low'])->total())->toBe(1)
        ->and($service->list($recipient, [
            'unread' => 'true',
            'type' => 'order.created',
            'priority' => 'low',
        ])->items()[0]->id)->toBe($target->id);
});

it('caps the page size at 100', function () {
    $recipient = newNotificationRecipient();
    Notification::factory()->forRecipient($recipient)->count(3)->create();

    $paginator = app(NotificationQueryService::class)->list($recipient, ['per_page' => 500]);

    expect($paginator->perPage())->toBe(100);
});

it('marks a notification as read once and preserves the timestamp', function () {
    $notification = Notification::factory()->create();
    $action = app(MarkNotificationAsRead::class);

    $first = $action($notification)->unwrap();
    $firstReadAt = $first->readAt;

    $second = $action($notification->refresh())->unwrap();

    expect($firstReadAt)->not->toBeNull()
        ->and($second->readAt?->getTimestamp())->toBe($firstReadAt?->getTimestamp())
        ->and($notification->refresh()->read_at)->not->toBeNull();
});

it('marks only active unread notifications as read in bulk', function () {
    $recipient = newNotificationRecipient();

    $unread = Notification::factory()->forRecipient($recipient)->create();
    $read = Notification::factory()->forRecipient($recipient)->read()->create();
    $expired = Notification::factory()->forRecipient($recipient)->expired()->create();

    Notification::factory()->forRecipient(newNotificationRecipient())->create();

    $affected = app(MarkAllNotificationsAsRead::class)($recipient)->unwrap();

    expect($affected)->toBe(1)
        ->and($unread->refresh()->read_at)->not->toBeNull()
        ->and($read->refresh()->read_at)->not->toBeNull()
        ->and($expired->refresh()->read_at)->toBeNull();
});

it('counts only active unread notifications', function () {
    $recipient = newNotificationRecipient();
    Notification::factory()->forRecipient($recipient)->create();
    Notification::factory()->forRecipient($recipient)->read()->create();
    Notification::factory()->forRecipient($recipient)->expired()->create();

    expect(app(GetUnreadNotificationCount::class)($recipient)->unwrap())->toBe(1);
});

it('deletes a notification', function () {
    $notification = Notification::factory()->create();

    app(DeleteNotification::class)($notification);

    expect(Notification::query()->whereKey($notification->id)->exists())->toBeFalse();
});
