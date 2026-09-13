<?php

namespace App\Modules\Service\Application\Concerns;

use App\Modules\Service\Domain\Models\Service;
use App\Modules\Service\Domain\Models\ServiceCategory;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * Build a slug that is unique across all services.
     */
    private function uniqueServiceSlug(string $name, ?string $ignoreId = null): string
    {
        return $this->uniqueSlug($name, function (string $slug) use ($ignoreId): bool {
            return Service::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();
        });
    }

    /**
     * Build a slug that is unique within a single service.
     */
    private function uniqueCategorySlug(string $serviceId, string $name, ?string $ignoreId = null): string
    {
        return $this->uniqueSlug($name, function (string $slug) use ($serviceId, $ignoreId): bool {
            return ServiceCategory::query()
                ->where('service_id', $serviceId)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();
        });
    }

    /**
     * @param  callable(string): bool  $exists
     */
    private function uniqueSlug(string $name, callable $exists): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'item';
        }

        $candidate = $base;
        $suffix = 2;

        while ($exists($candidate)) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
