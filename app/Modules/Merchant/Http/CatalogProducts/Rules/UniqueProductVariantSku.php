<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Rules;

use App\Modules\Merchant\Domain\Models\ProductVariant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Variant SKUs are unique per merchant, case-insensitively, among variants
 * that are not soft deleted. Mirrors the partial unique index on
 * (merchant_id, lower(sku)) so validation fails with a 422 instead of letting
 * the database raise a query exception. A variant may reuse the SKU of a
 * variant that was already deleted.
 */
final class UniqueProductVariantSku implements ValidationRule
{
    public function __construct(
        private readonly ?string $merchantId,
        private readonly ?string $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->merchantId === null || ! is_string($value) || $value === '') {
            return;
        }

        $exists = ProductVariant::query()
            ->where('merchant_id', $this->merchantId)
            ->whereRaw('lower(sku) = lower(?)', [$value])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The sku has already been taken.');
        }
    }
}
