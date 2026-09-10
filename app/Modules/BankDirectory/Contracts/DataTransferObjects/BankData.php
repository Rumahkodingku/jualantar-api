<?php

namespace App\Modules\BankDirectory\Contracts\DataTransferObjects;

final readonly class BankData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $category,
        public bool $isActive,
    ) {}
}
