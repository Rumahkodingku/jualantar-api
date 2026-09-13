<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class SubmitMerchantRegistration
{
    use ReportsRegistrationErrors;

    /**
     * Owner type understood by the Payout contract (kept primitive on purpose
     * so this module never imports the Payout domain).
     */
    private const OWNER_TYPE_MERCHANT = 'merchant';

    public function __construct(private readonly PayoutAccountLookup $payoutLookup) {}

    public function __invoke(Merchant $merchant): Result
    {
        if ($merchant->status === MerchantStatus::Pending) {
            return Result::ok($merchant);
        }

        if ($merchant->status !== MerchantStatus::Draft) {
            return $this->invalidRegistrationState('Only draft registrations can be submitted.');
        }

        $merchant->load(['identity', 'categories', 'outlets', 'legalEntity']);

        $missing = $this->missingRequirements($merchant);

        if ($missing !== []) {
            return $this->registrationIncomplete($missing);
        }

        return DB::transaction(function () use ($merchant): Result {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === MerchantStatus::Pending) {
                return Result::ok($locked);
            }

            if ($locked->status !== MerchantStatus::Draft) {
                return $this->invalidRegistrationState('Only draft registrations can be submitted.');
            }

            $locked->update(['status' => MerchantStatus::Pending]);

            return Result::ok($locked);
        });
    }

    /**
     * @return list<string>
     */
    private function missingRequirements(Merchant $merchant): array
    {
        $missing = [];

        if (blank($merchant->business_name)) {
            $missing[] = 'business_name';
        }

        if ($merchant->type === null) {
            $missing[] = 'type';
        }

        if ($merchant->service_id === null) {
            $missing[] = 'service_id';
        }

        if ($merchant->identity === null) {
            $missing[] = 'identity';
        }

        $categoryCount = $merchant->categories->count();

        if ($categoryCount < 1 || $categoryCount > 3) {
            $missing[] = 'categories';
        }

        $activeOutlets = $merchant->outlets
            ->filter(fn ($outlet): bool => $outlet->status === OutletStatus::Active)
            ->count();

        if ($activeOutlets < 1) {
            $missing[] = 'outlets';
        }

        if ($merchant->type === MerchantType::Company && $merchant->legal_entity_id === null) {
            $missing[] = 'legal_entity';
        }

        if ($this->payoutLookup->accountsForOwner(self::OWNER_TYPE_MERCHANT, $merchant->id) === []) {
            $missing[] = 'payout_account';
        }

        return $missing;
    }
}
