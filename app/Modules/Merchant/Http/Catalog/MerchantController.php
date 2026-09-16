<?php

namespace App\Modules\Merchant\Http\Catalog;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Http\Resources\MerchantDetailResource;
use App\Modules\Merchant\Http\Resources\MerchantResource;
use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MerchantController extends Controller
{
    /**
     * Owner type understood by the Payout contract (kept primitive on purpose
     * so this module never imports the Payout domain).
     */
    private const OWNER_TYPE_MERCHANT = 'merchant';

    public function __construct(
        private readonly ServiceLookup $serviceLookup,
        private readonly UserLookup $userLookup,
        private readonly GeographyLookup $geographyLookup,
        private readonly PayoutAccountLookup $payoutLookup,
        private readonly ObjectStorage $storage,
    ) {}

    public function index(IndexMerchantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Merchant::query();

        if (filled($search = $validated['search'] ?? null)) {
            $query->where(function ($query) use ($search): void {
                $query->where('business_name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['service_id'])) {
            $query->where('service_id', $validated['service_id']);
        }

        $query->orderBy($validated['sort'] ?? 'created_at', $validated['order'] ?? 'desc');

        $paginator = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        $merchants = $paginator->items();

        $services = $this->serviceLookup->servicesByIds(array_values(array_unique(array_filter(array_map(
            fn (Merchant $merchant): ?string => $merchant->service_id,
            $merchants,
        )))));

        $owners = $this->userLookup->usersByIds(array_values(array_unique(array_filter(
            array_map(fn (Merchant $merchant): ?string => $merchant->user_id, $merchants),
        ))));

        foreach ($merchants as $merchant) {
            $merchant->setAttribute('service', $merchant->service_id === null ? null : ($services[$merchant->service_id] ?? null));
            $merchant->setAttribute('owner', $merchant->user_id === null ? null : ($owners[$merchant->user_id] ?? null));
        }

        return ApiResponse::paginated($paginator, MerchantResource::collection($merchants));
    }

    public function show(Merchant $merchant): JsonResponse
    {
        $merchant->load(['identity', 'legalEntity', 'categories', 'outlets', 'documents']);

        $services = $merchant->service_id === null
            ? []
            : $this->serviceLookup->servicesByIds([$merchant->service_id]);
        $merchant->setAttribute('service', $merchant->service_id === null ? null : ($services[$merchant->service_id] ?? null));

        $users = $this->userLookup->usersByIds(array_values(array_filter([$merchant->user_id])));
        $merchant->setAttribute('owner', $merchant->user_id === null ? null : ($users[$merchant->user_id] ?? null));

        $categoryData = $this->serviceLookup->categoriesByIds(
            $merchant->categories->pluck('category_id')->all(),
        );

        foreach ($merchant->categories as $category) {
            $category->setAttribute('category', $categoryData[$category->category_id] ?? null);
        }

        $villageIds = $merchant->outlets->pluck('village_id')->all();

        if ($merchant->legalEntity?->village_id !== null) {
            $villageIds[] = $merchant->legalEntity->village_id;
        }

        $labels = $this->geographyLookup->villageLabels(array_values(array_unique($villageIds)));

        foreach ($merchant->outlets as $outlet) {
            $outlet->setAttribute('geography', $labels[$outlet->village_id] ?? null);
            $outlet->setAttribute('photos_url', $this->temporaryUrls($outlet->photos ?? []));
        }

        if ($merchant->legalEntity !== null) {
            $merchant->legalEntity->setAttribute('geography', $labels[$merchant->legalEntity->village_id] ?? null);
        }

        foreach ($merchant->documents as $document) {
            $document->setAttribute('url', $this->temporaryUrl($document->object_key));
        }

        $merchant->setAttribute('logo_url', $this->temporaryUrl($merchant->logo));
        $merchant->setAttribute('payout_accounts', $this->formatPayoutAccounts(
            $this->payoutLookup->accountsForOwner(self::OWNER_TYPE_MERCHANT, $merchant->id),
        ));

        return ApiResponse::success(new MerchantDetailResource($merchant));
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

    /**
     * @param  list<string>  $paths
     * @return list<string|null>
     */
    private function temporaryUrls(array $paths): array
    {
        return array_values(array_map(fn (string $path): ?string => $this->temporaryUrl($path), $paths));
    }

    /**
     * @param  list<PayoutAccountData>  $accounts
     * @return list<array<string, mixed>>
     */
    private function formatPayoutAccounts(array $accounts): array
    {
        return array_map(fn (PayoutAccountData $account): array => [
            'id' => $account->id,
            'bank_id' => $account->bankId,
            'account_number' => $account->accountNumber,
            'account_name' => $account->accountName,
            'is_primary' => $account->isPrimary,
            'status' => $account->status,
            'rejection_reason' => $account->rejectionReason,
        ], $accounts);
    }
}
