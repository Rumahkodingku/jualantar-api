<?php

namespace App\Modules\Merchant\Http\Approval;

use App\Modules\Geography\Contracts\DataTransferObjects\AddressLabelData;
use App\Modules\Geography\Contracts\GeographyLookup;
use App\Modules\IdentityAccess\Contracts\UserLookup;
use App\Modules\Merchant\Application\Approval\Actions\ApproveApplication;
use App\Modules\Merchant\Application\Approval\Actions\ClaimApproval;
use App\Modules\Merchant\Application\Approval\Actions\RejectApplication;
use App\Modules\Merchant\Application\Approval\Actions\ReleaseApproval;
use App\Modules\Merchant\Application\Approval\Actions\RequestRevision;
use App\Modules\Merchant\Application\Approval\Actions\ReviewComponent;
use App\Modules\Merchant\Application\Approval\Queries\ListApprovals;
use App\Modules\Merchant\Application\Approval\Queries\ShowApproval;
use App\Modules\Merchant\Application\Approval\Queries\SummarizeApprovals;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalComponent;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalReviewStatus;
use App\Modules\Merchant\Domain\Enums\MerchantApprovalSubjectType;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplicationSnapshot;
use App\Modules\Merchant\Domain\Models\MerchantApproval;
use App\Modules\Merchant\Domain\Models\MerchantApprovalRevision;
use App\Modules\Merchant\Http\Approval\Requests\IndexApprovalRequest;
use App\Modules\Merchant\Http\Approval\Requests\RejectApprovalRequest;
use App\Modules\Merchant\Http\Approval\Requests\RequestRevisionRequest;
use App\Modules\Merchant\Http\Approval\Requests\ReviewApprovalRequest;
use App\Modules\Merchant\Http\Resources\MerchantApprovalDetailResource;
use App\Modules\Merchant\Http\Resources\MerchantApprovalEventResource;
use App\Modules\Merchant\Http\Resources\MerchantApprovalResource;
use App\Modules\Merchant\Http\Resources\MerchantApprovalReviewResource;
use App\Modules\Merchant\Http\Resources\MerchantApprovalRevisionResource;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Modules\Storage\Contracts\ObjectStorage;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Result\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantApprovalController extends Controller
{
    public function __construct(
        private readonly ListApprovals $listApprovals,
        private readonly SummarizeApprovals $summarizeApprovals,
        private readonly ShowApproval $showApproval,
        private readonly ClaimApproval $claimApproval,
        private readonly ReleaseApproval $releaseApproval,
        private readonly ReviewComponent $reviewComponent,
        private readonly RequestRevision $requestRevision,
        private readonly RejectApplication $rejectApproval,
        private readonly ApproveApplication $approveApproval,
        private readonly UserLookup $userLookup,
        private readonly ServiceLookup $serviceLookup,
        private readonly GeographyLookup $geographyLookup,
        private readonly ObjectStorage $storage,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ($this->summarizeApprovals)((string) $request->user()->id),
        );
    }

    public function index(IndexApprovalRequest $request): JsonResponse
    {
        $paginator = ($this->listApprovals)($request->validated(), (string) $request->user()->id);

        $approvals = $paginator->items();

        $this->hydrateMerchantServices($approvals);

        return ApiResponse::paginated($paginator, MerchantApprovalResource::collection($approvals));
    }

    public function show(MerchantApproval $approval): JsonResponse
    {
        $data = ($this->showApproval)($approval);
        $this->attachOwner($data['merchant']);
        $this->enrichSnapshot($data['snapshot']);

        return ApiResponse::success(new MerchantApprovalDetailResource($data));
    }

    public function claim(MerchantApproval $approval, Request $request): JsonResponse
    {
        return $this->approvalResult(
            ($this->claimApproval)($approval, (string) $request->user()->id),
        );
    }

    public function release(MerchantApproval $approval, Request $request): JsonResponse
    {
        return $this->approvalResult(
            ($this->releaseApproval)($approval, (string) $request->user()->id),
        );
    }

    public function review(ReviewApprovalRequest $request, MerchantApproval $approval): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::fromResult(
            ($this->reviewComponent)(
                $approval,
                (string) $request->user()->id,
                MerchantApprovalComponent::from($validated['component']),
                MerchantApprovalSubjectType::from($validated['subject_type']),
                $validated['subject_id'],
                MerchantApprovalReviewStatus::from($validated['status']),
                $validated['note'] ?? null,
            ),
            fn (array $payload) => $payload['created']
                ? ApiResponse::created(new MerchantApprovalReviewResource($payload['review']))
                : ApiResponse::success(new MerchantApprovalReviewResource($payload['review'])),
        );
    }

    public function revision(RequestRevisionRequest $request, MerchantApproval $approval): JsonResponse
    {
        $validated = $request->validated();

        return ApiResponse::fromResult(
            ($this->requestRevision)(
                $approval,
                (string) $request->user()->id,
                $validated['note'] ?? null,
                $validated['items'],
            ),
            fn (MerchantApprovalRevision $revision) => ApiResponse::created(
                new MerchantApprovalRevisionResource($revision->load('items')),
            ),
        );
    }

    public function reject(RejectApprovalRequest $request, MerchantApproval $approval): JsonResponse
    {
        return $this->approvalResult(
            ($this->rejectApproval)(
                $approval,
                (string) $request->user()->id,
                $request->validated('reason'),
            ),
        );
    }

    public function approve(MerchantApproval $approval, Request $request): JsonResponse
    {
        return $this->approvalResult(
            ($this->approveApproval)($approval, (string) $request->user()->id),
        );
    }

    public function events(MerchantApproval $approval): JsonResponse
    {
        return ApiResponse::success(MerchantApprovalEventResource::collection(
            $approval->events()->orderBy('created_at')->get(),
        ));
    }

    public function revisions(MerchantApproval $approval): JsonResponse
    {
        return ApiResponse::success(MerchantApprovalRevisionResource::collection(
            $approval->revisions()->with('items')->latest('requested_at')->get(),
        ));
    }

    private function approvalResult(Result $result): JsonResponse
    {
        return ApiResponse::fromResult(
            $result,
            fn (MerchantApproval $approval) => ApiResponse::success(new MerchantApprovalResource($approval)),
        );
    }

    private function attachOwner(Merchant $merchant): void
    {
        if ($merchant->user_id === null) {
            $merchant->setAttribute('owner', null);

            return;
        }

        $owners = $this->userLookup->usersByIds([$merchant->user_id]);
        $merchant->setAttribute('owner', $owners[$merchant->user_id] ?? null);
    }

    /**
     * @param  list<MerchantApproval>  $approvals
     */
    private function hydrateMerchantServices(array $approvals): void
    {
        $merchants = [];

        foreach ($approvals as $approval) {
            $merchant = $approval->application?->merchant;

            if ($merchant instanceof Merchant) {
                $merchants[] = $merchant;
            }
        }

        if ($merchants === []) {
            return;
        }

        $serviceIds = array_values(array_unique(array_filter(
            array_map(fn (Merchant $merchant): ?string => $merchant->service_id, $merchants),
        )));

        $services = $serviceIds === [] ? [] : $this->serviceLookup->servicesByIds($serviceIds);

        foreach ($merchants as $merchant) {
            $merchant->setAttribute(
                'service',
                $merchant->service_id === null ? null : ($services[$merchant->service_id] ?? null),
            );
        }
    }

    private function enrichSnapshot(?MerchantApplicationSnapshot $snapshot): void
    {
        if ($snapshot === null) {
            return;
        }

        $payload = $snapshot->snapshot;
        $subjects = $payload['subjects'] ?? [];

        if (isset($subjects['merchant']) && is_array($subjects['merchant'])) {
            $subjects['merchant'] = $this->attachMerchantLogoUrl($subjects['merchant']);
        }

        if (isset($subjects['service']) && is_array($subjects['service'])) {
            $subjects['service'] = $this->attachServiceName($subjects['service']);
        }

        if (isset($subjects['merchant_category']) && is_array($subjects['merchant_category'])) {
            $subjects['merchant_category'] = $this->attachCategoryNames($subjects['merchant_category']);
        }

        if (isset($subjects['merchant_outlet']) && is_array($subjects['merchant_outlet'])) {
            $subjects['merchant_outlet'] = $this->attachOutletGeography($subjects['merchant_outlet']);
            $subjects['merchant_outlet'] = $this->attachOutletPhotoUrls($subjects['merchant_outlet']);
        }

        if (isset($subjects['legal_entity']) && is_array($subjects['legal_entity'])) {
            $subjects['legal_entity'] = $this->attachLegalEntityGeography($subjects['legal_entity']);
        }

        if (isset($subjects['merchant_document']) && is_array($subjects['merchant_document'])) {
            $subjects['merchant_document'] = $this->attachDocumentUrls($subjects['merchant_document']);
        }

        $payload['subjects'] = $subjects;
        $snapshot->setAttribute('snapshot', $payload);
    }

    /**
     * @param  array<string, mixed>  $merchant
     * @return array<string, mixed>
     */
    private function attachMerchantLogoUrl(array $merchant): array
    {
        $logo = $merchant['data']['logo'] ?? null;

        if (is_string($logo) && $logo !== '') {
            $merchant['data']['logo_url'] = $this->temporaryUrl($logo);
        }

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array<string, mixed>
     */
    private function attachServiceName(array $service): array
    {
        $name = $service['data']['name'] ?? null;

        if (is_string($name) && $name !== '') {
            return $service;
        }

        $serviceId = $service['data']['id'] ?? null;

        if (! is_string($serviceId) || $serviceId === '') {
            return $service;
        }

        $services = $this->serviceLookup->servicesByIds([$serviceId]);

        if (! isset($services[$serviceId])) {
            return $service;
        }

        $service['data']['name'] = $services[$serviceId]->name;
        $service['data']['slug'] = $services[$serviceId]->slug;

        return $service;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function attachCategoryNames(array $categories): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map(
            fn (array $category): ?string => $category['data']['category_id'] ?? null,
            $categories,
        ))));

        if ($categoryIds === []) {
            return $categories;
        }

        $categoryData = $this->serviceLookup->categoriesByIds($categoryIds);

        foreach ($categories as $index => $category) {
            $categoryId = $category['data']['category_id'] ?? null;

            if (! is_string($categoryId) || ! isset($categoryData[$categoryId])) {
                continue;
            }

            $categories[$index]['data']['name'] = $categoryData[$categoryId]->name;
            $categories[$index]['data']['slug'] = $categoryData[$categoryId]->slug;
        }

        return $categories;
    }

    /**
     * @param  array<int, array<string, mixed>>  $outlets
     * @return array<int, array<string, mixed>>
     */
    private function attachOutletGeography(array $outlets): array
    {
        $villageIds = array_values(array_unique(array_filter(array_map(
            fn (array $outlet): ?int => isset($outlet['data']['village_id'])
                ? (int) $outlet['data']['village_id']
                : null,
            $outlets,
        ))));

        if ($villageIds === []) {
            return $outlets;
        }

        $labels = $this->geographyLookup->villageLabels($villageIds);

        foreach ($outlets as $index => $outlet) {
            $villageId = $outlet['data']['village_id'] ?? null;

            if ($villageId === null || ! isset($labels[(int) $villageId])) {
                continue;
            }

            $outlets[$index]['data']['geography'] = $this->geographyPayload($labels[(int) $villageId]);
        }

        return $outlets;
    }

    /**
     * @param  array<int, array<string, mixed>>  $outlets
     * @return array<int, array<string, mixed>>
     */
    private function attachOutletPhotoUrls(array $outlets): array
    {
        foreach ($outlets as $index => $outlet) {
            $photos = $outlet['data']['photos'] ?? null;

            if (! is_array($photos)) {
                continue;
            }

            $outlets[$index]['data']['photos_url'] = array_values(array_map(
                fn (mixed $path): ?string => is_string($path) && $path !== '' ? $this->temporaryUrl($path) : null,
                $photos,
            ));
        }

        return $outlets;
    }

    /**
     * @param  array<string, mixed>  $legalEntity
     * @return array<string, mixed>
     */
    private function attachLegalEntityGeography(array $legalEntity): array
    {
        $villageId = $legalEntity['data']['village_id'] ?? null;

        if ($villageId === null) {
            return $legalEntity;
        }

        $labels = $this->geographyLookup->villageLabels([(int) $villageId]);

        if (isset($labels[(int) $villageId])) {
            $legalEntity['data']['geography'] = $this->geographyPayload($labels[(int) $villageId]);
        }

        return $legalEntity;
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function attachDocumentUrls(array $documents): array
    {
        foreach ($documents as $index => $document) {
            $objectKey = $document['data']['object_key'] ?? null;

            if (! is_string($objectKey) || $objectKey === '') {
                continue;
            }

            $documents[$index]['data']['url'] = $this->temporaryUrl($objectKey);
        }

        return $documents;
    }

    /**
     * @return array{village: string, district: string, regency: string, province: string}
     */
    private function geographyPayload(AddressLabelData $label): array
    {
        return [
            'village' => $label->village,
            'district' => $label->district,
            'regency' => $label->regency,
            'province' => $label->province,
        ];
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
