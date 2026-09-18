<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Merchant\Domain\Models\MerchantApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Registration overview for the merchant PWA: operational merchant data plus
 * the current (or latest) application and its revisions.
 *
 * @property array{
 *     merchant: Merchant,
 *     application: MerchantApplication|null,
 *     revisions: Collection<int, mixed>
 * } $resource
 */
class MerchantRegistrationOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $application = $this->resource['application'];

        return [
            'merchant' => MerchantRegistrationResource::make($this->resource['merchant']),
            'application' => $application === null
                ? null
                : MerchantApplicationResource::make($application),
            'revisions' => MerchantApprovalRevisionResource::collection($this->resource['revisions']),
            'decision_reason' => $application?->approval?->decision_reason,
        ];
    }
}
