<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Application\Catalog\Concerns\ManagesProductMedia;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class DeleteProductMedia
{
    use ManagesProductMedia;

    /**
     * Soft delete the media row only. The physical object is intentionally left
     * in storage for P0; storage cleanup is out of scope.
     *
     * When the primary media is removed the next one by display order is
     * promoted, if any.
     */
    public function __invoke(Product $product, ProductMedia $media): Result
    {
        return DB::transaction(function () use ($product, $media): Result {
            $wasPrimary = (bool) $media->is_primary;

            $media->forceFill(['is_primary' => false])->save();
            $media->delete();

            if ($wasPrimary) {
                $next = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->orderBy('display_order')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->first();

                if ($next !== null) {
                    $next->update(['is_primary' => true]);
                }
            }

            return Result::ok(null);
        });
    }
}
