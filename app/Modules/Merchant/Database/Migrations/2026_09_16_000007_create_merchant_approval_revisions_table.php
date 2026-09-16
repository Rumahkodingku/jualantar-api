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

        Schema::create('merchant.merchant_approval_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_id')
                ->constrained('merchant.merchant_approvals')
                ->cascadeOnDelete();
            // Cross-module reference to identity_access.users: no physical FK.
            $table->uuid('requested_by');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestampTz('requested_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['approval_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_approval_revisions');
    }
};
