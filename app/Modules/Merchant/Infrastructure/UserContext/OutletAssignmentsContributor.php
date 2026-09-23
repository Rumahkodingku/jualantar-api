<?php

namespace App\Modules\Merchant\Infrastructure\UserContext;

use App\Modules\IdentityAccess\Contracts\UserContextContributor;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;

/**
 * Exposes the user's outlet-scoped roles to /auth/me. Outlet roles live only on
 * merchant.merchant_outlet_users.role and are never granted as global Spatie
 * roles, so the frontend cannot read them from the global permissions list.
 */
final class OutletAssignmentsContributor implements UserContextContributor
{
    /**
     * @return array{outlet_assignments: list<array{outlet_id: string, role: string}>}
     */
    public function contribute(int|string $userId): array
    {
        $assignments = MerchantOutletUser::query()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (MerchantOutletUser $assignment): array => [
                'outlet_id' => $assignment->outlet_id,
                'role' => $assignment->role->value,
            ])
            ->values()
            ->all();

        return ['outlet_assignments' => $assignments];
    }
}
