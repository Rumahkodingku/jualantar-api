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
        $this->ensureSchema('merchant');

        Schema::create('merchant.merchant_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->string('application_number', 50)->unique();
            $table->string('status', 30)->default('draft');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
        });

        // A merchant may only have one application in the active lifecycle at
        // a time. Terminal applications (approved/rejected) are excluded so a
        // merchant can re-apply with a brand new application.
        DB::statement(
            "CREATE UNIQUE INDEX merchant_applications_active_unique
             ON merchant.merchant_applications (merchant_id)
             WHERE status IN ('draft', 'pending', 'in_review', 'revision_required')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_applications');
    }
};
