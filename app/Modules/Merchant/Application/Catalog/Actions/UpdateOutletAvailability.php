<?php

namespace App\Modules\Merchant\Application\Catalog\Actions;

use App\Modules\Merchant\Domain\Enums\ProductAvailabilityStatus;
use App\Modules\Merchant\Domain\Models\OutletProduct;
use App\Shared\Result\Result;

final class UpdateOutletAvailability
{
    /**
     * The manual availability toggle of a product at one outlet. Going back to
     * available clears any previous unavailability reason (D8).
     *
     * @param  array{status: string, reason?: string|null}  $data
     */
    public function __invoke(OutletProduct $assignment, array $data): Result
    {
        $status = ProductAvailabilityStatus::from($data['status']);

        $assignment->update([
            'availability_status' => $status,
            'unavailable_reason' => $status === ProductAvailabilityStatus::Unavailable
                ? ($data['reason'] ?? null)
                : null,
        ]);

        return Result::ok($assignment->refresh());
    }
}
