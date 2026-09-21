<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\Merchant\Domain\Models\MerchantOutletUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MerchantOutletUser
 */
class OutletUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'role' => $this->role->value,
            'assigned_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
