<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\IdentityAccess\Contracts\UserProvisioning;
use App\Modules\Merchant\Application\Operations\Concerns\ReportsOperationsErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Provisions an outlet employee account and assigns it to the outlet in one
 * transaction. P0 has no invitation flow: the owner sets the initial password
 * and hands the credentials over, so the account is created already verified.
 */
final class CreateOutletEmployee
{
    use ReportsOperationsErrors;

    public function __construct(
        private readonly UserProvisioning $userProvisioning,
    ) {}

    /**
     * @param  array{email: string, phone?: string|null, password: string, role: string}  $data
     */
    public function __invoke(Merchant $merchant, MerchantOutlet $outlet, array $data): Result
    {
        if ($outlet->merchant_id !== $merchant->id) {
            return $this->outletNotFound();
        }

        $role = (string) $data['role'];

        $assignment = DB::transaction(function () use ($outlet, $merchant, $data, $role): MerchantOutletUser {
            $user = $this->userProvisioning->provisionOutletEmployee([
                'email' => (string) $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => (string) $data['password'],
            ]);

            $assignment = MerchantOutletUser::query()->create([
                'merchant_id' => $merchant->id,
                'outlet_id' => $outlet->id,
                'user_id' => $user->id,
                'role' => $role,
            ]);

            return $assignment;
        });

        Log::info('Outlet employee account created and assigned.', [
            'merchant_id' => $assignment->merchant_id,
            'outlet_id' => $assignment->outlet_id,
            'user_id' => $assignment->user_id,
            'role' => $role,
        ]);

        return Result::ok($assignment);
    }
}
