<?php

namespace App\Modules\Communications\Database\Factories;

use App\Modules\Communications\Domain\Enums\DeliveryAttemptStatus;
use App\Modules\Communications\Domain\Models\Communication;
use App\Modules\Communications\Domain\Models\DeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAttempt>
 */
class DeliveryAttemptFactory extends Factory
{
    protected $model = DeliveryAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'communication_id' => Communication::factory(),
            'attempt_number' => 1,
            'status' => DeliveryAttemptStatus::Started,
            'provider' => 'smtp',
            'provider_message_id' => null,
            'error_code' => null,
            'error_message' => null,
            'started_at' => now(),
            'finished_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryAttemptStatus::Succeeded,
            'provider_message_id' => 'msg_'.fake()->uuid(),
            'finished_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryAttemptStatus::Failed,
            'error_code' => 'communication_provider_unavailable',
            'error_message' => 'The provider is temporarily unavailable.',
            'finished_at' => now(),
        ]);
    }
}
