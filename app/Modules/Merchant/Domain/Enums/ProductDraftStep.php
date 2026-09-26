<?php

namespace App\Modules\Merchant\Domain\Enums;

/**
 * Steps of the catalog "add product" wizard, in order. The integer values are
 * the position in the wizard and are stored on the draft so a merchant can be
 * resumed on the step they left behind.
 */
enum ProductDraftStep: int
{
    case Info = 0;
    case Price = 1;
    case Customization = 2;
    case Media = 3;
    case Outlet = 4;
    case Review = 5;

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Product information',
            self::Price => 'Price',
            self::Customization => 'Customization',
            self::Media => 'Media',
            self::Outlet => 'Outlet',
            self::Review => 'Review',
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
