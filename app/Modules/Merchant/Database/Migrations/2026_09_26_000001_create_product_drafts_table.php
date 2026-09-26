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

        Schema::create('merchant.product_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedSmallInteger('step_index')->default(0);
            $table->jsonb('payload');
            $table->timestampTz('expires_at');
            $table->timestamps();

            // The wizard keeps a single resumable draft, so a merchant can
            // never accumulate more than one.
            $table->unique('merchant_id');

            // Expired drafts are purged lazily when a merchant reads or saves.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.product_drafts');
    }
};
