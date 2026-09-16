<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApprovalEventType: string
{
    case ApplicationSubmitted = 'application_submitted';
    case ApprovalClaimed = 'approval_claimed';
    case ApprovalReleased = 'approval_released';
    case ComponentReviewed = 'component_reviewed';
    case RevisionRequested = 'revision_requested';
    case ApplicationResubmitted = 'application_resubmitted';
    case ApplicationApproved = 'application_approved';
    case ApplicationRejected = 'application_rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
