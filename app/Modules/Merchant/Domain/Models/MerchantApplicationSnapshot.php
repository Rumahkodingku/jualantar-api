<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantApplicationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of the data submitted at a point in time. Snapshots are
 * never updated or deleted through the model.
 */
#[Table('merchant.merchant_application_snapshots')]
#[UseFactory(MerchantApplicationSnapshotFactory::class)]
#[Fillable(['application_id', 'version', 'snapshot', 'submitted_at'])]
class MerchantApplicationSnapshot extends Model
{
    /** @use HasFactory<MerchantApplicationSnapshotFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    /**
     * @return BelongsTo<MerchantApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MerchantApplication::class, 'application_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
