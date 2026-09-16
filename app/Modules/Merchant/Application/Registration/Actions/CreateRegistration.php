<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Registration\Concerns\GeneratesApplicationNumber;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateRegistration
{
    use GeneratesApplicationNumber, ReportsRegistrationErrors;

    /**
     * Create the merchant (once) and open a new draft application for the
     * authenticated user. A merchant may only own a single active application
     * at a time; a duplicate is reported as a conflict so the client can
     * resume the existing application instead.
     */
    public function __invoke(string $userId): Result
    {
        return DB::transaction(function () use ($userId): Result {
            $merchant = Merchant::query()->where('user_id', $userId)->lockForUpdate()->first();

            if ($merchant === null) {
                try {
                    $merchant = Merchant::query()->create([
                        'user_id' => $userId,
                        'status' => MerchantStatus::Inactive,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    return $this->alreadyExists();
                }
            } elseif ($this->hasActiveApplication($merchant)) {
                return $this->alreadyExists();
            }

            MerchantApplication::query()->create([
                'merchant_id' => $merchant->id,
                'application_number' => $this->nextApplicationNumber(),
                'status' => MerchantApplicationStatus::Draft,
            ]);

            return Result::ok($merchant);
        });
    }

    private function hasActiveApplication(Merchant $merchant): bool
    {
        return MerchantApplication::query()
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', MerchantApplicationStatus::activeValues())
            ->exists();
    }
}
