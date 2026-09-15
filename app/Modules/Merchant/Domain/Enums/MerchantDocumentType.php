<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantDocumentType: string
{
    case Ktp = 'ktp';
    case Swafoto = 'swafoto';
    case Npwp = 'npwp';
    case Nib = 'nib';
    case Siup = 'siup';
    case IzinUsaha = 'izin_usaha';
    case AktaPendirian = 'akta_pendirian';
    case IdentitasDirektur = 'identitas_direktur';
    case Rekening = 'rekening';
    case FotoOutlet = 'foto_outlet';
    case Lainnya = 'lainnya';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
