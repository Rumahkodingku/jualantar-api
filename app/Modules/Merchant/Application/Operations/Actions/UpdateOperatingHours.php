<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Services\OperatingHoursValidator;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;

final class UpdateOperatingHours
{
    public function __construct(private readonly OperatingHoursValidator $validator) {}

    /**
     * @param  array<string, mixed>  $hours
     */
    public function __invoke(MerchantOutlet $outlet, array $hours): Result
    {
        $result = $this->validator->validate($hours);

        if ($result->isErr()) {
            return $result;
        }

        $outlet->update(['operating_hours' => $result->unwrap()]);

        return Result::ok($outlet->refresh());
    }
}
