<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\DeactivateOutletModifier;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateOutletModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateOutletProductVariant;
use App\Modules\Merchant\Application\Catalog\Actions\ResetOutletModifier;
use App\Modules\Merchant\Application\Catalog\Actions\ResetOutletModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\ResetOutletProductVariant;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\OutletProductModifier;
use App\Modules\Merchant\Domain\Models\OutletProductModifierGroup;
use App\Modules\Merchant\Domain\Models\OutletProductVariant;
use App\Modules\Merchant\Http\Resources\OutletItemOverrideResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Per-outlet status overrides written by an outlet manager.
 *
 * These routes sit outside the `merchant.owner` middleware: they are authorized
 * per outlet through CatalogAuthorization, so an employee only ever reaches the
 * items of a product assigned to an outlet they are assigned to.
 *
 * "deactivate" hides one item at this outlet without touching the master
 * catalog. "reset" drops the override so the item follows the master again — it
 * is not a promotion, and an item the owner left inactive stays inactive.
 */
class OutletItemOverrideController extends Controller
{
    private const OVERRIDE_RESOURCE = '\App\Modules\Merchant\Http\Resources\OutletItemOverrideResource';

    private const VARIANT_CAPABILITY = 'merchant.operations.catalog.variant.status.update';

    private const CUSTOMIZATION_CAPABILITY = 'merchant.operations.catalog.customization.status.update';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly DeactivateOutletProductVariant $deactivateVariant,
        private readonly ResetOutletProductVariant $resetVariant,
        private readonly DeactivateOutletModifierGroup $deactivateGroup,
        private readonly ResetOutletModifierGroup $resetGroup,
        private readonly DeactivateOutletModifier $deactivateModifier,
        private readonly ResetOutletModifier $resetModifier,
    ) {}

    #[OpenApiResponse(200, 'Variant hidden at this outlet', type: 'array{data: '.self::OVERRIDE_RESOURCE.'}')]
    public function deactivateVariant(string $outlet, string $product, string $variant): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletVariant($outlet, $product, $variant, self::VARIANT_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->deactivateVariant)($resolved[0], $resolved[1], $this->actorId()),
                fn (OutletProductVariant $override) => ApiResponse::success(new OutletItemOverrideResource($override)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Variant follows the master catalog again')]
    #[IgnoreResponse(200)]
    public function resetVariant(string $outlet, string $product, string $variant): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletVariant($outlet, $product, $variant, self::VARIANT_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->resetVariant)($resolved[0], $resolved[1]),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Customization group hidden at this outlet', type: 'array{data: '.self::OVERRIDE_RESOURCE.'}')]
    public function deactivateModifierGroup(string $outlet, string $product, string $group): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletModifierGroup($outlet, $product, $group, self::CUSTOMIZATION_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->deactivateGroup)($resolved[0], $resolved[1], $this->actorId()),
                fn (OutletProductModifierGroup $override) => ApiResponse::success(new OutletItemOverrideResource($override)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Customization group follows the master catalog again')]
    #[IgnoreResponse(200)]
    public function resetModifierGroup(string $outlet, string $product, string $group): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletModifierGroup($outlet, $product, $group, self::CUSTOMIZATION_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->resetGroup)($resolved[0], $resolved[1]),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Customization option hidden at this outlet', type: 'array{data: '.self::OVERRIDE_RESOURCE.'}')]
    public function deactivateModifier(string $outlet, string $product, string $group, string $modifier): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletModifier($outlet, $product, $group, $modifier, self::CUSTOMIZATION_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->deactivateModifier)($resolved[0], $resolved[1], $resolved[2], $this->actorId()),
                fn (OutletProductModifier $override) => ApiResponse::success(new OutletItemOverrideResource($override)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Customization option follows the master catalog again')]
    #[IgnoreResponse(200)]
    public function resetModifier(string $outlet, string $product, string $group, string $modifier): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->outletModifier($outlet, $product, $group, $modifier, self::CUSTOMIZATION_CAPABILITY),
            fn (array $resolved) => ApiResponse::fromResult(
                ($this->resetModifier)($resolved[0], $resolved[1], $resolved[2]),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * The actor recorded on the override row. Nullable so a write made outside a
     * request — seeding, a console command — stays valid.
     */
    private function actorId(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }
}
