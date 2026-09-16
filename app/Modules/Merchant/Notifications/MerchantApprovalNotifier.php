<?php

namespace App\Modules\Merchant\Notifications;

use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Best-effort mail notifications to the merchant owner. The owner is resolved
 * through the IdentityAccess contract so the Merchant module never imports the
 * User model.
 */
final class MerchantApprovalNotifier
{
    public function __construct(private readonly UserLookup $userLookup) {}

    public function approved(Merchant $merchant, MerchantApplication $application): void
    {
        $this->send($merchant, new MerchantApplicationApprovedNotification(
            $application->application_number,
            $merchant->business_name,
        ));
    }

    public function rejected(Merchant $merchant, MerchantApplication $application, string $reason): void
    {
        $this->send($merchant, new MerchantApplicationRejectedNotification(
            $application->application_number,
            $merchant->business_name,
            $reason,
        ));
    }

    public function revisionRequested(Merchant $merchant, MerchantApplication $application, ?string $note): void
    {
        $this->send($merchant, new MerchantApplicationRevisionRequestedNotification(
            $application->application_number,
            $merchant->business_name,
            $note,
        ));
    }

    private function send(Merchant $merchant, Notification $notification): void
    {
        if ($merchant->user_id === null) {
            return;
        }

        try {
            $owner = $this->userLookup->user($merchant->user_id);
        } catch (Throwable) {
            return;
        }

        NotificationFacade::route('mail', $owner->email)->notify($notification);
    }
}
