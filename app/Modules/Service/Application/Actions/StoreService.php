<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Application\Concerns\GeneratesUniqueSlug;
use App\Modules\Service\Application\Concerns\ReportsSlugConflict;
use App\Modules\Service\Domain\Models\Service;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class StoreService
{
    use GeneratesUniqueSlug, ReportsSlugConflict;

    public function __invoke(array $data): Result
    {
        $data['slug'] = $this->uniqueServiceSlug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        try {
            return Result::ok(Service::create($data));
        } catch (UniqueConstraintViolationException) {
            return $this->slugConflict();
        }
    }
}
