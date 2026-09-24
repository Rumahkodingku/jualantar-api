<?php

namespace App\Modules\Merchant\Http\Catalog\Rules;

use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Modifier group names are unique per product, case-insensitively, among
 * groups that are not soft deleted. Mirrors the partial unique index on
 * (product_id, lower(name)) so validation fails with a 422 instead of letting
 * the database raise a query exception.
 */
final class UniqueModifierGroupName implements ValidationRule
{
    public function __construct(
        private readonly ?string $merchantId,
        private readonly ?string $productId,
        private readonly ?string $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->merchantId === null || $this->productId === null || ! is_string($value)) {
            return;
        }

        $exists = ProductModifierGroup::query()
            ->where('merchant_id', $this->merchantId)
            ->where('product_id', $this->productId)
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The name has already been taken.');
        }
    }
}
