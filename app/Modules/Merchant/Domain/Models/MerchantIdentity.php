<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantIdentityFactory;
use App\Modules\Merchant\Domain\Enums\MerchantIdentityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('merchant.merchant_identities')]
#[UseFactory(MerchantIdentityFactory::class)]
#[Fillable(['merchant_id', 'id_type', 'id_number', 'full_name', 'birth_date'])]
class MerchantIdentity extends Model
{
    /** @use HasFactory<MerchantIdentityFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_type' => MerchantIdentityType::class,
            'birth_date' => 'date',
        ];
    }
}
