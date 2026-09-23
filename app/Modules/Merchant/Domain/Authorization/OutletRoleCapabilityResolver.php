<?php

namespace App\Modules\Merchant\Domain\Authorization;

use App\Modules\Merchant\Domain\Enums\OutletUserRole;

/**
 * Single source of truth for the capabilities granted by an outlet-scoped role.
 *
 * Outlet roles are contextual: they only apply to the outlet an employee is
 * assigned to (`merchant.merchant_outlet_users.role`). Their capabilities are
 * therefore resolved here, in the domain, instead of through global Spatie
 * roles. Capability names reuse the `merchant.operations.*` permission strings
 * so the contextual authorization service speaks the same action identifiers
 * as the routes and requests.
 */
final class OutletRoleCapabilityResolver
{
    /**
     * @return list<string>
     */
    public function permissionsFor(OutletUserRole $role): array
    {
        return match ($role) {
            OutletUserRole::OutletManager => [
                'merchant.operations.view',
                'merchant.operations.outlets.view',
                'merchant.operations.outlets.update',
                'merchant.operations.outlets.status.update',
                'merchant.operations.outlet_users.view',
                'merchant.operations.outlet_users.assign',
                'merchant.operations.outlet_users.remove',
                'merchant.operations.outlet_users.role.update',
                'merchant.operations.hours.view',
                'merchant.operations.hours.update',
                'merchant.operations.service_area.view',
                'merchant.operations.service_area.update',
                'merchant.operations.availability.view',
            ],
            OutletUserRole::OutletStaff => [
                'merchant.operations.view',
                'merchant.operations.outlets.view',
                'merchant.operations.hours.view',
                'merchant.operations.service_area.view',
                'merchant.operations.availability.view',
            ],
        };
    }

    public function allows(OutletUserRole $role, string $capability): bool
    {
        return in_array($capability, $this->permissionsFor($role), true);
    }
}
