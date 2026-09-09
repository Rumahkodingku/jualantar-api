<?php

namespace App\Services;

use App\Models\Bank;
use App\Support\Result\Result;
use App\Support\Result\ResultError;
use Illuminate\Database\UniqueConstraintViolationException;

class BankService
{
    public function store(array $data): Result
    {
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return Result::ok(Bank::create($data));
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflict();
        }
    }

    public function update(Bank $bank, array $data): Result
    {
        try {
            $bank->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflict();
        }

        return Result::ok($bank->refresh());
    }

    public function deactivate(Bank $bank): Result
    {
        if ($bank->is_active) {
            $bank->update(['is_active' => false]);
        }

        return Result::ok($bank->refresh());
    }

    private function codeConflict(): Result
    {
        return Result::err(new ResultError(
            code: 'conflict',
            message: 'A bank with the same code already exists.',
            status: 409,
            title: 'Conflict',
        ));
    }
}
