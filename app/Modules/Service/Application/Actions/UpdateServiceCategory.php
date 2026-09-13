<?php

namespace App\Modules\Service\Application\Actions;

use App\Modules\Service\Application\Concerns\GeneratesUniqueSlug;
use App\Modules\Service\Application\Concerns\ReportsSlugConflict;
use App\Modules\Service\Domain\Models\ServiceCategory;
use App\Shared\Result\Result;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateServiceCategory
{
    use GeneratesUniqueSlug, ReportsSlugConflict;

    public function __invoke(ServiceCategory $category, array $data): Result
    {
        if (array_key_exists('name', $data) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueCategorySlug(
                $category->service_id,
                $data['name'],
                $category->getKey(),
            );
        }

        try {
            $category->update($data);
        } catch (UniqueConstraintViolationException) {
            return $this->slugConflict();
        }

        return Result::ok($category->refresh());
    }
}
