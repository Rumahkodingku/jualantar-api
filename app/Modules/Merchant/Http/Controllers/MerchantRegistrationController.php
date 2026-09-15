<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\Merchant\Application\Actions\AttachMerchantDocument;
use App\Modules\Merchant\Application\Actions\CreateMerchantOutlet;
use App\Modules\Merchant\Application\Actions\CreateRegistrationUpload;
use App\Modules\Merchant\Application\Actions\DeleteMerchantDocument;
use App\Modules\Merchant\Application\Actions\DeleteMerchantOutlet;
use App\Modules\Merchant\Application\Actions\RegisterMerchant;
use App\Modules\Merchant\Application\Actions\ReopenMerchantRegistration;
use App\Modules\Merchant\Application\Actions\SaveLegalEntity;
use App\Modules\Merchant\Application\Actions\SaveMerchantCategories;
use App\Modules\Merchant\Application\Actions\SaveMerchantIdentity;
use App\Modules\Merchant\Application\Actions\SaveMerchantService;
use App\Modules\Merchant\Application\Actions\SavePayoutAccount;
use App\Modules\Merchant\Application\Actions\SubmitMerchantRegistration;
use App\Modules\Merchant\Application\Actions\UpdateMerchantOutlet;
use App\Modules\Merchant\Application\Actions\UpdateMerchantRegistration;
use App\Modules\Merchant\Domain\Exceptions\MerchantRegistrationNotFoundException;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantDocument;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\AttachDocumentRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\CreateUploadRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\SaveCategoriesRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\SaveIdentityRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\SaveLegalEntityRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\SavePayoutAccountRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\SaveServiceRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\StoreOutletRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\StoreRegistrationRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\UpdateOutletRequest;
use App\Modules\Merchant\Http\Requests\MerchantRegistration\UpdateRegistrationRequest;
use App\Modules\Merchant\Http\Resources\MerchantDocumentResource;
use App\Modules\Merchant\Http\Resources\MerchantRegistrationResource;
use App\Modules\Payout\Contracts\DataTransferObjects\PayoutAccountData;
use App\Modules\Payout\Contracts\PayoutAccountLookup;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MerchantRegistrationController extends Controller
{
    /**
     * Owner type understood by the Payout contract (kept primitive on purpose
     * so this module never imports the Payout domain).
     */
    private const OWNER_TYPE_MERCHANT = 'merchant';

    public function __construct(
        private readonly RegisterMerchant $registerMerchant,
        private readonly UpdateMerchantRegistration $updateMerchantRegistration,
        private readonly SaveMerchantIdentity $saveMerchantIdentity,
        private readonly SaveLegalEntity $saveLegalEntity,
        private readonly SaveMerchantService $saveMerchantService,
        private readonly SaveMerchantCategories $saveMerchantCategories,
        private readonly CreateMerchantOutlet $createMerchantOutlet,
        private readonly UpdateMerchantOutlet $updateMerchantOutlet,
        private readonly DeleteMerchantOutlet $deleteMerchantOutlet,
        private readonly CreateRegistrationUpload $createRegistrationUpload,
        private readonly AttachMerchantDocument $attachMerchantDocument,
        private readonly DeleteMerchantDocument $deleteMerchantDocument,
        private readonly SavePayoutAccount $savePayoutAccount,
        private readonly SubmitMerchantRegistration $submitMerchantRegistration,
        private readonly ReopenMerchantRegistration $reopenMerchantRegistration,
        private readonly ServiceLookup $serviceLookup,
        private readonly GeographyLookup $geographyLookup,
        private readonly PayoutAccountLookup $payoutLookup,
        private readonly ObjectStorage $storage,
    ) {}

    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->registerMerchant)((string) $request->user()->id),
            fn (Merchant $merchant) => ApiResponse::created(
                $this->statusPayload($merchant),
                route('api.v1.merchants.registration.show'),
            ),
        );
    }

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->registrationResource($this->registration()));
    }

    public function review(): JsonResponse
    {
        return ApiResponse::success($this->registrationResource($this->registration()));
    }

    public function update(UpdateRegistrationRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->updateMerchantRegistration)($merchant, $request->validated()),
            fn (Merchant $updated) => ApiResponse::success($this->registrationResource($updated)),
        );
    }

    public function saveIdentity(SaveIdentityRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->saveMerchantIdentity)($merchant, $request->validated()),
            fn (Merchant $updated) => ApiResponse::success($this->registrationResource($updated)),
        );
    }

    public function saveLegalEntity(SaveLegalEntityRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->saveLegalEntity)($merchant, $request->validated()),
            fn (Merchant $updated) => ApiResponse::success($this->registrationResource($updated)),
        );
    }

    public function saveService(SaveServiceRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->saveMerchantService)($merchant, $request->validated('service_id')),
            fn (Merchant $updated) => ApiResponse::success($this->registrationResource($updated)),
        );
    }

    public function saveCategories(SaveCategoriesRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->saveMerchantCategories)($merchant, $request->validated('category_ids')),
            fn (Merchant $updated) => ApiResponse::success($this->registrationResource($updated)),
        );
    }

    public function storeOutlet(StoreOutletRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->createMerchantOutlet)($merchant, $request->validated()),
            fn () => ApiResponse::created($this->registrationResource($merchant->refresh())),
        );
    }

    public function updateOutlet(UpdateOutletRequest $request, MerchantOutlet $outlet): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->updateMerchantOutlet)($merchant, $outlet, $request->validated()),
            fn () => ApiResponse::success($this->registrationResource($merchant->refresh())),
        );
    }

    public function destroyOutlet(MerchantOutlet $outlet): Response|JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->deleteMerchantOutlet)($merchant, $outlet),
            fn () => ApiResponse::noContent(),
        );
    }

    public function storeUpload(CreateUploadRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->createRegistrationUpload)($merchant, $request->validated()),
            fn (array $upload) => ApiResponse::created($upload),
        );
    }

    public function storeDocument(AttachDocumentRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->attachMerchantDocument)($merchant, $request->validated()),
            function (MerchantDocument $document): JsonResponse {
                $document->setAttribute('url', $this->temporaryUrl($document->object_key));

                return ApiResponse::created(new MerchantDocumentResource($document));
            },
        );
    }

    public function destroyDocument(MerchantDocument $document): Response|JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->deleteMerchantDocument)($merchant, $document),
            fn () => ApiResponse::noContent(),
        );
    }

    public function savePayoutAccount(SavePayoutAccountRequest $request): JsonResponse
    {
        $merchant = $this->registration();

        return ApiResponse::fromResult(
            ($this->savePayoutAccount)($merchant, $request->validated()),
            fn () => ApiResponse::success($this->registrationResource($merchant->refresh())),
        );
    }

    public function submit(): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->submitMerchantRegistration)($this->registration()),
            fn (Merchant $merchant) => ApiResponse::success($this->statusPayload($merchant)),
        );
    }

    public function reopen(): JsonResponse
    {
        return ApiResponse::fromResult(
            ($this->reopenMerchantRegistration)($this->registration()),
            fn (Merchant $merchant) => ApiResponse::success($this->statusPayload($merchant)),
        );
    }

    private function registration(): Merchant
    {
        $merchant = Merchant::query()
            ->where('user_id', auth()->id())
            ->first();

        if ($merchant === null) {
            throw new MerchantRegistrationNotFoundException;
        }

        return $merchant;
    }

    /**
     * @return array{merchant_id: string, status: string}
     */
    private function statusPayload(Merchant $merchant): array
    {
        return [
            'merchant_id' => $merchant->id,
            'status' => $merchant->status->value,
        ];
    }

    private function registrationResource(Merchant $merchant): MerchantRegistrationResource
    {
        $merchant->load(['identity', 'legalEntity', 'categories', 'outlets', 'documents']);

        $services = $merchant->service_id === null
            ? []
            : $this->serviceLookup->servicesByIds([$merchant->service_id]);
        $merchant->setAttribute('service', $merchant->service_id === null ? null : ($services[$merchant->service_id] ?? null));

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

        return new MerchantRegistrationResource($merchant);
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
            'bank_name' => $account->bankName,
            'account_number' => $account->accountNumber,
            'account_name' => $account->accountName,
            'is_primary' => $account->isPrimary,
            'status' => $account->status,
            'rejection_reason' => $account->rejectionReason,
        ], $accounts);
    }
}
