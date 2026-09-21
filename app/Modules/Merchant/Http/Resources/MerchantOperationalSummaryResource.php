<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Merchant
 */
class MerchantOperationalSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'merchant' => [
                'id' => $this->id,
                'business_name' => $this->business_name,
                'status' => $this->status->value,
            ],
            'operational' => [
                'status' => $this->status->value,
            ],
        ];
    }
}
