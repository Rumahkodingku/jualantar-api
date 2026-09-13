<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    /**
     * The statuses this status may legally transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::Active, self::Rejected],
            self::Rejected => [self::Draft, self::Pending],
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
