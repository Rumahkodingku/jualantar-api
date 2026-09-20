<?php

namespace App\Modules\Communications\Contracts\Enums;

/**
 * Delivery lifecycle state. Part of the public contract surface so producers
 * can read CommunicationResult without importing the Communications domain.
 */
enum CommunicationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    /**
     * The statuses this status may legally transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Queued, self::Failed],
            self::Queued => [self::Processing, self::Failed],
            self::Processing => [self::Sent, self::Failed],
            self::Sent => [self::Delivered],
            self::Delivered, self::Failed => [],
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
