<?php

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\RegencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('geography.regencies')]
#[UseFactory(RegencyFactory::class)]
#[Fillable(['province_id', 'code', 'name', 'type', 'is_active'])]
class Regency extends Model
{
    /** @use HasFactory<RegencyFactory> */
    use HasFactory;

    /**
     * The province this regency belongs to.
     *
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * The districts that belong to this regency.
     *
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    /**
     * Constrain the query to regencies whose province chain is active.
     *
     * @param  Builder<Regency>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('province', fn (Builder $query) => $query->active());
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
