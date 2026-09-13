<?php

namespace App\Modules\Payout\Http\Resources;

use App\Modules\BankDirectory\Contracts\DataTransferObjects\BankData;
use App\Modules\Payout\Domain\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayoutAccount
 */
class PayoutAccountResource extends JsonResource
{
    public function __construct($resource, private readonly ?BankData $bank = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type->value,
            'owner_id' => $this->owner_id,
            'bank_id' => $this->bank_id,
            'bank' => $this->bank === null ? null : [
                'id' => $this->bank->id,
                'code' => $this->bank->code,
                'name' => $this->bank->name,
                'category' => $this->bank->category,
                'is_active' => $this->bank->isActive,
            ],
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'is_primary' => $this->is_primary,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by' => $this->verified_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
