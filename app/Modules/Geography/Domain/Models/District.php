<?php

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\DistrictFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('geography.districts')]
#[UseFactory(DistrictFactory::class)]
#[Fillable(['regency_id', 'code', 'name', 'is_active'])]
class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    /**
     * The regency this district belongs to.
     *
     * @return BelongsTo<Regency, $this>
     */
    public function regency(): BelongsTo
    {
        return $this->belongsTo(Regency::class);
    }

    /**
     * The villages that belong to this district.
     *
     * @return HasMany<Village, $this>
     */
    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }

    /**
     * Constrain the query to districts whose regency chain is active.
     *
     * @param  Builder<District>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('regency', fn (Builder $query) => $query->active());
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
