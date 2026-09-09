<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'category', 'address', 'phone', 'website', 'is_active'])]
class Bank extends Model
{
    //
}
