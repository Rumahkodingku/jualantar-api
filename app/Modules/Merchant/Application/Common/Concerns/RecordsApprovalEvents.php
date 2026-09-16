<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalEvent;

trait RecordsApprovalEvents
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        MerchantApproval $approval,
        MerchantApprovalEventType $type,
        ?string $actorId,
        array $metadata = [],
    ): MerchantApprovalEvent {
        return MerchantApprovalEvent::query()->create([
            'approval_id' => $approval->id,
            'event_type' => $type,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);
    }
}
