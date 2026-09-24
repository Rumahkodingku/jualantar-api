<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\ProductModifierGroupFactory;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use App\Modules\Merchant\Domain\Enums\ModifierSelectionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('merchant.product_modifier_groups')]
#[UseFactory(ProductModifierGroupFactory::class)]
#[Fillable([
    'merchant_id',
    'product_id',
    'name',
    'description',
    'selection_type',
    'min_selection',
    'max_selection',
    'is_required',
    'status',
    'display_order',
])]
class ProductModifierGroup extends Model
{
    /** @use HasFactory<ProductModifierGroupFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductModifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(ProductModifier::class, 'modifier_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selection_type' => ModifierSelectionType::class,
            'status' => CatalogStatus::class,
            'min_selection' => 'integer',
            'max_selection' => 'integer',
            'is_required' => 'boolean',
        ];
    }
}
