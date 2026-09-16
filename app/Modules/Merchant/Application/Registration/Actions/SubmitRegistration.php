<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Merchant\Application\Common\Concerns\ManagesApprovalReviews;
use App\Modules\Merchant\Application\Common\Concerns\RecordsApprovalEvents;
use App\Modules\Merchant\Application\Common\Concerns\RegistrationRequirements;
use App\Modules\Merchant\Application\Common\Concerns\ReportsApprovalErrors;
use App\Modules\Merchant\Application\Common\Concerns\ResolvesActiveApplication;
use App\Modules\Merchant\Application\Registration\Concerns\BuildsApplicationSnapshot;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalEventType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class SubmitRegistration
{
    use BuildsApplicationSnapshot, ManagesApprovalReviews, RecordsApprovalEvents, RegistrationRequirements, ReportsApprovalErrors, ReportsRegistrationErrors, ResolvesActiveApplication;

    /**
     * Owner type understood by the Payout contract (kept primitive on purpose
     * so this module never imports the Payout domain).
     */
    private const OWNER_TYPE_MERCHANT = 'merchant';

    public function __construct(
        private readonly PayoutAccountLookup $payoutLookup,
        private readonly ServiceLookup $serviceLookup,
    ) {}

    public function __invoke(Merchant $merchant): Result
    {
        $application = $this->activeApplication($merchant);

        if ($application === null) {
            return $this->applicationNotFound();
        }

        if (in_array($application->status, [
            MerchantApplicationStatus::Pending,
            MerchantApplicationStatus::InReview,
        ], true)) {
            return Result::ok($merchant);
        }

        if ($application->status->isTerminal()) {
            return $this->invalidStateTransition('A terminal application cannot be submitted.');
        }

        $merchant->load(['identity', 'categories', 'outlets', 'legalEntity']);

        $payoutAccounts = $this->payoutLookup->accountsForOwner(self::OWNER_TYPE_MERCHANT, $merchant->id);
        $missing = $this->missingRequirements($merchant, $payoutAccounts);

        if ($missing !== []) {
            return $this->registrationIncomplete($missing);
        }

        return DB::transaction(function () use ($merchant, $application, $payoutAccounts): Result {
            $lockedApplication = MerchantApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedApplication->status, [
                MerchantApplicationStatus::Pending,
                MerchantApplicationStatus::InReview,
            ], true)) {
                return Result::ok($merchant->refresh());
            }

            if ($lockedApplication->status->isTerminal()) {
                return $this->invalidStateTransition('A terminal application cannot be submitted.');
            }

            $isResubmit = $lockedApplication->status === MerchantApplicationStatus::RevisionRequired;

            $merchant->loadMissing(['identity', 'legalEntity', 'categories', 'outlets', 'documents']);

            $serviceData = $merchant->service_id === null
                ? null
                : ($this->serviceLookup->servicesByIds([$merchant->service_id])[$merchant->service_id] ?? null);

            $version = ((int) MerchantApplicationSnapshot::query()
                ->where('application_id', $lockedApplication->id)
                ->max('version')) + 1;

            $snapshot = MerchantApplicationSnapshot::query()->create([
                'application_id' => $lockedApplication->id,
                'version' => $version,
                'snapshot' => $this->buildSnapshot(
                    $merchant,
                    $version,
                    $serviceData === null ? null : (array) $serviceData,
                    $payoutAccounts,
                ),
                'submitted_at' => now(),
            ]);

            $lockedApplication->update([
                'status' => MerchantApplicationStatus::Pending,
                'submitted_at' => now(),
            ]);

            $approval = MerchantApproval::query()->firstOrCreate([
                'application_id' => $lockedApplication->id,
            ]);

            if ($isResubmit) {
                $this->applyReviewReset($approval, $snapshot);
                $this->resolveOpenRevisions($approval);
            }

            $this->recordEvent(
                $approval,
                $isResubmit
                    ? MerchantApprovalEventType::ApplicationResubmitted
                    : MerchantApprovalEventType::ApplicationSubmitted,
                $merchant->user_id,
                [
                    'application_id' => $lockedApplication->id,
                    'snapshot_id' => $snapshot->id,
                    'version' => $version,
                ],
            );

            return Result::ok($merchant->refresh());
        });
    }
}
