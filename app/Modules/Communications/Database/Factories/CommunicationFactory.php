<?php

namespace App\Modules\Communications\Database\Factories;

use App\Modules\Communications\Contracts\Enums\CommunicationChannel;
use App\Modules\Communications\Contracts\Enums\CommunicationStatus;
use App\Modules\Communications\Domain\Models\Communication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Communication>
 */
class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => CommunicationChannel::Email,
            'type' => 'system.test',
            'recipient_address' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'template' => 'email.generic',
            'payload' => [
                'title' => fake()->sentence(),
                'body' => fake()->paragraph(),
            ],
            'idempotency_key' => null,
            'status' => CommunicationStatus::Pending,
            'metadata' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::Queued,
            'queued_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::Sent,
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => CommunicationStatus::Failed,
            'failed_at' => now(),
            'last_error_code' => 'communication_provider_unavailable',
            'last_error_message' => 'The provider is temporarily unavailable.',
        ]);
    }
}
