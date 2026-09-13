<?php

namespace App\Modules\Payout\Domain\Enums;

enum PayoutOwnerType: string
{
    case Merchant = 'merchant';
    case Driver = 'driver';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
