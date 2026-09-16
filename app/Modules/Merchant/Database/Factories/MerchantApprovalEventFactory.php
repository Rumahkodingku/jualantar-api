<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantApprovalEvent>
 */
class MerchantApprovalEventFactory extends Factory
{
    protected $model = MerchantApprovalEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_id' => MerchantApproval::factory(),
            'event_type' => MerchantApprovalEventType::ApplicationSubmitted,
            'actor_id' => (string) Str::uuid(),
            'metadata' => [],
        ];
    }
}
