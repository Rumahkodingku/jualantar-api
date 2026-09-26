<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\ProductDraftFactory;
use App\Modules\Merchant\Domain\Enums\ProductDraftStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The resumable state of the catalog "add product" wizard.
 *
 * A merchant owns at most one draft at a time. The payload is deliberately
 * untyped: it is an in-progress form, so it is allowed to be incomplete, and
 * the completeness rules belong to the create-product endpoints, not here.
 */
#[Table('merchant.product_drafts')]
#[UseFactory(ProductDraftFactory::class)]
#[Fillable(['merchant_id', 'version', 'step_index', 'payload', 'expires_at'])]
class ProductDraft extends Model
{
    /** @use HasFactory<ProductDraftFactory> */
    use HasFactory, HasUuids;

    /**
     * The payload a brand new draft starts from.
     *
     * @var array<string, mixed>
     */
    public const EMPTY_PAYLOAD = [
        'info' => [
            'name' => '',
            'category_id' => '',
            'description' => '',
            'product_type' => 'simple',
        ],
        'price_raw' => '',
        'variants' => [],
        'modifier_groups' => [],
        'media' => [],
        'outlet_ids' => [],
    ];

    /**
     * @param  Builder<ProductDraft>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /**
     * @param  Builder<ProductDraft>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function step(): ProductDraftStep
    {
        return ProductDraftStep::tryFrom($this->step_index) ?? ProductDraftStep::Info;
    }

    /**
     * The media entries the merchant has staged, in wizard order.
     *
     * @return list<array<string, mixed>>
     */
    public function mediaEntries(): array
    {
        $media = $this->payload['media'] ?? [];

        return is_array($media) ? array_values($media) : [];
    }

    /**
     * Storage keys of every staged media entry.
     *
     * @return list<string>
     */
    public function mediaObjectKeys(): array
    {
        $keys = [];

        foreach ($this->mediaEntries() as $entry) {
            $key = $entry['object_key'] ?? null;

            if (is_string($key) && $key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'step_index' => 'integer',
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
