<?php

namespace App\Modules\Merchant\Domain\Enums;

/**
 * Operational state of a merchant. Approval lifecycle lives on
 * MerchantApplicationStatus, never on the merchant itself.
 */
enum MerchantStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * The statuses this status may legally transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Inactive => [self::Active],
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
