<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\CreateProductMedia;
use App\Modules\Merchant\Application\Catalog\Actions\CreateProductMediaUploadUrl;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteProductMedia;
use App\Modules\Merchant\Application\Catalog\Actions\ReorderProductMedia;
use App\Modules\Merchant\Application\Catalog\Actions\SetPrimaryProductMedia;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Application\Catalog\Services\MediaUrlHydrator;
use App\Modules\Merchant\Domain\Models\Product;
use App\Modules\Merchant\Domain\Models\ProductMedia;
use App\Modules\Merchant\Http\Catalog\Requests\IndexProductMediaRequest;
use App\Modules\Merchant\Http\Catalog\Requests\ReorderProductMediaRequest;
use App\Modules\Merchant\Http\Catalog\Requests\StoreProductMediaRequest;
use App\Modules\Merchant\Http\Catalog\Requests\StoreProductMediaUploadUrlRequest;
use App\Modules\Merchant\Http\Resources\ProductMediaResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductMediaController extends Controller
{
    private const RESOURCE = '\App\Modules\Merchant\Http\Resources\ProductMediaResource';

    private const PAGINATED = 'array{data: array<'.self::RESOURCE.'>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}';

    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly CreateProductMediaUploadUrl $createUploadUrl,
        private readonly CreateProductMedia $createMedia,
        private readonly DeleteProductMedia $deleteMedia,
        private readonly SetPrimaryProductMedia $setPrimaryMedia,
        private readonly ReorderProductMedia $reorderMedia,
        private readonly MediaUrlHydrator $mediaUrls,
    ) {}

    #[OpenApiResponse(200, 'Product media', type: self::PAGINATED)]
    public function index(IndexProductMediaRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => $this->list($model, $request->validated()),
        );
    }

    #[OpenApiResponse(200, 'Presigned upload URL', type: 'array{data: array{object_key: string, upload_url: string, headers: array<string, string>, expires_at: string}}')]
    public function storeUploadUrl(StoreProductMediaUploadUrlRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->createUploadUrl)($model, $request->validated()),
                fn (array $upload) => ApiResponse::success($upload),
            ),
        );
    }

    #[OpenApiResponse(201, 'Media registered', type: 'array{data: '.self::RESOURCE.'}')]
    #[IgnoreResponse(200)]
    public function store(StoreProductMediaRequest $request, string $product): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->createMedia)($model, $request->validated()),
                fn (ProductMedia $media) => ApiResponse::created(
                    $this->resource($media),
                    route('api.v1.merchant.catalog.products.media.show', [
                        'product' => $model->id,
                        'media' => $media->id,
                    ]),
                ),
            ),
        );
    }

    #[OpenApiResponse(200, 'Media', type: 'array{data: '.self::RESOURCE.'}')]
    public function show(string $product, string $media): JsonResponse
    {
        return $this->actOnMedia(
            $product,
            $media,
            fn (): Result => Result::ok(null),
            fn (ProductMedia $found): JsonResponse => ApiResponse::success($this->resource($found)),
        );
    }

    #[OpenApiResponse(204, 'Media deleted')]
    #[IgnoreResponse(200)]
    public function destroy(string $product, string $media): Response|JsonResponse
    {
        return $this->actOnMedia(
            $product,
            $media,
            fn (Product $model, ProductMedia $found): Result => ($this->deleteMedia)($model, $found),
            fn (): Response => ApiResponse::noContent(),
        );
    }

    #[OpenApiResponse(200, 'Primary media', type: 'array{data: '.self::RESOURCE.'}')]
    public function setPrimary(string $product, string $media): JsonResponse
    {
        return $this->actOnMedia(
            $product,
            $media,
            fn (Product $model, ProductMedia $found): Result => ($this->setPrimaryMedia)($model, $found),
            fn (ProductMedia $updated): JsonResponse => ApiResponse::success($this->resource($updated)),
        );
    }

    #[OpenApiResponse(204, 'Media reordered')]
    #[IgnoreResponse(200)]
    public function reorder(ReorderProductMediaRequest $request, string $product): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->product($product),
            fn (Product $model) => ApiResponse::fromResult(
                ($this->reorderMedia)($model, $request->validated()['items']),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    /**
     * Resolve the product and one of its media before running the action.
     *
     * @param  callable(Product, ProductMedia): Result  $action
     * @param  callable(ProductMedia): (Response|JsonResponse)  $success
     */
    private function actOnMedia(
        string $productId,
        string $mediaId,
        callable $action,
        callable $success,
    ): Response|JsonResponse {
        return ApiResponse::fromResult(
            $this->authorization->product($productId),
            fn (Product $product) => ApiResponse::fromResult(
                $this->authorization->media($product, $mediaId),
                fn (ProductMedia $media) => ApiResponse::fromResult(
                    $action($product, $media),
                    fn (mixed $value) => $success($media),
                ),
            ),
        );
    }

    private function resource(ProductMedia $media): ProductMediaResource
    {
        $this->mediaUrls->hydrate([$media]);

        return new ProductMediaResource($media);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function list(Product $product, array $validated): JsonResponse
    {
        $query = ProductMedia::query()->where('product_id', $product->id);

        $query->orderBy($validated['sort'] ?? 'display_order', $validated['order'] ?? 'asc')
            ->orderBy('created_at')
            ->orderBy('id');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        $media = $paginator->getCollection();
        $this->mediaUrls->hydrate($media);

        return ApiResponse::paginated($paginator, ProductMediaResource::collection($media));
    }
}
