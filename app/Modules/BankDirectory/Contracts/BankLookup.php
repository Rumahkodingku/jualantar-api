<?php

namespace App\Modules\BankDirectory\Contracts;

use App\Modules\BankDirectory\Contracts\DataTransferObjects\BankData;

interface BankLookup
{
    public function bankExists(int $bankId): bool;

    public function bank(int $bankId): BankData;
}
