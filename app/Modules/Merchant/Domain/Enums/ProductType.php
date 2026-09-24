<?php

namespace App\Modules\Merchant\Domain\Enums;

enum ProductType: string
{
    case Simple = 'simple';
    case Variable = 'variable';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
