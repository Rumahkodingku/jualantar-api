<?php

namespace App\Modules\Merchant\Application\Common\Concerns;

use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\Merchant;

/**
 * Single source of truth for what a merchant registration must contain and
 * which subjects an approval must verify before the application can be
 * approved.
 */
trait RegistrationRequirements
{
    /**
     * The components required for a given merchant type. `legal_entity` only
     * applies to companies.
     *
     * @return list<MerchantApprovalComponent>
     */
    private function requiredComponents(MerchantType $type): array
    {
        return array_values(array_filter(
            MerchantApprovalComponent::cases(),
            fn (MerchantApprovalComponent $component): bool => $component->isRequiredFor($type),
        ));
    }

    /**
     * Derive the required subjects per component from a submission snapshot.
     * Every required component is present as a key, even when it has no
     * subjects (e.g. a submission without documents), so the approval gate can
     * detect missing subjects.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, list<array{subject_type: string, subject_id: string}>>
     */
    private function requiredSubjectsByComponent(array $snapshot): array
    {
        $type = MerchantType::tryFrom((string) ($snapshot['merchant_type'] ?? ''))
            ?? MerchantType::Individual;

        $result = [];

        foreach ($this->requiredComponents($type) as $component) {
            $subjectType = $component->subjectType()->value;
            $entry = $snapshot['subjects'][$subjectType] ?? null;
            $subjects = [];

            if ($entry !== null) {
                $list = array_is_list($entry) ? $entry : [$entry];

                foreach ($list as $subject) {
                    if (($subject['subject_id'] ?? null) === null) {
                        continue;
                    }

                    $subjects[] = [
                        'subject_type' => $subjectType,
                        'subject_id' => (string) $subject['subject_id'],
                    ];
                }
            }

            $result[$component->value] = $subjects;
        }

        return $result;
    }

    /**
     * Missing pieces of a registration, used by submit completeness. Kept in
     * sync with the approval component list above.
     *
     * @param  list<mixed>  $payoutAccounts
     * @return list<string>
     */
    private function missingRequirements(Merchant $merchant, array $payoutAccounts): array
    {
        $missing = [];

        if (blank($merchant->business_name)) {
            $missing[] = 'business_name';
        }

        if ($merchant->type === null) {
            $missing[] = 'type';
        }

        if ($merchant->service_id === null) {
            $missing[] = 'service_id';
        }

        if ($merchant->identity === null) {
            $missing[] = 'identity';
        }

        $categoryCount = $merchant->categories->count();

        if ($categoryCount < 1 || $categoryCount > 3) {
            $missing[] = 'categories';
        }

        $activeOutlets = $merchant->outlets
            ->filter(fn ($outlet): bool => $outlet->status === OutletStatus::Active)
            ->count();

        if ($activeOutlets < 1) {
            $missing[] = 'outlets';
        }

        if ($merchant->type === MerchantType::Company && $merchant->legal_entity_id === null) {
            $missing[] = 'legal_entity';
        }

        if ($payoutAccounts === []) {
            $missing[] = 'payout_account';
        }

        return $missing;
    }
}
