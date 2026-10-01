<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\ProductFactory;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('merchant.products')]
#[UseFactory(ProductFactory::class)]
#[Fillable([
    'merchant_id',
    'category_id',
    'name',
    'description',
    'product_type',
    'price',
    'status',
    'display_order',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class);
    }

    /**
     * @return HasMany<ProductModifierGroup, $this>
     */
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ProductModifierGroup::class);
    }

    /**
     * @return HasMany<OutletProduct, $this>
     */
    public function outletProducts(): HasMany
    {
        return $this->hasMany(OutletProduct::class);
    }

    /**
     * Every per-outlet variant restriction of this product, across outlets.
     *
     * @return HasMany<OutletProductVariant, $this>
     */
    public function outletVariantOverrides(): HasMany
    {
        return $this->hasMany(OutletProductVariant::class, 'product_id');
    }

    /**
     * @return HasMany<OutletProductModifierGroup, $this>
     */
    public function outletModifierGroupOverrides(): HasMany
    {
        return $this->hasMany(OutletProductModifierGroup::class, 'product_id');
    }

    /**
     * @return HasMany<OutletProductModifier, $this>
     */
    public function outletModifierOverrides(): HasMany
    {
        return $this->hasMany(OutletProductModifier::class, 'product_id');
    }

    public function isVariable(): bool
    {
        return $this->product_type === ProductType::Variable;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => CatalogStatus::class,
            'price' => 'decimal:2',
        ];
    }
}
