<?php

namespace App\Modules\Merchant\Http\CatalogProducts;

use App\Modules\Merchant\Application\Catalog\Actions\ActivateProduct;
use App\Modules\Merchant\Application\Catalog\Actions\CreateProduct;
use App\Modules\Merchant\Application\Catalog\Actions\DeactivateProduct;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteProduct;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderProducts;
use App\Modules\Merchant\Application\Catalog\Actions\UpdateProduct;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Application\Catalog\Services\MediaUrlHydrator;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Http\CatalogProducts\Requests\IndexProductRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\ReorderProductRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\StoreProductRequest;
use App\Modules\Merchant\Http\CatalogProducts\Requests\UpdateProductRequest;
use App\Modules\Merchant\Http\Resources\ProductDetailResource;
use App\Modules\Merchant\Http\Resources\ProductResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\ProductResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly DeleteProduct $deleteProduct,
        private readonly ActivateProduct $activateProduct,
        private readonly DeactivateProduct $deactivateProduct,
        private readonly ReorderProducts $reorderProducts,
        private readonly MediaUrlHydrator $mediaUrls,
    ) {}

    #[OpenApiResponse(200, 'Products', type: self::PAGINATED)]
    public function index(IndexProductRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => $this->list($merchant, $request->validated()),
        );
    }

    #[OpenApiResponse(201, 'Product created', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreProductRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->createProduct)($merchant, $request->validated()),
                fn (Product $product) => ApiResponse::created(
                    new ProductResource($product),
                    route('api.v1.merchant.catalog.products.show', ['product' => $product->id]),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Product detail', type: 'array{data: \App\Modules\Merchant\Http\Resources\ProductDetailResource}')]
    public function show(string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            function (Product $model): JsonResponse {
                $model->load(['category', 'variants', 'media']);
                $this->mediaUrls->hydrate($model->media);

                return ApiResponse::success(new ProductDetailResource($model));
            },
        );
    }

    #[OpenApiResponse(200, 'Product updated', type: 'array{data: '.self::RESOURCE.'}')]
    public function update(UpdateProductRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->updateProduct)($model, $request->validated()),
                fn (Product $updated) => ApiResponse::success(new ProductResource($updated)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Product deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $product): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->deleteProduct)($model),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Product activated', type: 'array{data: '.self::RESOURCE.'}')]
    public function activate(string $product): JsonResponse
    {
        return $this->changeStatus($product, fn (Product $model): Result => ($this->activateProduct)($model));
    }

    #[OpenApiResponse(200, 'Product deactivated', type: 'array{data: '.self::RESOURCE.'}')]
    public function deactivate(string $product): JsonResponse
    {
        return $this->changeStatus($product, fn (Product $model): Result => ($this->deactivateProduct)($model));
    }

    #[OpenApiResponse(204, 'Products reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderProductRequest $request): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->reorderProducts)($merchant, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * @param  callable(Product): Result  $action
     */
    private function changeStatus(string $product, callable $action): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                $action($model),
                fn (Product $updated) => ApiResponse::success(new ProductResource($updated)),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Merchant $merchant, array $validated): JsonResponse
    {
        $query = Product::query()->where('merchant_id', $merchant->id);

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['product_type'])) {
            $query->where('product_type', $validated['product_type']);
        }

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return ApiResponse::paginated($paginator, ProductResource::collection($paginator->items()));
    }
}
