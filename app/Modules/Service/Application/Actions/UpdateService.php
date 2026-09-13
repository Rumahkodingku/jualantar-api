<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Application\Concerns\GeneratesUniqueSlug;
use App\Modules\Service\Application\Concerns\ReportsSlugConflict;
use App\Modules\Service\Domain\Models\Service;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateService
{
    use GeneratesUniqueSlug, ReportsSlugConflict;

    public function __invoke(Service $service, array $data): Result
    {
        if (array_key_exists('name', $data) && $data['name'] !== $service->name) {
            $data['slug'] = $this->uniqueServiceSlug($data['name'], $service->getKey());
        }

        try {
            $service->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->slugConflict();
        }

        return Result::ok($service->refresh());
    }
}
