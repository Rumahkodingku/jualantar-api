<?php

namespace App\Modules\Merchant\Application\Operations\Actions;

use App\Modules\Merchant\Application\Operations\Services\ServiceAreaValidator;
use App\Modules\Merchant\Domain\Models\MerchantOutlet;
use App\Shared\Result\Result;

final class UpdateServiceArea
{
    public function __construct(private readonly ServiceAreaValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(MerchantOutlet $outlet, array $data): Result
    {
        $result = $this->validator->validate($outlet, $data);

        if ($result->isErr()) {
            return $result;
        }

        $normalized = $result->unwrap();

        $outlet->update([
            'service_area_type' => $normalized['type'],
            'service_radius_km' => $normalized['radius_km'],
        ]);

        return Result::ok($outlet->refresh());
    }
}
