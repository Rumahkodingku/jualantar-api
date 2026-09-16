<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval state now lives on merchant_applications/merchant_approvals, so the
 * legacy review/rejection columns are removed from the merchant itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant.merchants', function (Blueprint $table) {
            $table->dropColumn([
                'rejection_stage',
                'rejection_reason',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('merchant.merchants', function (Blueprint $table) {
            $table->string('rejection_stage', 50)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
        });
    }
};
