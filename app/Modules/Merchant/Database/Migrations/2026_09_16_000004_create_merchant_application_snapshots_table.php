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

        Schema::create('merchant.merchant_application_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                ->constrained('merchant.merchant_applications')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->unique(['application_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_application_snapshots');
    }
};
