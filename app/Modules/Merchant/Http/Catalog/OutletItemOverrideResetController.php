<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\ClearOutletItemOverrides;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The owner's view of the per-outlet status overrides.
 *
 * An outlet manager can only ever restrict an item at their own outlet, so the
 * reverse map — "which outlets hid this?" — belongs to the owner. Resetting
 * here drops the override at every outlet at once; the master status is never
 * touched, so an item the owner left inactive stays inactive.
 *
 * These routes stay inside the `merchant.owner` middleware group.
 */
class OutletItemOverrideResetController extends Controller
{
    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly ClearOutletItemOverrides $clearOverrides,
    ) {}

    #[OpenApiResponse(204, 'Variant overrides cleared at every outlet')]
    #[IgnoreResponse(200)]
    public function forVariant(string $product, string $variant): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->clearOverrides)->forVariant($model, $variant),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(204, 'Customization group and option overrides cleared at every outlet')]
    #[IgnoreResponse(200)]
    public function forModifierGroup(string $product, string $group): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($model, $group),
                fn (ProductModifierGroup $found) => ApiResponse::fromResult(
                    ($this->clearOverrides)->forModifierGroup($model, $found->id),
                    fn () => ApiResponse::noContent(),
                ),
            ),
        );
    }

    #[OpenApiResponse(204, 'Customization option overrides cleared at every outlet')]
    #[IgnoreResponse(200)]
    public function forModifier(string $product, string $group, string $modifier): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($model, $group),
                fn (ProductModifierGroup $found) => ApiResponse::fromResult(
                    $this->authorization->modifier($found, $modifier),
                    fn () => ApiResponse::fromResult(
                        ($this->clearOverrides)->forModifier($model, $modifier),
                        fn () => ApiResponse::noContent(),
                    ),
                ),
            ),
        );
    }
}
