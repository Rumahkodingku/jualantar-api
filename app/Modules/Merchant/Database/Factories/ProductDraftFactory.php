<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\ProductDraftStep;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDraft>
 */
class ProductDraftFactory extends Factory
{
    protected $model = ProductDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'version' => 1,
            'step_index' => ProductDraftStep::Info->value,
            'payload' => ProductDraft::EMPTY_PAYLOAD,
            'expires_at' => now()->addDays((int) config('merchant.product_draft.ttl_days', 7)),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }

    public function atStep(ProductDraftStep $step): static
    {
        return $this->state(fn (): array => ['step_index' => $step->value]);
    }

    public function atVersion(int $version): static
    {
        return $this->state(fn (): array => ['version' => $version]);
    }

    public function forMerchant(string $merchantId): static
    {
        return $this->state(fn (): array => ['merchant_id' => $merchantId]);
    }

    /**
     * Stage media entries in the draft payload. The objects themselves are
     * registered on the storage double by the test.
     *
     * @param  list<array<string, mixed>>  $media
     */
    public function withMedia(array $media): static
    {
        return $this->state(fn (): array => [
            'payload' => [...ProductDraft::EMPTY_PAYLOAD, 'media' => $media],
        ]);
    }
}
