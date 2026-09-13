<?php

use App\Shared\Database\Concerns\CreatesSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatesSchema;

    public function up(): void
    {
        $this->ensureSchema('payout');

        Schema::create('payout.payout_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type', 20);
            $table->uuid('owner_id');
            // Cross-module reference to bank_directory.banks: no physical FK.
            $table->unsignedBigInteger('bank_id');
            $table->string('account_number', 50);
            $table->string('account_name', 150);
            $table->boolean('is_primary')->default(true);
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('bank_id');
            $table->index('status');
        });

        // Enforce at most one primary payout account per owner at the database
        // level to protect against race conditions.
        DB::statement(
            'CREATE UNIQUE INDEX payout_accounts_owner_primary_unique
             ON payout.payout_accounts (owner_type, owner_id)
             WHERE is_primary = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payout.payout_accounts');
    }
};
