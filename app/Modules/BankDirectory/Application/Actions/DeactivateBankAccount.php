<?php

namespace App\Modules\BankDirectory\Application\Actions;

use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Shared\Result\Result;

final class DeactivateBankAccount
{
    public function __invoke(Bank $bank): Result
    {
        if ($bank->is_active) {
            $bank->update(['is_active' => false]);
        }

        return Result::ok($bank->refresh());
    }
}
