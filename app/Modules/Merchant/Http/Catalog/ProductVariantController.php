<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateProductVariant;
use App\Modules\Merchant\Application\Catalog\Actions\CreateProductVariant;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateProductVariant;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteProductVariant;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderProductVariants;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateProductVariant;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductVariant;
use App\Modules\Merchant\Http\Catalog\Requests\IndexProductVariantRequest;
use App\Modules\Merchant\Http\Catalog\Requests\ReorderProductVariantRequest;
use App\Modules\Merchant\Http\Catalog\Requests\StoreProductVariantRequest;
use App\Modules\Merchant\Http\Catalog\Requests\UpdateProductVariantRequest;
use App\Modules\Merchant\Http\Resources\ProductVariantResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductVariantController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\ProductVariantResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateProductVariant $createVariant,
        private readonly UpdateProductVariant $updateVariant,
        private readonly DeleteProductVariant $deleteVariant,
        private readonly ActivateProductVariant $activateVariant,
        private readonly DeactivateProductVariant $deactivateVariant,
        private readonly ReorderProductVariants $reorderVariants,
    ) {}

    #[OpenApiResponse(200, 'Product variants', type: self::PAGINATED)]
    public function index(IndexProductVariantRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => $this->list($model, $request->validated()),
        );
    }

    #[OpenApiResponse(201, 'Variant created', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreProductVariantRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->createVariant)($model, $request->validated()),
                fn (ProductVariant $variant) => ApiResponse::created(
                    new ProductVariantResource($variant),
                    route('api.v1.merchant.catalog.products.variants.show', [
                        'product' => $model->id,
                        'variant' => $variant->id,
                    ]),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Variant', type: 'array{data: '.self::RESOURCE.'}')]
    public function show(string $product, string $variant): JsonResponse
    {
        return $this->actOnVariant(
            $product,
            $variant,
            fn (): Result => Result::ok(null),
            fn (Product $model, ProductVariant $found): JsonResponse => ApiResponse::success(new ProductVariantResource($found)),
        );
    }

    #[OpenApiResponse(200, 'Variant updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function update(UpdateProductVariantRequest $request, string $product, string $variant): JsonResponse
    {
        return $this->actOnVariant(
            $product,
            $variant,
            fn (Product $model, ProductVariant $found): Result => ($this->updateVariant)($model, $found, $request->validated()),
            fn (Product $model, ProductVariant $updated): JsonResponse => ApiResponse::success(new ProductVariantResource($updated)),
        );
    }

    #[OpenApiResponse(204, 'Variant deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $product, string $variant): Response|JsonResponse
    {
        return $this->actOnVariant(
            $product,
            $variant,
            fn (Product $model, ProductVariant $found): Result => ($this->deleteVariant)($model, $found),
            fn (): Response => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Variant activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $product, string $variant): JsonResponse
    {
        return $this->actOnVariant(
            $product,
            $variant,
            fn (Product $model, ProductVariant $found): Result => ($this->activateVariant)($model, $found),
            fn (Product $model, ProductVariant $updated): JsonResponse => ApiResponse::success(new ProductVariantResource($updated)),
        );
    }

    #[OpenApiResponse(200, 'Variant deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $product, string $variant): JsonResponse
    {
        return $this->actOnVariant(
            $product,
            $variant,
            fn (Product $model, ProductVariant $found): Result => ($this->deactivateVariant)($model, $found),
            fn (Product $model, ProductVariant $updated): JsonResponse => ApiResponse::success(new ProductVariantResource($updated)),
        );
    }

    #[OpenApiResponse(204, 'Variants reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderProductVariantRequest $request, string $product): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->reorderVariants)($model, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * Resolve the product and one of its variants before running the action.
     *
     * The action receives both models so it can guard the parent product state;
     * the success callback receives them too and may ignore the result value.
     *
     * @param  callable(Product, ProductVariant): Result  $action
     * @param  callable(Product, ProductVariant): (Response|JsonResponse)  $success
     */
    private function actOnVariant(
        string $productId,
        string $variantId,
        callable $action,
        callable $success,
    ): Response|JsonResponse {
        return ApiResponse::fromResult(
            $this->authorization->product($productId),
            fn (Product $product) => ApiResponse::fromResult(
                $this->authorization->variant($product, $variantId),
                fn (ProductVariant $variant) => ApiResponse::fromResult(
                    $action($product, $variant),
                    fn (mixed $value) => $success($product, $variant),
                ),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Product $product, array $validated): JsonResponse
    {
        $query = ProductVariant::query()->where('product_id', $product->id);

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, ProductVariantResource::collection($paginator->items()));
    }
}
