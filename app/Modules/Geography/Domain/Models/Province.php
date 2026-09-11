<?php

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('geography.provinces')]
#[UseFactory(ProvinceFactory::class)]
#[Fillable(['code', 'name', 'is_active'])]
class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    /**
     * The regencies that belong to this province.
     *
     * @return HasMany<Regency, $this>
     */
    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }

    /**
     * Constrain the query to provinces that are effectively active.
     *
     * @param  Builder<Province>  $query
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
