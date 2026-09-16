<?php

namespace App\Modules\Merchant\Application\Registration\Concerns;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Support\Str;

trait GeneratesSlug
{
    /**
     * Build a unique slug from the business name, appending a numeric suffix
     * when a collision exists. The current merchant is ignored on updates.
     */
    private function uniqueMerchantSlug(string $businessName, ?string $ignoreMerchantId = null): string
    {
        $base = Str::limit(Str::slug($businessName), 90, '');

        if ($base === '') {
            $base = 'merchant';
        }

        $slug = $base;
        $suffix = 2;

        while (
            Merchant::query()
                ->where('slug', $slug)
                ->when($ignoreMerchantId !== null, fn ($query) => $query->whereKeyNot($ignoreMerchantId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
