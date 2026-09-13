<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantDocumentType: string
{
    case Ktp = 'ktp';
    case Npwp = 'npwp';
    case Nib = 'nib';
    case Siup = 'siup';
    case AktaPendirian = 'akta_pendirian';
    case Lainnya = 'lainnya';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
