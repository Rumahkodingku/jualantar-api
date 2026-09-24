<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductMedia;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class SetPrimaryProductMedia
{
    use ManagesProductMedia;

    /**
     * Swap the product primary media in a single transaction: the previous
     * primary is released before the new one is set so the partial unique index
     * is never violated.
     */
    public function __invoke(Product $product, ProductMedia $media): Result
    {
        return DB::transaction(function () use ($product, $media): Result {
            $this->clearPrimary($product);
            $media->update(['is_primary' => true]);

            return Result::ok($media->refresh());
        });
    }
}
