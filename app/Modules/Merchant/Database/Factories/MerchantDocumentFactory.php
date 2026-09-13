<?php

namespace App\Modules\Merchant\Database\Factories;

use App\Modules\Merchant\Domain\Enums\MerchantDocumentType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MerchantDocument>
 */
class MerchantDocumentFactory extends Factory
{
    protected $model = MerchantDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'document_type' => fake()->randomElement(MerchantDocumentType::cases()),
            'file_name' => fake()->word().'.pdf',
            'object_key' => 'merchants/'.Str::uuid().'/documents/'.Str::uuid(),
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1024, 5_000_000),
        ];
    }
}
