<?php

use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attributes
 */
function notificationForRecipient(string $recipientId, array $attributes = []): Notification
{
    return Notification::factory()->forRecipient($recipientId)->create($attributes);
}

it('rejects unauthenticated access', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->patchJson('/api/v1/notifications/read-all')->assertUnauthorized();
});

it('lists only the authenticated recipient notifications', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id);
    notificationForRecipient((string) Str::uuid());

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure([
            'data' => [[
                'id', 'type', 'title', 'body', 'action_url',
                'priority', 'data', 'read_at', 'expires_at', 'created_at',
            ]],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
});

it('paginates notifications', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    Notification::factory()->forRecipient($user->id)->count(3)->create();

    $this->getJson('/api/v1/notifications?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2);
});

it('filters unread notifications', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id);
    notificationForRecipient($user->id, ['read_at' => now()]);

    $this->getJson('/api/v1/notifications?unread=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.read_at', null);
});

it('filters by type and priority', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id, [
        'type' => 'order.created',
        'priority' => NotificationPriority::High,
    ]);
    notificationForRecipient($user->id, [
        'type' => 'payout.completed',
        'priority' => NotificationPriority::Low,
    ]);

    $this->getJson('/api/v1/notifications?type=order.created')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/notifications?priority=low')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('excludes expired notifications unless requested', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id);
    notificationForRecipient($user->id, ['expires_at' => now()->subDay()]);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/v1/notifications?include_expired=true')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('returns a recipient owned notification detail', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $notification = notificationForRecipient($user->id);

    $this->getJson("/api/v1/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id);
});

it('hides notifications owned by another recipient', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $other = notificationForRecipient((string) Str::uuid());

    $this->getJson("/api/v1/notifications/{$other->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'notification_not_found');
});

it('hides expired notifications', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $expired = notificationForRecipient($user->id, ['expires_at' => now()->subDay()]);

    $this->getJson("/api/v1/notifications/{$expired->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'notification_not_found');
});

it('marks a notification as read idempotently', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $notification = notificationForRecipient($user->id);

    $readAt = $this->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->json('data.read_at');

    $this->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read_at', $readAt);
});

it('marks all active unread notifications as read', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    Notification::factory()->forRecipient($user->id)->count(2)->create();
    notificationForRecipient($user->id, ['expires_at' => now()->subDay()]);

    $this->patchJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.count', 2);
});

it('returns the unread count excluding expired notifications', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id);
    notificationForRecipient($user->id, ['read_at' => now()]);
    notificationForRecipient($user->id, ['expires_at' => now()->subDay()]);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('deletes an owned notification', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $notification = notificationForRecipient($user->id);

    $this->deleteJson("/api/v1/notifications/{$notification->id}")
        ->assertNoContent();

    expect(Notification::query()->whereKey($notification->id)->exists())->toBeFalse();
});

it('cannot delete another recipient notification', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $other = notificationForRecipient((string) Str::uuid());

    $this->deleteJson("/api/v1/notifications/{$other->id}")
        ->assertNotFound();

    expect(Notification::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('validates list query parameters', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications?per_page=500')->assertUnprocessable();
    $this->getJson('/api/v1/notifications?priority=extreme')->assertUnprocessable();
    $this->getJson('/api/v1/notifications?type=Invalid Type')->assertUnprocessable();
});

it('returns notification content as inert data', function () {
    $user = $this->plainUser();
    Sanctum::actingAs($user);

    notificationForRecipient($user->id, ['body' => '<script>alert(1)</script>']);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.body', '<script>alert(1)</script>');
});
