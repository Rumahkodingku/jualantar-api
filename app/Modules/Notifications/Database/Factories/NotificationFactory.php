<?php

namespace App\Modules\Notifications\Database\Factories;

use App\Modules\Notifications\Domain\Enums\NotificationPriority;
use App\Modules\Notifications\Domain\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => (string) Str::uuid(),
            // Generic placeholder type; business modules define their own types.
            'type' => 'general.information',
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'action_url' => null,
            'priority' => NotificationPriority::Normal,
            'data' => null,
            'deduplication_key' => null,
            'read_at' => null,
            'expires_at' => null,
        ];
    }

    public function forRecipient(string $recipientId): static
    {
        return $this->state(fn (): array => ['recipient_id' => $recipientId]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function priority(NotificationPriority $priority): static
    {
        return $this->state(fn (): array => ['priority' => $priority]);
    }

    public function type(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
