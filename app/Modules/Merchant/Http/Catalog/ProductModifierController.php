<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateModifier;
use App\Modules\Merchant\Application\Catalog\Actions\CreateModifier;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateModifier;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteModifier;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderModifiers;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateModifier;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifier;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Http\Catalog\Requests\IndexModifierRequest;
use App\Modules\Merchant\Http\Catalog\Requests\ReorderModifierRequest;
use App\Modules\Merchant\Http\Catalog\Requests\StoreModifierRequest;
use App\Modules\Merchant\Http\Catalog\Requests\UpdateModifierRequest;
use App\Modules\Merchant\Http\Resources\ProductModifierResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductModifierController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\ProductModifierResource';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateModifier $createModifier,
        private readonly UpdateModifier $updateModifier,
        private readonly DeleteModifier $deleteModifier,
        private readonly ActivateModifier $activateModifier,
        private readonly DeactivateModifier $deactivateModifier,
        private readonly ReorderModifiers $reorderModifiers,
    ) {}

    #[OpenApiResponse(200, 'Modifiers', type: 'array{data: array<'.self::RESOURCE.'>}')]
    public function index(IndexModifierRequest $request, string $product, string $group): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($model, $group),
                fn (ProductModifierGroup $found) => $this->list($found, $request->validated()),
            ),
        );
    }

    #[OpenApiResponse(201, 'Modifier created', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreModifierRequest $request, string $product, string $group): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($model, $group),
                fn (ProductModifierGroup $found) => ApiResponse::fromResult(
                    ($this->createModifier)($found, $request->validated()),
                    fn (ProductModifier $modifier) => ApiResponse::created(
                        new ProductModifierResource($modifier),
                        route('api.v1.merchant.catalog.products.modifier_groups.modifiers.show', [
                            'product' => $model->id,
                            'group' => $found->id,
                            'modifier' => $modifier->id,
                        ]),
                    ),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Modifier', type: 'array{data: '.self::RESOURCE.'}')]
    public function show(string $product, string $group, string $modifier): JsonResponse
    {
        return $this->actOnModifier(
            $product,
            $group,
            $modifier,
            fn (): Result => Result::ok(null),
            fn (ProductModifier $found): JsonResponse => ApiResponse::success(new ProductModifierResource($found)),
        );
    }

    #[OpenApiResponse(200, 'Modifier updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function update(UpdateModifierRequest $request, string $product, string $group, string $modifier): JsonResponse
    {
        return $this->actOnModifier(
            $product,
            $group,
            $modifier,
            fn (ProductModifierGroup $parent, ProductModifier $found): Result => ($this->updateModifier)($parent, $found, $request->validated()),
            fn (ProductModifier $updated): JsonResponse => ApiResponse::success(new ProductModifierResource($updated)),
        );
    }

    #[OpenApiResponse(204, 'Modifier deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $product, string $group, string $modifier): Response|JsonResponse
    {
        return $this->actOnModifier(
            $product,
            $group,
            $modifier,
            fn (ProductModifierGroup $parent, ProductModifier $found): Result => ($this->deleteModifier)($parent, $found),
            fn (): Response => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Modifier activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $product, string $group, string $modifier): JsonResponse
    {
        return $this->actOnModifier(
            $product,
            $group,
            $modifier,
            fn (ProductModifierGroup $parent, ProductModifier $found): Result => ($this->activateModifier)($parent, $found),
            fn (ProductModifier $updated): JsonResponse => ApiResponse::success(new ProductModifierResource($updated)),
        );
    }

    #[OpenApiResponse(200, 'Modifier deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $product, string $group, string $modifier): JsonResponse
    {
        return $this->actOnModifier(
            $product,
            $group,
            $modifier,
            fn (ProductModifierGroup $parent, ProductModifier $found): Result => ($this->deactivateModifier)($parent, $found),
            fn (ProductModifier $updated): JsonResponse => ApiResponse::success(new ProductModifierResource($updated)),
        );
    }

    #[OpenApiResponse(204, 'Modifiers reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderModifierRequest $request, string $product, string $group): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($model, $group),
                fn (ProductModifierGroup $found) => ApiResponse::fromResult(
                    ($this->reorderModifiers)($found, $request->validated()['items']),
                    fn () => ApiResponse::noContent(),
                ),
            ),
        );
    }

    /**
     * Resolve the product, one of its groups and one of its modifiers before
     * running the action, so a modifier reached through the wrong group is 404.
     *
     * @param  callable(ProductModifierGroup, ProductModifier): Result  $action
     * @param  callable(ProductModifier): (Response|JsonResponse)  $success
     */
    private function actOnModifier(
        string $productId,
        string $groupId,
        string $modifierId,
        callable $action,
        callable $success,
    ): Response|JsonResponse {
        return ApiResponse::fromResult(
            $this->authorization->product($productId),
            fn (Product $product) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($product, $groupId),
                fn (ProductModifierGroup $group) => ApiResponse::fromResult(
                    $this->authorization->modifier($group, $modifierId),
                    fn (ProductModifier $modifier) => ApiResponse::fromResult(
                        $action($group, $modifier),
                        fn (mixed $value) => $success($value instanceof ProductModifier ? $value : $modifier),
                    ),
                ),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(ProductModifierGroup $group, array $validated): JsonResponse
    {
        $query = ProductModifier::query()->where('modifier_group_id', $group->id);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        return ApiResponse::success(ProductModifierResource::collection($query->get()));
    }
}
