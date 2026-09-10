<?php

namespace App\Shared\Database\Concerns;

use Illuminate\Support\Facades\DB;

trait CreatesSchema
{
    /**
     * Ensure a PostgreSQL schema exists before a module migration writes to it.
     */
    protected function ensureSchema(string $schema): void
    {
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
    }
}
