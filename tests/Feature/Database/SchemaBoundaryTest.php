<?php

use Illuminate\Support\Facades\DB;

/**
 * Enforces docs/ARCHITECTURE.md Bab 6.2: foreign key constraints must stay
 * within a module. Each module owns one PostgreSQL schema, so any FK whose
 * source and target schemas differ is a cross-module FK and therefore illegal.
 */
it('keeps every foreign key within a single module schema', function () {
    $rows = DB::select(<<<'SQL'
        SELECT
            con.conname AS constraint_name,
            src_ns.nspname AS source_schema,
            src.relname AS source_table,
            tgt_ns.nspname AS target_schema,
            tgt.relname AS target_table
        FROM pg_constraint con
        JOIN pg_class src ON src.oid = con.conrelid
        JOIN pg_namespace src_ns ON src_ns.oid = src.relnamespace
        JOIN pg_class tgt ON tgt.oid = con.confrelid
        JOIN pg_namespace tgt_ns ON tgt_ns.oid = tgt.relnamespace
        WHERE con.contype = 'f'
          AND src_ns.nspname NOT IN ('pg_catalog', 'information_schema')
          AND tgt_ns.nspname NOT IN ('pg_catalog', 'information_schema')
        ORDER BY src_ns.nspname, src.relname
        SQL);

    $violations = [];

    foreach ($rows as $row) {
        if ($row->source_schema !== $row->target_schema) {
            $violations[] = "{$row->source_schema}.{$row->source_table}.{$row->constraint_name} -> {$row->target_schema}.{$row->target_table}";
        }
    }

    expect($violations)->toBe([]);
});
