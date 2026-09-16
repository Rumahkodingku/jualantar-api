<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantApplicationSnapshot>
 */
class MerchantApplicationSnapshotFactory extends Factory
{
    protected $model = MerchantApplicationSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => MerchantApplication::factory(),
            'version' => 1,
            'snapshot' => [],
            'submitted_at' => now(),
        ];
    }

    public function forApplication(string $applicationId, int $version = 1): static
    {
        return $this->state(fn (): array => [
            'application_id' => $applicationId,
            'version' => $version,
        ]);
    }
}
