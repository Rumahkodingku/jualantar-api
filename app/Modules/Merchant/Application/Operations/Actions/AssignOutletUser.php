<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Exceptions\ApiException;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\Log;

final class AssignOutletUser
{
    use ReportsOperationsErrors;

    public function __construct(
        private readonly UserLookup $userLookup,
    ) {}

    /**
     * @param  array{user_id: string, role: string}  $data
     */
    public function __invoke(Merchant $merchant, MerchantOutlet $outlet, array $data): Result
    {
        $userId = (string) $data['user_id'];
        $role = (string) $data['role'];

        try {
            $this->userLookup->user($userId);
        } catch (ApiException) {
            return $this->userNotFound();
        }

        if ($merchant->user_id !== null && $merchant->user_id === $userId) {
            return $this->businessRuleViolation('The merchant owner cannot be assigned as an outlet employee.');
        }

        if ($outlet->merchant_id !== $merchant->id) {
            return $this->outletNotFound();
        }

        $exists = MerchantOutletUser::query()
            ->where('outlet_id', $outlet->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return $this->duplicateAssignment();
        }

        $assignment = MerchantOutletUser::query()->create([
            'merchant_id' => $merchant->id,
            'outlet_id' => $outlet->id,
            'user_id' => $userId,
            'role' => $role,
        ]);

        Log::info('Outlet employee assigned to outlet.', [
            'merchant_id' => $merchant->id,
            'outlet_id' => $outlet->id,
            'user_id' => $userId,
            'role' => $role,
        ]);

        return Result::ok($assignment);
    }
}
