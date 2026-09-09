<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Province extends Model
{
    /**
     * The regencies that belong to this province.
     *
     * @return HasMany<Regency, $this>
     */
    public function regencies(): HasMany
    {
        return $this->hasMany(Regency::class);
    }
}
