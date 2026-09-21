<?php

namespace App\Modules\Merchant\Domain\Enums;

enum OutletStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * Outlets can move freely between active and inactive.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [self::Inactive],
            self::Inactive => [self::Active],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
