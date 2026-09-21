<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantFactory;
use App\Modules\Merchant\Domain\Enums\MerchantStatus;
use App\Modules\Merchant\Domain\Enums\MerchantType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('merchant.merchants')]
#[UseFactory(MerchantFactory::class)]
#[Fillable([
    'user_id',
    'legal_entity_id',
    'service_id',
    'business_name',
    'slug',
    'description',
    'operational_phone',
    'operational_email',
    'website',
    'type',
    'logo',
    'status',
])]
class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasOne<MerchantIdentity, $this>
     */
    public function identity(): HasOne
    {
        return $this->hasOne(MerchantIdentity::class);
    }

    /**
     * @return BelongsTo<LegalEntity, $this>
     */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    /**
     * @return HasMany<MerchantCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(MerchantCategory::class);
    }

    /**
     * @return HasMany<MerchantOutlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(MerchantOutlet::class);
    }

    /**
     * @return HasMany<MerchantDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(MerchantDocument::class);
    }

    /**
     * @return HasMany<MerchantOutletUser, $this>
     */
    public function outletUsers(): HasMany
    {
        return $this->hasMany(MerchantOutletUser::class);
    }

    /**
     * @return HasMany<MerchantApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(MerchantApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MerchantType::class,
            'status' => MerchantStatus::class,
        ];
    }
}
