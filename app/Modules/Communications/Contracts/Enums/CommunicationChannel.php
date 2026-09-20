<?php

namespace App\Modules\Communications\Contracts\Enums;

/**
 * Delivery medium. Part of the public contract surface so producers can select
 * a channel without importing the Communications domain.
 */
enum CommunicationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
