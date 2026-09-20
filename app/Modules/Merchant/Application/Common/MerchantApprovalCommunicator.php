<?php

namespace App\Modules\Merchant\Application\Common;

use App\Modules\Communications\Contracts\Communications;
use App\Modules\Communications\Contracts\DataTransferObjects\SendCommunicationData;
use App\Modules\Communications\Contracts\Enums\CommunicationChannel;
use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends merchant approval outcome emails through the Communications contract.
 *
 * The owner is resolved through the IdentityAccess contract so the Merchant
 * module never imports the User model. Delivery is best-effort: a failure is
 * logged and never rolls back the approval workflow.
 */
final class MerchantApprovalCommunicator
{
    public function __construct(
        private readonly Communications $communications,
        private readonly UserLookup $userLookup,
    ) {}

    public function approved(Merchant $merchant, MerchantApplication $application): void
    {
        $this->send(
            merchant: $merchant,
            application: $application,
            type: 'merchant.application.approved',
            template: 'email.merchant.application-approved',
            subject: 'Pengajuan merchant disetujui',
            idempotencyKey: 'merchant.application.approved:'.$application->id,
            payload: [
                'business_name' => $merchant->business_name,
                'application_number' => $application->application_number,
            ],
        );
    }

    public function rejected(Merchant $merchant, MerchantApplication $application, string $reason): void
    {
        $this->send(
            merchant: $merchant,
            application: $application,
            type: 'merchant.application.rejected',
            template: 'email.merchant.application-rejected',
            subject: 'Pengajuan merchant ditolak',
            idempotencyKey: 'merchant.application.rejected:'.$application->id,
            payload: [
                'business_name' => $merchant->business_name,
                'application_number' => $application->application_number,
                'reason' => $reason,
            ],
        );
    }

    public function revisionRequested(Merchant $merchant, MerchantApplication $application, ?string $note): void
    {
        $this->send(
            merchant: $merchant,
            application: $application,
            type: 'merchant.application.revision_requested',
            template: 'email.merchant.application-revision-requested',
            subject: 'Perbaikan pengajuan merchant diperlukan',
            idempotencyKey: 'merchant.application.revision_requested:'.$application->id,
            payload: [
                'business_name' => $merchant->business_name,
                'application_number' => $application->application_number,
                'note' => $note,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(
        Merchant $merchant,
        MerchantApplication $application,
        string $type,
        string $template,
        string $subject,
        string $idempotencyKey,
        array $payload,
    ): void {
        if ($merchant->user_id === null) {
            return;
        }

        try {
            $owner = $this->userLookup->user($merchant->user_id);
        } catch (Throwable) {
            return;
        }

        try {
            $this->communications->send(new SendCommunicationData(
                channel: CommunicationChannel::Email,
                type: $type,
                recipientAddress: $owner->email,
                subject: $subject,
                template: $template,
                payload: array_merge($payload, [
                    'action_url' => config('merchant.application_url'),
                ]),
                idempotencyKey: $idempotencyKey,
            ));
        } catch (Throwable $e) {
            Log::warning('merchant_approval_communication_failed', [
                'application_id' => $application->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
