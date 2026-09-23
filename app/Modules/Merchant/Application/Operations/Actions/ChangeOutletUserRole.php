<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Log;

final class ChangeOutletUserRole
{
    use ReportsOperationsErrors;

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

        if ($assignment->role->value === $newRole) {
            return Result::ok($assignment);
        }

        $previousRole = $assignment->role->value;

        $assignment->update(['role' => $newRole]);

        Log::info('Outlet employee role changed.', [
            'outlet_id' => $outlet->id,
            'user_id' => $userId,
            'from_role' => $previousRole,
            'to_role' => $newRole,
        ]);

        return Result::ok($assignment->refresh());
    }
}
