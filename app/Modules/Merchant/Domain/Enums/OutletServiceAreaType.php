<?php

namespace App\Modules\Merchant\Domain\Enums;

enum OutletServiceAreaType: string
{
    case Radius = 'radius';
    case Province = 'province';
    case Regency = 'regency';
    case District = 'district';
    case Village = 'village';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
