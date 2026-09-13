<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Application\Concerns\GeneratesUniqueSlug;
use App\Modules\Service\Application\Concerns\ReportsSlugConflict;
use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class StoreServiceCategory
{
    use GeneratesUniqueSlug, ReportsSlugConflict;

    public function __invoke(Service $service, array $data): Result
    {
        $data['service_id'] = $service->getKey();
        $data['slug'] = $this->uniqueCategorySlug($service->getKey(), $data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return Result::ok(ServiceCategory::create($data));
        } catch (UniqueConstraintViolationException) {
            return $this->slugConflict();
        }
    }
}
