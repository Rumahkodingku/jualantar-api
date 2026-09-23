<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Log;

final class RemoveOutletUser
{
    use ReportsOperationsErrors;

    public function __invoke(MerchantOutlet $outlet, string $userId): Result
    {
        $assignment = MerchantOutletUser::query()
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $userId)
            ->first();

        if ($assignment === null) {
            return $this->assignmentNotFound();
        }

        $assignment->delete();

        Log::info('Outlet employee removed from outlet.', [
            'outlet_id' => $outlet->id,
            'user_id' => $userId,
        ]);

        return Result::ok(null);
    }
}
