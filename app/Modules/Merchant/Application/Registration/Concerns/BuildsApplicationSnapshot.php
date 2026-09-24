<?php

namespace App\Modules\Merchant\Application\Registration\Concerns;

use App\Modules\Merchant\Domain\Enums\OutletStatus;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantIdentity;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;

/**
 * Builds the immutable submission payload. Only active outlets are captured as
 * subjects because inactive outlets are not part of the approval requirement.
 */
trait BuildsApplicationSnapshot
{
    /**
     * @param  array<string, mixed>|null  $serviceData
     * @param  list<PayoutAccountData>  $payoutAccounts
     * @return array<string, mixed>
     */
    private function buildSnapshot(
        Merchant $merchant,
        int $version,
        ?array $serviceData,
        array $payoutAccounts,
    ): array {
        $merchant->loadMissing(['identity', 'legalEntity', 'categories', 'outlets', 'documents']);

        $subjects = [
            'merchant' => $this->subject('merchant', $merchant->id, [
                'id' => $merchant->id,
                'business_name' => $merchant->business_name,
                'slug' => $merchant->slug,
                'description' => $merchant->description,
                'type' => $merchant->type?->value,
                'logo' => $merchant->logo,
            ]),
            'merchant_identity' => $merchant->identity === null
                ? null
                : $this->subject('merchant_identity', $merchant->identity->id, $this->identityData($merchant->identity)),
            'legal_entity' => $merchant->legalEntity === null
                ? null
                : $this->subject('legal_entity', $merchant->legalEntity->id, $this->legalEntityData($merchant->legalEntity)),
            'service' => $merchant->service_id === null
                ? null
                : $this->subject('service', $merchant->service_id, $serviceData ?? ['id' => $merchant->service_id]),
            'merchant_category' => $merchant->categories
                ->map(fn($category): array => $this->subject('merchant_category', $category->id, [
                    'id' => $category->id,
                    'category_id' => $category->category_id,
                ]))
                ->values()
                ->all(),
            'merchant_outlet' => $merchant->outlets
                ->filter(fn($outlet): bool => $outlet->status === OutletStatus::Active)
                ->map(fn(MerchantOutlet $outlet): array => $this->subject('merchant_outlet', $outlet->id, $this->outletData($outlet)))
                ->values()
                ->all(),
            'merchant_document' => $merchant->documents
                ->map(fn(MerchantDocument $document): array => $this->subject('merchant_document', $document->id, $this->documentData($document)))
                ->values()
                ->all(),
            'payout_account' => array_map(
                fn(PayoutAccountData $account): array => $this->subject('payout_account', $account->id, [
                    'id' => $account->id,
                    'bank_id' => $account->bankId,
                    'bank_name' => $account->bankName,
                    'account_number' => $account->accountNumber,
                    'account_name' => $account->accountName,
                    'is_primary' => $account->isPrimary,
                ]),
                $payoutAccounts,
            ),
        ];

        return [
            'version' => $version,
            'submitted_at' => now()->toIso8601String(),
            'merchant_type' => $merchant->type?->value,
            'subjects' => $subjects,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject_type: string, subject_id: string, data: array<string, mixed>}
     */
    private function subject(string $type, string $id, array $data): array
    {
        return [
            'subject_type' => $type,
            'subject_id' => $id,
            'data' => $data,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identityData(MerchantIdentity $identity): array
    {
        return [
            'id' => $identity->id,
            'id_type' => $identity->id_type->value,
            'id_number' => $identity->id_number,
            'full_name' => $identity->full_name,
            'birth_date' => $identity->birth_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legalEntityData(LegalEntity $legalEntity): array
    {
        return [
            'id' => $legalEntity->id,
            'entity_type' => $legalEntity->entity_type->value,
            'name' => $legalEntity->name,
            'nib' => $legalEntity->nib,
            'npwp' => $legalEntity->npwp,
            'address' => $legalEntity->address,
            'village_id' => $legalEntity->village_id,
            'postal_code' => $legalEntity->postal_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outletData(MerchantOutlet $outlet): array
    {
        return [
            'id' => $outlet->id,
            'name' => $outlet->name,
            'phone' => $outlet->phone,
            'email' => $outlet->email,
            'address' => $outlet->address,
            'village_id' => $outlet->village_id,
            'postal_code' => $outlet->postal_code,
            'latitude' => $outlet->latitude,
            'longitude' => $outlet->longitude,
            'service_area_type' => $outlet->service_area_type->value,
            'service_radius_km' => $outlet->service_radius_km,
            'operating_hours' => $outlet->operating_hours,
            'photos' => $outlet->photos,
            'status' => $outlet->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(MerchantDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type->value,
            'file_name' => $document->file_name,
            'object_key' => $document->object_key,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
        ];
    }
}
