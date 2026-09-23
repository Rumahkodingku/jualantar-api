<?php

namespace App\Modules\Merchant\Http\Operations;

use App\Modules\Merchant\Application\Operations\Actions\UpdateServiceArea;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Http\Operations\Requests\UpdateServiceAreaRequest;
use App\Modules\Merchant\Http\Resources\ServiceAreaResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ServiceAreaController extends Controller
{
    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly UpdateServiceArea $updateServiceArea,
    ) {}

    public function show(string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.service_area.view'),
            fn (MerchantOutlet $model) => ApiResponse::success(new ServiceAreaResource($model)),
        );
    }

    public function update(UpdateServiceAreaRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.service_area.update'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->updateServiceArea)($model, $request->validated()),
                fn (MerchantOutlet $updated) => ApiResponse::success(new ServiceAreaResource($updated)),
            ),
        );
    }
}
