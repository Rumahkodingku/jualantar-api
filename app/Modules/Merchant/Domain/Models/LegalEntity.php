<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\LegalEntityFactory;
use App\Modules\Merchant\Domain\Enums\LegalEntityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('merchant.legal_entities')]
#[UseFactory(LegalEntityFactory::class)]
#[Fillable([
    'entity_type',
    'name',
    'nib',
    'npwp',
    'address',
    'province_id',
    'regency_id',
    'district_id',
    'village_id',
    'postal_code',
])]
class LegalEntity extends Model
{
    /** @use HasFactory<LegalEntityFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => LegalEntityType::class,
        ];
    }
}
