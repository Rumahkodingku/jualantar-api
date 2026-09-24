<?php

namespace App\Modules\Merchant\Domain\Enums;

/**
 * Manual availability toggle of a product at a single outlet.
 *
 * This is deliberately separate from the derived open/closed availability
 * resolved from an outlet's operating hours.
 */
enum ProductAvailabilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
