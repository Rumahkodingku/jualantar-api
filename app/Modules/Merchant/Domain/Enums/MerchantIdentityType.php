<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantIdentityType: string
{
    case Ktp = 'ktp';
    case Sim = 'sim';
    case Paspor = 'paspor';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
