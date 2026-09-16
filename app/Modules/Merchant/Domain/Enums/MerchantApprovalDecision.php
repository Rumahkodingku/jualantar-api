<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
