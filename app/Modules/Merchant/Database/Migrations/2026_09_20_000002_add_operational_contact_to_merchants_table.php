<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedicated operational contact owned by the Merchant domain. These are
     * distinct from the owner identity held by IdentityAccess.
     */
    public function up(): void
    {
        Schema::table('merchant.merchants', function (Blueprint $table) {
            $table->string('operational_phone', 20)->nullable()->after('description');
            $table->string('operational_email', 100)->nullable()->after('operational_phone');
            $table->string('website', 255)->nullable()->after('operational_email');
        });
    }

    public function down(): void
    {
        Schema::table('merchant.merchants', function (Blueprint $table) {
            $table->dropColumn(['operational_phone', 'operational_email', 'website']);
        });
    }
};
