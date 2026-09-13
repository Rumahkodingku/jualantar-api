<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\LegalEntity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegalEntity
 */
class LegalEntityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type->value,
            'name' => $this->name,
            'nib' => $this->nib,
            'npwp' => $this->npwp,
            'address' => $this->address,
            'province_id' => $this->province_id,
            'regency_id' => $this->regency_id,
            'district_id' => $this->district_id,
            'village_id' => $this->village_id,
            'postal_code' => $this->postal_code,
            'geography' => $this->geography === null ? null : [
                'village' => $this->geography->village,
                'district' => $this->geography->district,
                'regency' => $this->geography->regency,
                'province' => $this->geography->province,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
