<?php

namespace App\Modules\Merchant\Domain\Enums;

enum LegalEntityType: string
{
    case Pt = 'pt';
    case Cv = 'cv';
    case Ud = 'ud';
    case Koperasi = 'koperasi';
    case Yayasan = 'yayasan';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
