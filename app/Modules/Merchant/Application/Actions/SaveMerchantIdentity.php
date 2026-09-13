<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;

final class SaveMerchantIdentity
{
    use ReportsRegistrationErrors;

    /**
     * @param  array{id_type: string, id_number: string, full_name: string, birth_date?: string|null}  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        $merchant->identity()->updateOrCreate([], [
            'id_type' => $data['id_type'],
            'id_number' => $data['id_number'],
            'full_name' => $data['full_name'],
            'birth_date' => $data['birth_date'] ?? null,
        ]);

        return Result::ok($merchant->refresh());
    }
}
