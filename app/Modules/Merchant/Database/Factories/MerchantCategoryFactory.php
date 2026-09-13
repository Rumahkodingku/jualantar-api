<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantCategory>
 */
class MerchantCategoryFactory extends Factory
{
    protected $model = MerchantCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            // Cross-module reference to service.categories: keep it opaque.
            'category_id' => (string) Str::uuid(),
        ];
    }
}
