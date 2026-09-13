<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Shared\Result\Result;

final class DeactivateServiceCategory
{
    public function __invoke(ServiceCategory $category): Result
    {
        if ($category->is_active) {
            $category->update(['is_active' => false]);
        }

        return Result::ok($category->refresh());
    }
}
