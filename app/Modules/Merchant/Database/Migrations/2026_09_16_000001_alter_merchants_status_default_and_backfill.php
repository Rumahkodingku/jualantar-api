<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `merchants.status` now only carries the operational state. The former
     * registration states (draft/pending/rejected) are folded into `inactive`.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE merchant.merchants ALTER COLUMN status SET DEFAULT 'inactive'");

        DB::table('merchant.merchants')
            ->whereIn('status', ['draft', 'pending', 'rejected'])
            ->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE merchant.merchants ALTER COLUMN status SET DEFAULT 'draft'");
    }
};
