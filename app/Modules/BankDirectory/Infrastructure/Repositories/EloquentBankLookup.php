<?php

namespace App\Modules\BankDirectory\Infrastructure\Repositories;

use App\Modules\BankDirectory\Contracts\BankLookup;
use App\Modules\BankDirectory\Contracts\DataTransferObjects\BankData;
use App\Modules\BankDirectory\Domain\Exceptions\BankNotFoundException;
use App\Modules\BankDirectory\Domain\Models\Bank;

final class EloquentBankLookup implements BankLookup
{
    public function bankExists(int $bankId): bool
    {
        return Bank::query()->whereKey($bankId)->exists();
    }

    public function bank(int $bankId): BankData
    {
        $bank = Bank::query()->find($bankId);

        if ($bank === null) {
            throw new BankNotFoundException;
        }

        return new BankData(
            id: $bank->id,
            code: $bank->code,
            name: $bank->name,
            category: $bank->category,
            isActive: $bank->is_active,
        );
    }
}
