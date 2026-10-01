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
 * One outlet's deviation from a master variant's active status.
 *
 * The row only exists while the item is hidden at that outlet, so `status` is
 * always `inactive` on a live row. Deleting the row (soft) is what "follow the
 * master again" means.
 *
 * @property CatalogStatus $status
 */
#[Table('merchant.outlet_product_variants')]
#[Fillable([
    'merchant_id',
    'outlet_id',
    'product_id',
    'product_variant_id',
    'status',
    'deactivated_by',
    'deactivated_at',
])]
class OutletProductVariant extends Model
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
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
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
