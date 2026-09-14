<?php

namespace App\Modules\Merchant\Http\Resources;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserData
 */
class RegisteredMerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->emailVerified,
        ];
    }
}
