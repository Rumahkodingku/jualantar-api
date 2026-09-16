<?php

namespace App\Modules\Merchant\Application\Approval\Queries;

use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Support\Collection;

final class SummarizeApprovals
{
    /**
     * @return array{by_status: array<string, int>, unassigned: int, assigned_to_me: int}
     */
    public function __invoke(string $actorId): array
    {
        $counts = MerchantApplication::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = Collection::make(MerchantApplicationStatus::values())
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])
            ->all();

        $unassigned = MerchantApproval::query()
            ->whereNull('assigned_to')
            ->whereHas('application', fn ($query) => $query->where('status', MerchantApplicationStatus::Pending->value))
            ->count();

        $assignedToMe = MerchantApproval::query()
            ->where('assigned_to', $actorId)
            ->whereHas('application', fn ($query) => $query->where('status', MerchantApplicationStatus::InReview->value))
            ->count();

        return [
            'by_status' => $byStatus,
            'unassigned' => $unassigned,
            'assigned_to_me' => $assignedToMe,
        ];
    }
}
