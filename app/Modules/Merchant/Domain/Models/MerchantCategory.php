<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('merchant.merchant_categories')]
#[UseFactory(MerchantCategoryFactory::class)]
#[Fillable(['merchant_id', 'category_id'])]
class MerchantCategory extends Model
{
    /** @use HasFactory<MerchantCategoryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
