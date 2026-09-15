<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant.merchant_outlets', function (Blueprint $table) {
            $table->jsonb('photos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merchant.merchant_outlets', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
