<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Domain\Models\Service;
use App\Shared\Result\Result;

final class DeactivateService
{
    public function __invoke(Service $service): Result
    {
        if ($service->is_active) {
            $service->update(['is_active' => false]);
        }

        return Result::ok($service->refresh());
    }
}
