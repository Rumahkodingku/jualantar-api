<?php

use App\Shared\Database\Concerns\CreatesSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CreatesSchema;

    public function up(): void
    {
        $this->ensureSchema('merchant');

        Schema::create('merchant.merchant_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                ->unique()
                ->constrained('merchant.merchant_applications')
                ->cascadeOnDelete();
            // Cross-module reference to identity_access.users: no physical FK.
            $table->uuid('assigned_to')->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('decision', 20)->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_approvals');
    }
};
