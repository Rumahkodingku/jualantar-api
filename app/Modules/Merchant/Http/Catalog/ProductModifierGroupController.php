<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\CreateModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteModifierGroup;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderModifierGroups;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateModifierGroup;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductModifierGroup;
use App\Modules\Merchant\Http\Catalog\Requests\IndexModifierGroupRequest;
use App\Modules\Merchant\Http\Catalog\Requests\ReorderModifierGroupRequest;
use App\Modules\Merchant\Http\Catalog\Requests\StoreModifierGroupRequest;
use App\Modules\Merchant\Http\Catalog\Requests\UpdateModifierGroupRequest;
use App\Modules\Merchant\Http\Resources\ProductModifierGroupResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductModifierGroupController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\ProductModifierGroupResource';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateModifierGroup $createModifierGroup,
        private readonly UpdateModifierGroup $updateModifierGroup,
        private readonly DeleteModifierGroup $deleteModifierGroup,
        private readonly ActivateModifierGroup $activateModifierGroup,
        private readonly DeactivateModifierGroup $deactivateModifierGroup,
        private readonly ReorderModifierGroups $reorderModifierGroups,
    ) {}

    #[OpenApiResponse(200, 'Modifier groups', type: 'array{data: array<'.self::RESOURCE.'>}')]
    public function index(IndexModifierGroupRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => $this->list($model, $request->validated()),
        );
    }

    #[OpenApiResponse(201, 'Modifier group created', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreModifierGroupRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->createModifierGroup)($model, $request->validated()),
                fn (ProductModifierGroup $group) => ApiResponse::created(
                    new ProductModifierGroupResource($group),
                    route('api.v1.merchant.catalog.products.modifier_groups.show', [
                        'product' => $model->id,
                        'group' => $group->id,
                    ]),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Modifier group', type: 'array{data: '.self::RESOURCE.'}')]
    public function show(string $product, string $group): JsonResponse
    {
        return $this->actOnGroup(
            $product,
            $group,
            fn (): Result => Result::ok(null),
            fn (ProductModifierGroup $found): JsonResponse => $this->groupResponse($found),
        );
    }

    #[OpenApiResponse(200, 'Modifier group updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function update(UpdateModifierGroupRequest $request, string $product, string $group): JsonResponse
    {
        return $this->actOnGroup(
            $product,
            $group,
            fn (ProductModifierGroup $found): Result => ($this->updateModifierGroup)($found, $request->validated()),
            fn (ProductModifierGroup $updated): JsonResponse => $this->groupResponse($updated),
        );
    }

    #[OpenApiResponse(204, 'Modifier group deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $product, string $group): Response|JsonResponse
    {
        return $this->actOnGroup(
            $product,
            $group,
            fn (ProductModifierGroup $found): Result => ($this->deleteModifierGroup)($found),
            fn (): Response => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Modifier group activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $product, string $group): JsonResponse
    {
        return $this->actOnGroup(
            $product,
            $group,
            fn (ProductModifierGroup $found): Result => ($this->activateModifierGroup)($found),
            fn (ProductModifierGroup $updated): JsonResponse => $this->groupResponse($updated),
        );
    }

    #[OpenApiResponse(200, 'Modifier group deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $product, string $group): JsonResponse
    {
        return $this->actOnGroup(
            $product,
            $group,
            fn (ProductModifierGroup $found): Result => ($this->deactivateModifierGroup)($found),
            fn (ProductModifierGroup $updated): JsonResponse => $this->groupResponse($updated),
        );
    }

    #[OpenApiResponse(204, 'Modifier groups reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderModifierGroupRequest $request, string $product): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->reorderModifierGroups)($model, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * Resolve the product and one of its modifier groups before running the
     * action, so a group that belongs to another product or merchant is a 404.
     *
     * @param  callable(ProductModifierGroup): Result  $action
     * @param  callable(ProductModifierGroup): (Response|JsonResponse)  $success
     */
    private function actOnGroup(
        string $productId,
        string $groupId,
        callable $action,
        callable $success,
    ): Response|JsonResponse {
        return ApiResponse::fromResult(
            $this->authorization->product($productId),
            fn (Product $product) => ApiResponse::fromResult(
                $this->authorization->modifierGroup($product, $groupId),
                fn (ProductModifierGroup $group) => ApiResponse::fromResult(
                    $action($group),
                    fn (mixed $value) => $success($value instanceof ProductModifierGroup ? $value : $group),
                ),
            ),
        );
    }

    private function groupResponse(ProductModifierGroup $group): JsonResponse
    {
        $group->load(['modifiers' => fn ($relation) => $this->ordered($relation)]);

        return ApiResponse::success(new ProductModifierGroupResource($group));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Product $product, array $validated): JsonResponse
    {
        $query = ProductModifierGroup::query()
            ->where('product_id', $product->id)
            ->with(['modifiers' => fn ($relation) => $this->ordered($relation)]);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        return ApiResponse::success(ProductModifierGroupResource::collection($query->get()));
    }

    private function ordered(mixed $relation): mixed
    {
        return $relation
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->orderBy('id');
    }
}
