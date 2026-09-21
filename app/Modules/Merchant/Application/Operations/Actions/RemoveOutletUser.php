<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\IdentityAccess\Contracts\Authorization;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;

final class RemoveOutletUser
{
    use ReportsOperationsErrors;

    public function __construct(private readonly Authorization $authorization) {}

    public function __invoke(MerchantOutlet $outlet, string $userId): Result
    {
        $assignment = MerchantOutletUser::query()
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $userId)
            ->first();

        if ($assignment === null) {
            return $this->assignmentNotFound();
        }

        $role = $assignment->role->value;
        $assignment->delete();

        $stillHasRole = MerchantOutletUser::query()
            ->where('user_id', $userId)
            ->where('role', $role)
            ->exists();

        if (! $stillHasRole) {
            $this->authorization->removeRole($userId, $role);
        }

        return Result::ok(null);
    }
}
