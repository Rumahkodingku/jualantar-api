<?php

namespace App\Modules\Merchant\Http\Operations;

use App\Modules\Merchant\Application\Operations\Actions\UpdateOperatingHours;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Http\Operations\Requests\UpdateOperatingHoursRequest;
use App\Modules\Merchant\Http\Resources\OperatingHoursResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OperatingHoursController extends Controller
{
    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly UpdateOperatingHours $updateOperatingHours,
    ) {}

    public function show(string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::success(
                new OperatingHoursResource($model->operating_hours ?? []),
            ),
        );
    }

    public function update(UpdateOperatingHoursRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->updateOperatingHours)($model, $request->all()),
                fn (MerchantOutlet $updated) => ApiResponse::success(
                    new OperatingHoursResource($updated->operating_hours ?? []),
                ),
            ),
        );
    }
}
