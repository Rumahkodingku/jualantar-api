<?php

namespace App\Modules\Merchant\Application\Approval\Queries;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Support\Collection;

final class ShowApproval
{
    /**
     * @return array{
     *     approval: MerchantApproval,
     *     merchant: Merchant,
     *     snapshot: MerchantApplicationSnapshot|null,
     *     reviews: Collection<int, mixed>,
     *     revisions: Collection<int, mixed>,
     *     events: Collection<int, mixed>
     * }
     */
    public function __invoke(MerchantApproval $approval): array
    {
        $approval->load(['application.merchant', 'reviews', 'revisions.items', 'events']);

        $snapshot = MerchantApplicationSnapshot::query()
            ->where('application_id', $approval->application_id)
            ->orderByDesc('version')
            ->first();

        return [
            'approval' => $approval,
            'merchant' => $approval->application->merchant,
            'snapshot' => $snapshot,
            'reviews' => $approval->reviews,
            'revisions' => $approval->revisions->sortByDesc('requested_at')->values(),
            'events' => $approval->events->sortBy('created_at')->values(),
        ];
    }
}
