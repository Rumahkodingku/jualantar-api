<?php

namespace App\Modules\BankDirectory\Application\Actions;

use App\Modules\BankDirectory\Application\Concerns\ReportsBankCodeConflict;
use App\Modules\BankDirectory\Domain\Models\Bank;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class StoreBankAccount
{
    use ReportsBankCodeConflict;

    public function __invoke(array $data): Result
    {
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return Result::ok(Bank::create($data));
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflict();
        }
    }
}
