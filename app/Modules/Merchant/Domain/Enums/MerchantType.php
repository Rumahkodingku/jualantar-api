<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantType: string
{
    case Individual = 'individual';
    case Company = 'company';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
