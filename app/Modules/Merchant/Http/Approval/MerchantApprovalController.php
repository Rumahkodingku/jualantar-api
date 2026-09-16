<?php

namespace App\Modules\Merchant\Http\Approval;

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

        return ApiResponse::paginated($paginator, MerchantApprovalResource::collection($paginator->items()));
    }

    public function show(MerchantApproval $approval): JsonResponse
    {
        $data = ($this->showApproval)($approval);
        $this->attachOwner($data['merchant']);

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
}
