<?php

namespace App\Modules\Merchant\Http\Catalog\Rules;

use App\Modules\Merchant\Domain\Models\ProductModifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Modifier names are unique per group, case-insensitively, among modifiers
 * that are not soft deleted. Mirrors the partial unique index on
 * (modifier_group_id, lower(name)) so validation fails with a 422 instead of
 * letting the database raise a query exception.
 */
final class UniqueModifierName implements ValidationRule
{
    public function __construct(
        private readonly ?string $groupId,
        private readonly ?string $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->groupId === null || ! is_string($value)) {
            return;
        }

        $exists = ProductModifier::query()
            ->where('modifier_group_id', $this->groupId)
            ->whereRaw('lower(name) = lower(?)', [$value])
            ->when($this->ignoreId !== null, fn ($query) => $query->whereKeyNot($this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The name has already been taken.');
        }
    }
}
