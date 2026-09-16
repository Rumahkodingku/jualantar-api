<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApplicationFactory;
use App\Modules\Merchant\Domain\Enums\MerchantApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('merchant.merchant_applications')]
#[UseFactory(MerchantApplicationFactory::class)]
#[Fillable(['merchant_id', 'application_number', 'status', 'submitted_at'])]
class MerchantApplication extends Model
{
    /** @use HasFactory<MerchantApplicationFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasOne<MerchantApproval, $this>
     */
    public function approval(): HasOne
    {
        return $this->hasOne(MerchantApproval::class, 'application_id');
    }

    /**
     * @return HasMany<MerchantApplicationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(MerchantApplicationSnapshot::class, 'application_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchantApplicationStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
