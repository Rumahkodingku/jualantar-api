<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Domain\Enums\CatalogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One outlet's deviation from a master modifier option's active status.
 *
 * @property CatalogStatus $status
 */
#[Table('merchant.outlet_product_modifiers')]
#[Fillable([
    'merchant_id',
    'outlet_id',
    'product_id',
    'product_modifier_group_id',
    'product_modifier_id',
    'status',
    'deactivated_by',
    'deactivated_at',
])]
class OutletProductModifier extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<MerchantOutlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(MerchantOutlet::class, 'outlet_id');
    }

    /**
     * @return BelongsTo<ProductModifier, $this>
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(ProductModifier::class, 'product_modifier_id');
    }

    /**
     * @return BelongsTo<ProductModifierGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductModifierGroup::class, 'product_modifier_group_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogStatus::class,
            'deactivated_at' => 'datetime',
        ];
    }
}
