<?php

namespace App\Modules\Merchant\Http\Operations;

use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Application\Operations\Actions\AssignOutletUser;
use App\Modules\Merchant\Application\Operations\Actions\ChangeOutletUserRole;
use App\Modules\Merchant\Application\Operations\Actions\CreateOutletEmployee;
use App\Modules\Merchant\Application\Operations\Actions\RemoveOutletUser;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use App\Modules\Merchant\Http\Operations\Requests\AssignOutletUserRequest;
use App\Modules\Merchant\Http\Operations\Requests\ChangeOutletUserRoleRequest;
use App\Modules\Merchant\Http\Operations\Requests\StoreOutletEmployeeRequest;
use App\Modules\Merchant\Http\Resources\OutletUserResource;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OutletUsersController extends Controller
{
    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly AssignOutletUser $assignOutletUser,
        private readonly ChangeOutletUserRole $changeOutletUserRole,
        private readonly RemoveOutletUser $removeOutletUser,
        private readonly CreateOutletEmployee $createOutletEmployee,
        private readonly UserLookup $userLookup,
    ) {}

    public function index(string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.outlet_users.view'),
            function (MerchantOutlet $model): JsonResponse {
                $assignments = MerchantOutletUser::query()
                    ->where('outlet_id', $model->id)
                    ->orderBy('created_at')
                    ->get();

                $users = $this->userLookup->usersByIds(
                    $assignments->pluck('user_id')->unique()->values()->all(),
                );

                foreach ($assignments as $assignment) {
                    $assignment->setAttribute('user', $users[$assignment->user_id] ?? null);
                }

                return ApiResponse::success(OutletUserResource::collection($assignments));
            },
        );
    }

    public function store(AssignOutletUserRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.outlet_users.assign'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->assignOutletUser)($model->merchant, $model, $request->validated()),
                fn (MerchantOutletUser $assignment) => ApiResponse::created($this->resource($assignment)),
            ),
        );
    }

    public function storeEmployee(StoreOutletEmployeeRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.outlet_users.assign'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->createOutletEmployee)($model->merchant, $model, $request->validated()),
                fn (MerchantOutletUser $assignment) => ApiResponse::created($this->resource($assignment)),
            ),
        );
    }

    public function update(ChangeOutletUserRoleRequest $request, string $outlet, string $user): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.outlet_users.role.update'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->changeOutletUserRole)($model, $user, (string) $request->validated('role')),
                fn (MerchantOutletUser $assignment) => ApiResponse::success($this->resource($assignment)),
            ),
        );
    }

    public function destroy(string $outlet, string $user): Response|JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizeOutletAction($outlet, 'merchant.operations.outlet_users.remove'),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->removeOutletUser)($model, $user),
                fn () => ApiResponse::noContent(),
            ),
        );
    }

    private function resource(MerchantOutletUser $assignment): OutletUserResource
    {
        $users = $this->userLookup->usersByIds([$assignment->user_id]);
        $assignment->setAttribute('user', $users[$assignment->user_id] ?? null);

        return new OutletUserResource($assignment);
    }
}
