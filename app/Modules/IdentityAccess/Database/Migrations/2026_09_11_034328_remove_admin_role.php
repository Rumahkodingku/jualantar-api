<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The permissions the removed `admin` role held, restored on rollback.
     *
     * @var list<string>
     */
    private const ADMIN_PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'roles.view',
        'roles.create',
        'roles.update',
        'permissions.view',
        'geography.update',
    ];

    /**
     * Remove the retired `admin` role and detach it from any users.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['roles'])) {
            return;
        }

        $roleId = DB::table($tableNames['roles'])
            ->where('name', 'admin')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($roleId === null) {
            return;
        }

        DB::transaction(function () use ($tableNames, $roleId): void {
            DB::table($tableNames['model_has_roles'])->where('role_id', $roleId)->delete();
            DB::table($tableNames['role_has_permissions'])->where('role_id', $roleId)->delete();
            DB::table($tableNames['roles'])->where('id', $roleId)->delete();
        });
    }

    /**
     * Recreate the `admin` role with its former permissions.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (! Schema::hasTable($tableNames['roles'])) {
            return;
        }

        $now = now();

        DB::table($tableNames['roles'])->insertOrIgnore([
            'name' => 'admin',
            'guard_name' => 'sanctum',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table($tableNames['roles'])
            ->where('name', 'admin')
            ->where('guard_name', 'sanctum')
            ->value('id');

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('guard_name', 'sanctum')
            ->whereIn('name', self::ADMIN_PERMISSIONS)
            ->pluck('id');

        $rows = $permissionIds
            ->map(fn (int|string $permissionId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ])
            ->all();

        if ($rows !== []) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore($rows);
        }
    }
};
