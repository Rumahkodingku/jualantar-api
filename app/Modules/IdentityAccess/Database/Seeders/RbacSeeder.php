<?php

namespace App\Modules\IdentityAccess\Database\Seeders;

use App\Modules\IdentityAccess\Domain\Models\Permission;
use App\Modules\IdentityAccess\Domain\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'orders.view',
        'orders.create',
        'orders.update',
        'deliveries.view',
        'deliveries.update',
    ];

    /**
     * Permission bundles granted to each initial role.
     *
     * `super-admin` intentionally has no explicit permissions; it is granted
     * every ability through the `Gate::before` hook in the service provider.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'super-admin' => [],
        'admin' => [
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'roles.create',
            'roles.update',
            'permissions.view',
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

            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'sanctum');

                if ($permissions !== []) {
                    $role->syncPermissions($permissions);
                }
            }
        });
    }
}
