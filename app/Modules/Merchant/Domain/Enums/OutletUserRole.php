<?php

namespace App\Modules\Merchant\Domain\Enums;

/**
 * Outlet-scoped role of an employee assignment. The owner is never stored here;
 * ownership lives on `merchant.merchants.user_id`.
 */
enum OutletUserRole: string
{
    case OutletManager = 'outlet_manager';
    case OutletStaff = 'outlet_staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
