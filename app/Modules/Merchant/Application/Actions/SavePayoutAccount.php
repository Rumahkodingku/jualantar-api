<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Payout\Contracts\PayoutAccountProvisioning;
use App\Shared\Result\Result;

final class SavePayoutAccount
{
    use ReportsRegistrationErrors;

    /**
     * Owner type understood by the Payout contract (kept primitive on purpose
     * so this module never imports the Payout domain).
     */
    private const OWNER_TYPE_MERCHANT = 'merchant';

    public function __construct(private readonly PayoutAccountProvisioning $provisioning) {}

    /**
     * @param  array{bank_id: int, account_number: string, account_name: string}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        return $this->provisioning->saveForOwner(
            self::OWNER_TYPE_MERCHANT,
            $merchant->id,
            (int) $data['bank_id'],
            $data['account_number'],
            $data['account_name'],
        );
    }
}
