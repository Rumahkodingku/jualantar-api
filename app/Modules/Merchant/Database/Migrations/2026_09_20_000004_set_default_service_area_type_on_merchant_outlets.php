<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Operations can create an outlet without an explicit service area; radius
     * is the safe default until the merchant configures one.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE merchant.merchant_outlets ALTER COLUMN service_area_type SET DEFAULT 'radius'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE merchant.merchant_outlets ALTER COLUMN service_area_type DROP DEFAULT');
    }
};
