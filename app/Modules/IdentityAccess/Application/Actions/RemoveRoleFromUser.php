<?php

namespace App\Modules\IdentityAccess\Application\Actions;

use App\Modules\IdentityAccess\Application\Concerns\ReportsRbacErrors;
use App\Modules\IdentityAccess\Domain\Models\Role;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class RemoveRoleFromUser
{
    use ReportsRbacErrors;

    /**
     * @param  list<string>  $roleNames
     */
    public function __invoke(User $actor, User $target, array $roleNames): Result
    {
        $roleNames = array_values(array_unique($roleNames));

        $missing = $this->missingRoles($roleNames);

        if ($missing !== []) {
            return $this->invalidRole($missing);
        }

        if (in_array(Role::SUPER_ADMIN, $roleNames, true) && ! $actor->hasRole(Role::SUPER_ADMIN)) {
            return $this->privilegeEscalation();
        }

        DB::transaction(function () use ($target, $roleNames): void {
            foreach ($roleNames as $roleName) {
                $target->removeRole($roleName);
            }
        });

        return Result::ok($target->refresh()->load('roles'));
    }

    /**
     * @param  list<string>  $roleNames
     * @return list<string>
     */
    private function missingRoles(array $roleNames): array
    {
        $existing = Role::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $roleNames)
            ->pluck('name')
            ->all();

        return array_values(array_diff($roleNames, $existing));
    }
}
