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

        Schema::create('merchant.merchant_approval_revision_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('revision_id')
                ->constrained('merchant.merchant_approval_revisions')
                ->cascadeOnDelete();
            $table->string('component', 30);
            $table->string('subject_type', 100);
            $table->uuid('subject_id');
            $table->text('reason');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index('revision_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_approval_revision_items');
    }
};
