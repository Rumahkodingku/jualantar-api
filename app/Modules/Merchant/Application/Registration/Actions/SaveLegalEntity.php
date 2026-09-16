<?php

namespace App\Modules\Merchant\Application\Registration\Actions;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Registration\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\LegalEntity;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class SaveLegalEntity
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly GeographyLookup $geographyLookup) {}

    /**
     * @param  array{
     *     entity_type: string,
     *     name: string,
     *     nib: string,
     *     npwp: string,
     *     address?: string|null,
     *     province_id: int,
     *     regency_id: int,
     *     district_id: int,
     *     village_id: int,
     *     postal_code?: string|null
     * }  $data
     */
    public function __invoke(Merchant $merchant, array $data): Result
    {
        if ($error = $this->requireEditable($merchant)) {
            return $error;
        }

        if (! $this->geographyLookup->villageExists((int) $data['village_id'])) {
            return $this->invalidGeography();
        }

        $attributes = [
            'entity_type' => $data['entity_type'],
            'name' => $data['name'],
            'nib' => $data['nib'],
            'npwp' => $data['npwp'],
            'address' => $data['address'] ?? null,
            'province_id' => $data['province_id'],
            'regency_id' => $data['regency_id'],
            'district_id' => $data['district_id'],
            'village_id' => $data['village_id'],
            'postal_code' => $data['postal_code'] ?? null,
        ];

        try {
            $legalEntity = $merchant->legalEntity;

            if ($legalEntity === null) {
                $legalEntity = LegalEntity::query()->create($attributes);
            } else {
                $legalEntity->update($attributes);
            }
        } catch (UniqueConstraintViolationException) {
            return $this->legalEntityConflict();
        }

        if ($merchant->legal_entity_id !== $legalEntity->id) {
            $merchant->update(['legal_entity_id' => $legalEntity->id]);
        }

        return Result::ok($merchant->refresh());
    }
}
