<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantRejectionStage: string
{
    case Merchant = 'merchant';
    case Identity = 'identity';
    case LegalEntity = 'legal_entity';
    case Outlet = 'outlet';
    case Document = 'document';
    case Payout = 'payout';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
