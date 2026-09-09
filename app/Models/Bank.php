<?php

namespace App\Models;

use Database\Factories\BankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'category', 'address', 'phone', 'website', 'is_active'])]
class Bank extends Model
{
    /** @use HasFactory<BankFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'persero',
        'private',
        'regional',
        'foreign',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
