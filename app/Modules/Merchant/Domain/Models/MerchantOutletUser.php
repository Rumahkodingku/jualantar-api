<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantOutletUserFactory;
use App\Modules\Merchant\Domain\Enums\OutletUserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('merchant.merchant_outlet_users')]
#[UseFactory(MerchantOutletUserFactory::class)]
#[Fillable([
    'merchant_id',
    'outlet_id',
    'user_id',
    'role',
])]
class MerchantOutletUser extends Model
{
    /** @use HasFactory<MerchantOutletUserFactory> */
    use HasFactory, HasUuids;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OutletUserRole::class,
        ];
    }
}
