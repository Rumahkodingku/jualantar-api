<?php

namespace App\Modules\BankDirectory\Application\Actions;

use App\Modules\BankDirectory\Application\Concerns\ReportsBankCodeConflict;
use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateBankAccount
{
    use ReportsBankCodeConflict;

    public function __invoke(Bank $bank, array $data): Result
    {
        try {
            $bank->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflict();
        }

        return Result::ok($bank->refresh());
    }
}
