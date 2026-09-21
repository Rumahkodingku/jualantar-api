<?php

namespace App\Modules\Merchant\Http\Operations;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Operations\Actions\ActivateOutlet;
use App\Modules\Merchant\Application\Operations\Actions\CreateOutlet;
use App\Modules\Merchant\Application\Operations\Actions\DeactivateOutlet;
use App\Modules\Merchant\Application\Operations\Actions\UpdateOutlet;
use App\Modules\Merchant\Application\Operations\Services\MerchantOperationsAuthorization;
use App\Modules\Merchant\Application\Operations\Services\OperationalAvailabilityResolver;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Http\Operations\Requests\ListOperationalOutletsRequest;
use App\Modules\Merchant\Http\Operations\Requests\StoreOperationalOutletRequest;
use App\Modules\Merchant\Http\Operations\Requests\UpdateOperationalOutletRequest;
use App\Modules\Merchant\Http\Resources\MerchantOutletOperationsResource;
use App\Modules\Merchant\Http\Resources\OperationalAvailabilityResource;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class OutletOperationsController extends Controller
{
    public function __construct(
        private readonly MerchantOperationsAuthorization $authorization,
        private readonly CreateOutlet $createOutlet,
        private readonly UpdateOutlet $updateOutlet,
        private readonly ActivateOutlet $activateOutlet,
        private readonly DeactivateOutlet $deactivateOutlet,
        private readonly OperationalAvailabilityResolver $availabilityResolver,
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

    public function index(ListOperationalOutletsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = $this->authorization->outletQuery();

        if (filled($search = $validated['search'] ?? null)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $query->orderBy('created_at', 'desc');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        $this->hydrateCollection($paginator->getCollection());

        return ApiResponse::paginated($paginator, MerchantOutletOperationsResource::collection($paginator->items()));
    }

    public function show(string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::success($this->resource($model)),
        );
    }

    public function store(StoreOperationalOutletRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->ownedMerchant(),
            fn (Merchant $merchant) => ApiResponse::fromResult(
                ($this->createOutlet)($merchant, $request->validated()),
                fn (MerchantOutlet $outlet) => ApiResponse::created(
                    $this->resource($outlet),
                    route('api.v1.merchant.operations.outlets.show', ['outlet' => $outlet->id]),
                ),
            ),
        );
    }

    public function update(UpdateOperationalOutletRequest $request, string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                ($this->updateOutlet)($model, $request->validated()),
                fn (MerchantOutlet $updated) => ApiResponse::success($this->resource($updated)),
            ),
        );
    }

    public function activate(string $outlet): JsonResponse
    {
        return $this->act($outlet, fn (MerchantOutlet $model) => ($this->activateOutlet)($model));
    }

    public function deactivate(string $outlet): JsonResponse
    {
        return $this->act($outlet, fn (MerchantOutlet $model) => ($this->deactivateOutlet)($model));
    }

    public function availability(string $outlet): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::success(
                new OperationalAvailabilityResource($this->availabilityResolver->resolve($model)),
            ),
        );
    }

    /**
     * @param  callable(MerchantOutlet): Result  $action
     */
    private function act(string $outlet, callable $action): JsonResponse
    {
        return ApiResponse::fromResult(
            $this->authorization->authorizedOutlet($outlet),
            fn (MerchantOutlet $model) => ApiResponse::fromResult(
                $action($model),
                fn (MerchantOutlet $updated) => ApiResponse::success($this->resource($updated)),
            ),
        );
    }

    private function resource(MerchantOutlet $outlet): MerchantOutletOperationsResource
    {
        $this->hydrateCollection(new Collection([$outlet]));

        return new MerchantOutletOperationsResource($outlet);
    }

    /**
     * @param  Collection<int, MerchantOutlet>  $outlets
     */
    private function hydrateCollection(Collection $outlets): void
    {
        if ($outlets->isEmpty()) {
            return;
        }

        $villageIds = $outlets->pluck('village_id')->unique()->values()->all();
        $labels = $this->geographyLookup->villageLabels($villageIds);

        foreach ($outlets as $outlet) {
            $outlet->setAttribute('geography', $labels[$outlet->village_id] ?? null);
            $outlet->setAttribute('photos_url', $this->temporaryUrls($outlet->photos ?? []));
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string|null>
     */
    private function temporaryUrls(array $paths): array
    {
        return array_values(array_map(fn (string $path): ?string => $this->temporaryUrl($path), $paths));
    }

    private function temporaryUrl(string $path): ?string
    {
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
