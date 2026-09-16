<?php

namespace App\Modules\Merchant\Application\Approval\Queries;

use App\Modules\Merchant\Domain\Models\MerchantApproval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListApprovals
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __invoke(array $filters, string $actorId): LengthAwarePaginator
    {
        $query = MerchantApproval::query()
            ->with(['application.merchant'])
            ->join('merchant.merchant_applications as applications', 'applications.id', '=', 'merchant.merchant_approvals.application_id')
            ->join('merchant.merchants as merchants', 'merchants.id', '=', 'applications.merchant_id')
            ->select('merchant.merchant_approvals.*');

        if (filled($filters['status'] ?? null)) {
            $query->where('applications.status', $filters['status']);
        }

        $assignment = $filters['assignment'] ?? null;

        if ($assignment === 'me') {
            $query->where('merchant.merchant_approvals.assigned_to', $actorId);
        } elseif ($assignment === 'unassigned') {
            $query->whereNull('merchant.merchant_approvals.assigned_to');
        } elseif (filled($assignment)) {
            $query->where('merchant.merchant_approvals.assigned_to', $assignment);
        }

        if (filled($filters['search'] ?? null)) {
            $search = (string) $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('merchants.business_name', 'ilike', "%{$search}%")
                    ->orWhere('applications.application_number', 'ilike', "%{$search}%");
            });
        }

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $order = (string) ($filters['order'] ?? 'desc');

        $query->orderBy('merchant.merchant_approvals.'.$sort, $order);

        return $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }
}
