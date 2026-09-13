<?php

namespace App\Modules\Service\Domain\Models;

use App\Modules\Service\Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('service.services')]
#[UseFactory(ServiceFactory::class)]
#[Fillable(['name', 'slug', 'description', 'icon', 'is_active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasUuids;

    /**
     * The categories that belong to this service.
     *
     * @return HasMany<ServiceCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    /**
     * Constrain the query to active services.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
