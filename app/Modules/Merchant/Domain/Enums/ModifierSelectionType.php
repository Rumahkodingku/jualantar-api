<?php

namespace App\Modules\Merchant\Domain\Enums;

enum ModifierSelectionType: string
{
    case Single = 'single';
    case Multiple = 'multiple';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
