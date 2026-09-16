<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApplicationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InReview = 'in_review';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';
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
            self::Pending => [self::InReview],
            self::InReview => [self::Pending, self::RevisionRequired, self::Approved, self::Rejected],
            self::RevisionRequired => [self::Pending],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected], true);
    }

    /**
     * Whether the application is still part of the editable/active lifecycle.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Pending,
            self::InReview,
            self::RevisionRequired,
        ], true);
    }

    /**
     * Statuses that count as an active application for the "one active
     * application per merchant" constraint.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Draft->value,
            self::Pending->value,
            self::InReview->value,
            self::RevisionRequired->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
