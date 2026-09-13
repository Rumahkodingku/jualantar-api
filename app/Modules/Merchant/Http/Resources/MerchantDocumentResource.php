<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\MerchantDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantDocument
 */
class MerchantDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'document_type' => $this->document_type->value,
            'file_name' => $this->file_name,
            'object_key' => $this->object_key,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
