<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Merchant\Application\Catalog\Actions\CreateProductDraftMediaUploadUrl;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteProductDraft;
use App\Modules\Merchant\Application\Catalog\Actions\DeleteProductDraftMedia;
use App\Modules\Merchant\Application\Catalog\Actions\SaveProductDraft;
use App\Modules\Merchant\Application\Catalog\Actions\ShowProductDraft;
use App\Modules\Merchant\Application\Catalog\Services\CatalogAuthorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\ProductDraft;
use App\Modules\Merchant\Http\Catalog\Requests\CreateProductDraftMediaUploadUrlRequest;
use App\Modules\Merchant\Http\Catalog\Requests\DeleteProductDraftMediaRequest;
use App\Modules\Merchant\Http\Catalog\Requests\SaveProductDraftRequest;
use App\Modules\Merchant\Http\Resources\ProductDraftResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Dedoc\Scramble\Attributes\Response as OpenApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The resumable state of the catalog "add product" wizard.
 *
 * A merchant keeps at most one draft. Reads return 204 when there is nothing to
 * resume, writes are guarded by the version the client last saw, and discarding
 * is idempotent so the client can fire it after a successful create without
 * having to know whether it was already gone.
 */
class ProductDraftController extends Controller
{
    public function __construct(
        private readonly CatalogAuthorization $authorization,
        private readonly ShowProductDraft $showDraft,
        private readonly SaveProductDraft $saveDraft,
        private readonly DeleteProductDraft $deleteDraft,
        private readonly CreateProductDraftMediaUploadUrl $createUploadUrl,
        private readonly DeleteProductDraftMedia $deleteMedia,
    ) {}

    #[OpenApiResponse(200, 'Product draft', type: 'array{data: \App\Modules\Merchant\Http\Resources\ProductDraftResource}')]
    #[IgnoreResponse(204)]
    public function show(): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->showDraft)($merchant),
                fn (?ProductDraft $draft) => $draft === null
                    ? ApiResponse::noContent()
                    : ApiResponse::success(new ProductDraftResource($draft)),
            ),
        );
    }

    #[OpenApiResponse(200, 'Product draft saved', type: 'array{data: \App\Modules\Merchant\Http\Resources\ProductDraftResource}')]
    public function save(SaveProductDraftRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->saveDraft)($merchant, $request->validated()),
                fn (ProductDraft $draft) => ApiResponse::success(new ProductDraftResource($draft)),
            ),
        );
    }

    #[OpenApiResponse(204, 'Product draft discarded')]
    public function destroy(): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->deleteDraft)($merchant),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    #[OpenApiResponse(200, 'Presigned draft upload URL', type: 'array{data: array{object_key: string, upload_url: string, headers: array<string, string>, expires_at: string, preview_url: ?string}}')]
    public function storeUploadUrl(CreateProductDraftMediaUploadUrlRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->createUploadUrl)($merchant, $request->validated()),
                fn (array $upload) => ApiResponse::success($upload),
            ),
        );
    }

    #[OpenApiResponse(200, 'Draft media removed', type: 'array{data: \App\Modules\Merchant\Http\Resources\ProductDraftResource}')]
    public function destroyMedia(DeleteProductDraftMediaRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->deleteMedia)($merchant, $request->validated()['object_key']),
                fn (ProductDraft $draft) => ApiResponse::success(new ProductDraftResource($draft)),
            ),
        );
    }
}
