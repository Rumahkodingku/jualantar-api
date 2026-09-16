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

        Schema::create('merchant.merchant_approval_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('approval_id')
                ->constrained('merchant.merchant_approvals')
                ->cascadeOnDelete();
            $table->string('component', 30);
            $table->string('subject_type', 100);
            $table->uuid('subject_id');
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            // Cross-module reference to identity_access.users: no physical FK.
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignUuid('snapshot_id')
                ->nullable()
                ->constrained('merchant.merchant_application_snapshots')
                ->nullOnDelete();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['approval_id', 'component', 'subject_type', 'subject_id'],
                'merchant_approval_reviews_subject_unique',
            );
            $table->index(['approval_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_approval_reviews');
    }
};
