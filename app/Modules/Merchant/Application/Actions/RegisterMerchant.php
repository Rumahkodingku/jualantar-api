<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class RegisterMerchant
{
    use ReportsRegistrationErrors;

    /**
     * Create an empty draft registration for the authenticated user.
     *
     * A user may only own a single merchant; a duplicate is reported as a
     * conflict so the client can resume the existing registration instead.
     */
    public function __invoke(string $userId): Result
    {
        if (Merchant::query()->where('user_id', $userId)->exists()) {
            return $this->alreadyExists();
        }

        try {
            $merchant = Merchant::query()->create([
                'user_id' => $userId,
                'status' => MerchantStatus::Draft,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->alreadyExists();
        }

        return Result::ok($merchant);
    }
}
