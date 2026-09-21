<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;

final class ChangeOutletUserRole
{
    use ReportsOperationsErrors;

    public function __construct(private readonly Authorization $authorization) {}

    public function __invoke(MerchantOutlet $outlet, string $userId, string $newRole): Result
    {
        $assignment = MerchantOutletUser::query()
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $userId)
            ->first();

        if ($assignment === null) {
            return $this->assignmentNotFound();
        }

        if ($assignment->merchant->user_id === $userId) {
            return $this->businessRuleViolation('The merchant owner role cannot be changed through an outlet assignment.');
        }

        $oldRole = $assignment->role->value;

        if ($oldRole === $newRole) {
            return Result::ok($assignment);
        }

        $assignment->update(['role' => $newRole]);

        $stillHasOldRole = MerchantOutletUser::query()
            ->where('user_id', $userId)
            ->where('role', $oldRole)
            ->whereKeyNot($assignment->id)
            ->exists();

        if (! $stillHasOldRole) {
            $this->authorization->removeRole($userId, $oldRole);
        }

        $this->authorization->assignRole($userId, $newRole);

        return Result::ok($assignment->refresh());
    }
}
