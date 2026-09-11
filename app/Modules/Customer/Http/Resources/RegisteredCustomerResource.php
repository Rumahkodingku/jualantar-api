<?php

namespace App\Modules\Customer\Http\Resources;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
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
            'email_verified' => $this->hasVerifiedEmail(),
        ];
    }
}
