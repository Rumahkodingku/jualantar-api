<?php

namespace App\Modules\IdentityAccess\Database\Seeders;

use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Domain\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Full permission catalog owned by the capabilities of the platform.
     *
     * Permissions are grouped conceptually by the module that owns the
     * capability; IdentityAccess only manages the RBAC mechanism itself.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'permissions.view',
        'permissions.create',
        'permissions.update',
        'permissions.delete',
        'profile.view',
        'profile.update',
        'merchant.view',
        'merchant.update',
        'merchant.approval.view',
        'merchant.approval.claim',
        'merchant.approval.review',
        'merchant.approval.revision',
        'merchant.approval.reject',
        'merchant.approval.approve',
        'merchant.operations.view',
        'merchant.operations.status.update',
        'merchant.operations.profile.update',
        'merchant.operations.outlets.view',
        'merchant.operations.outlets.create',
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
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'orders.view',
        'orders.create',
        'orders.update',
        'deliveries.view',
        'deliveries.update',
        'geography.update',
        'services.manage',
        'categories.manage',
        'payout_accounts.manage',
    ];

    /**
     * Permission bundles granted to each initial role.
     *
     * `super-admin` holds the administrative permissions explicitly, and is
     * additionally granted every ability through the `Gate::before` hook in
     * the service provider.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'super-admin' => [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'geography.update',
            'services.manage',
            'categories.manage',
            'payout_accounts.manage',
        ],
        'customer' => [
            'profile.view',
            'profile.update',
            'orders.view',
            'orders.create',
        ],
        'merchant' => [
            'profile.view',
            'profile.update',
            'merchant.view',
            'merchant.update',
            'merchant.operations.view',
            'merchant.operations.status.update',
            'merchant.operations.profile.update',
            'merchant.operations.outlets.view',
            'merchant.operations.outlets.create',
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
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'orders.view',
        ],
        'driver' => [
            'profile.view',
            'profile.update',
            'deliveries.view',
            'deliveries.update',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::PERMISSIONS as $permission) {
                Permission::findOrCreate($permission, 'sanctum');
            }

            // Spatie caches the (empty) permission list on the first lookup above
            // and does not invalidate it when permissions are created, so the
            // syncPermissions calls below would not see the new permissions.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'sanctum');

                if ($permissions !== []) {
                    $role->syncPermissions($permissions);
                }
            }
        });
    }
}
