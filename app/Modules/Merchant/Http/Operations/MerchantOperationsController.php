<?php

namespace App\Modules\Merchant\Http\Operations;

use App\Modules\Merchant\Application\Operations\Actions\ActivateMerchant;
use App\Modules\Merchant\Application\Operations\Actions\CreateOperationalUpload;
use App\Modules\Merchant\Application\Operations\Actions\ReactivateMerchant;
use App\Modules\Merchant\Application\Operations\Actions\SuspendMerchant;
use App\Modules\Merchant\Application\Operations\Actions\UpdateMerchantOperationalProfile;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Http\Operations\Requests\CreateOperationalUploadRequest;
use App\Modules\Merchant\Http\Operations\Requests\UpdateMerchantOperationalProfileRequest;
use App\Modules\Merchant\Http\Resources\MerchantOperationalProfileResource;
use App\Modules\Merchant\Http\Resources\MerchantOperationalStatusResource;
use App\Modules\Merchant\Http\Resources\MerchantOperationalSummaryResource;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Illuminate\Http\JsonResponse;

class MerchantOperationsController extends Controller
{
    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly ActivateMerchant $activateMerchant,
        private readonly SuspendMerchant $suspendMerchant,
        private readonly ReactivateMerchant $reactivateMerchant,
        private readonly UpdateMerchantOperationalProfile $updateProfile,
        private readonly CreateOperationalUpload $createOperationalUpload,
        private readonly ObjectStorage $storage,
    ) {}

    public function summary(): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchantContext(),
            fn (Merchant $merchant) => ApiResponse::success(new MerchantOperationalSummaryResource($merchant)),
        );
    }

    public function activate(): JsonResponse
    {
        return $this->actOnOwnedMerchant(
            fn (Merchant $merchant) => ($this->activateMerchant)($merchant),
        );
    }

    public function suspend(): JsonResponse
    {
        return $this->actOnOwnedMerchant(
            fn (Merchant $merchant) => ($this->suspendMerchant)($merchant),
        );
    }

    public function reactivate(): JsonResponse
    {
        return $this->actOnOwnedMerchant(
            fn (Merchant $merchant) => ($this->reactivateMerchant)($merchant),
        );
    }

    public function showProfile(): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchantContext(),
            fn (Merchant $merchant) => ApiResponse::success($this->profile($merchant)),
        );
    }

    public function updateProfile(UpdateMerchantOperationalProfileRequest $request): JsonResponse
    {
        return $this->actOnOwnedMerchant(
            fn (Merchant $merchant) => ($this->updateProfile)($merchant, $request->validated()),
            fn (Merchant $merchant) => $this->profile($merchant),
        );
    }

    public function storeUpload(CreateOperationalUploadRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->merchantContext(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->createOperationalUpload)($merchant, $request->validated()),
                fn (array $upload) => ApiResponse::created($upload),
            ),
        );
    }

    /**
     * Run a use case against the owned merchant and render its response.
     *
     * @param  callable(Merchant): Result  $action
     * @param  (callable(Merchant): mixed)|null  $resource
     */
    private function actOnOwnedMerchant(callable $action, ?callable $resource = null): JsonResponse
    {
        $resource ??= fn (Merchant $merchant) => new MerchantOperationalStatusResource($merchant);

        return ApiResponse::fromResult(
            $this->authorization->ownedMerchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                $action($merchant),
                fn (Merchant $updated) => ApiResponse::success($resource($updated)),
            ),
        );
    }

    private function profile(Merchant $merchant): MerchantOperationalProfileResource
    {
        $merchant->setAttribute('logo_url', $this->temporaryUrl($merchant->logo));

        return new MerchantOperationalProfileResource($merchant);
    }

    private function temporaryUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        try {
            return $this->storage->temporaryUrl(
                $path,
                now()->addSeconds((int) config('storage.temporary_url.ttl', 300)),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
