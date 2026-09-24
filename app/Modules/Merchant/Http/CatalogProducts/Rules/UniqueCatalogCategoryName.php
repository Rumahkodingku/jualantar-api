<?php

namespace App\Modules\Merchant\Http\CatalogProducts\Rules;

use App\Modules\Merchant\Domain\Models\CatalogCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Category names are unique per merchant, case-insensitively, among categories
 * that are not soft deleted. Mirrors the partial unique index on
 * (merchant_id, lower(name)) so validation fails with a 422 instead of letting
 * the database raise a query exception.
 */
final class UniqueCatalogCategoryName implements ValidationRule
{
    public function __construct(
        private readonly ?string $merchantId,
        private readonly ?string $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->merchantId === null || ! is_string($value)) {
            return;
        }

        $exists = CatalogCategory::query()
            ->where('merchant_id', $this->merchantId)
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The name has already been taken.');
        }
    }
}
