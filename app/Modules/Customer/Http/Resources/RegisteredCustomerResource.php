<?php

namespace App\Modules\Customer\Http\Resources;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserData
 */
class RegisteredCustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->id,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
        ];
    }
}
