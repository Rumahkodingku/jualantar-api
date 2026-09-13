<?php

namespace App\Modules\Payout\Contracts\DataTransferObjects;

final readonly class PayoutAccountData
{
    public function __construct(
        public string $id,
        public string $ownerType,
        public string $ownerId,
        public int $bankId,
        public string $accountNumber,
        public string $accountName,
        public bool $isPrimary,
        public string $status,
        public ?string $rejectionReason,
        public ?string $bankName = null,
    ) {}
}
