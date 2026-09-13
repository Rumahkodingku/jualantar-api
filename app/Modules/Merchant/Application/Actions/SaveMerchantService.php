<?php

namespace App\Modules\Merchant\Application\Actions;

use App\Modules\Merchant\Application\Concerns\ReportsRegistrationErrors;
use App\Modules\Merchant\Domain\Models\Merchant;
use App\Modules\Service\Contracts\ServiceLookup;
use App\Shared\Result\Result;
use Illuminate\Support\Facades\DB;

final class SaveMerchantService
{
    use ReportsRegistrationErrors;

    public function __construct(private readonly ServiceLookup $serviceLookup) {}

    public function __invoke(Merchant $merchant, string $serviceId): Result
    {
        if ($error = $this->requireDraft($merchant)) {
            return $error;
        }

        $service = $this->serviceLookup->servicesByIds([$serviceId])[$serviceId] ?? null;

        if ($service === null || ! $service->isActive) {
            return $this->invalidService();
        }

        DB::transaction(function () use ($merchant, $serviceId): void {
            if ($merchant->service_id !== $serviceId) {
                $merchant->categories()->delete();
            }

            $merchant->update(['service_id' => $serviceId]);
        });

        return Result::ok($merchant->refresh());
    }
}
