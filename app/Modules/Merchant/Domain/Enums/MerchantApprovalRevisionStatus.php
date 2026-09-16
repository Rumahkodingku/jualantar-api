<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApprovalRevisionStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
