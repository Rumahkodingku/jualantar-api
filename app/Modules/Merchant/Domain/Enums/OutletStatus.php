<?php

namespace App\Modules\Merchant\Domain\Enums;

enum OutletStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
