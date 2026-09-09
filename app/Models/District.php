<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['regency_id', 'code', 'name'])]
class District extends Model
{
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
}
