<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantOutletFactory;
use App\Modules\Merchant\Domain\Enums\OutletServiceAreaType;
use App\Modules\Merchant\Domain\Enums\OutletStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('merchant.merchant_outlets')]
#[UseFactory(MerchantOutletFactory::class)]
#[Fillable([
    'merchant_id',
    'name',
    'phone',
    'email',
    'address',
    'province_id',
    'regency_id',
    'district_id',
    'village_id',
    'postal_code',
    'latitude',
    'longitude',
    'service_area_type',
    'service_radius_km',
    'operating_hours',
    'photos',
    'status',
])]
class MerchantOutlet extends Model
{
    /** @use HasFactory<MerchantOutletFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<MerchantOutletUser, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(MerchantOutletUser::class, 'outlet_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_area_type' => OutletServiceAreaType::class,
            'status' => OutletStatus::class,
            'operating_hours' => 'array',
            'photos' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'service_radius_km' => 'decimal:2',
        ];
    }
}
