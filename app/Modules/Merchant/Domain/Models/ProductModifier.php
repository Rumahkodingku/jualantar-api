<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\ProductModifierFactory;
use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Table('merchant.product_modifiers')]
#[UseFactory(ProductModifierFactory::class)]
#[Fillable([
    'merchant_id',
    'modifier_group_id',
    'name',
    'description',
    'price',
    'status',
    'is_default',
    'display_order',
])]
class ProductModifier extends Model
{
    /** @use HasFactory<ProductModifierFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return BelongsTo<ProductModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'modifier_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
