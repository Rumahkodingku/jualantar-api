<?php

namespace App\Modules\Geography\Domain\Models;

use App\Modules\Geography\Database\Factories\VillageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('geography.villages')]
#[UseFactory(VillageFactory::class)]
#[Fillable(['district_id', 'code', 'name', 'type', 'is_active'])]
class Village extends Model
{
    /** @use HasFactory<VillageFactory> */
    use HasFactory;

    /**
     * The district this village belongs to.
     *
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Constrain the query to villages whose district chain is active.
     *
     * @param  Builder<Village>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('district', fn (Builder $query) => $query->active());
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
