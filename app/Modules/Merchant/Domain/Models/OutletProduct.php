<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\OutletProductFactory;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('merchant.outlet_products')]
#[UseFactory(OutletProductFactory::class)]
#[Fillable([
    'merchant_id',
    'outlet_id',
    'product_id',
    'status',
    'availability_status',
    'unavailable_reason',
    'display_order',
])]
class OutletProduct extends Model
{
    /** @use HasFactory<OutletProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<MerchantOutlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(MerchantOutlet::class, 'outlet_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The variant overrides recorded for this one outlet.
     *
     * The local key is `outlet_id`, not the row id: an outlet holds one
     * assignment row per product, and every override row of that outlet shares
     * the outlet id. Callers narrow to one product with
     * `where('product_id', $product->id)`.
     *
     * @return HasMany<OutletProductVariant, $this>
     */
    public function variantOverrides(): HasMany
    {
        return $this->hasMany(OutletProductVariant::class, 'outlet_id', 'outlet_id');
    }

    /**
     * The modifier group overrides recorded for this one outlet.
     *
     * @return HasMany<OutletProductModifierGroup, $this>
     */
    public function modifierGroupOverrides(): HasMany
    {
        return $this->hasMany(OutletProductModifierGroup::class, 'outlet_id', 'outlet_id');
    }

    /**
     * The modifier option overrides recorded for this one outlet.
     *
     * @return HasMany<OutletProductModifier, $this>
     */
    public function modifierOverrides(): HasMany
    {
        return $this->hasMany(OutletProductModifier::class, 'outlet_id', 'outlet_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'availability_status' => ProductAvailabilityStatus::class,
        ];
    }
}
