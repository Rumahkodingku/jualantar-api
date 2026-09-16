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

        Schema::create('merchant.merchant_approval_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_id')
                ->constrained('merchant.merchant_approvals')
                ->cascadeOnDelete();
            $table->string('event_type', 50);
            // Cross-module reference to identity_access.users: no physical FK.
            $table->uuid('actor_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['approval_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_approval_events');
    }
};
