<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\ProductDraft;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDraft
 */
class ProductDraftResource extends JsonResource
{
    /**
     * `version` is echoed back on the next save so the API can reject a write
     * that would clobber a newer draft.
     *
     * `data` is the wizard form itself: `info`, `price_raw`, `variants`,
     * `modifier_groups`, `media` and `outlet_ids`. It is stored as jsonb and is
     * deliberately untyped here, because a draft may be saved at any point of
     * completion; SaveProductDraftRequest documents the accepted shape. Each
     * media entry also carries a `preview_url` signed per read, which is not
     * part of the stored payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => (int) $this->version,
            'step_index' => (int) $this->step_index,
            'data' => $this->payload,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
