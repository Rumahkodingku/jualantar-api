<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Retired global outlet roles. Their capabilities now live in
     * App\Modules\Merchant\Domain\Authorization\OutletRoleCapabilityResolver and
     * are scoped to a merchant_outlet_users assignment instead of the user.
     *
     * @var list<string>
     */
    private const LEGACY_ROLES = ['outlet_manager', 'outlet_staff'];

    /**
     * Remove the retired outlet roles and detach them from every user.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['roles'])) {
            return;
        }

        $roleIds = DB::table($tableNames['roles'])
            ->where('guard_name', 'sanctum')
            ->whereIn('name', self::LEGACY_ROLES)
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($tableNames, $roleIds): void {
            DB::table($tableNames['model_has_roles'])->whereIn('role_id', $roleIds)->delete();
            DB::table($tableNames['role_has_permissions'])->whereIn('role_id', $roleIds)->delete();
            DB::table($tableNames['roles'])->whereIn('id', $roleIds)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Recreate the retired role rows without permissions. The capability mapping
     * is not restored because the outlet domain now owns it.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['roles'])) {
            return;
        }

        $now = now();

        foreach (self::LEGACY_ROLES as $role) {
            DB::table($tableNames['roles'])->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'sanctum',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
