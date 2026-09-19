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
        $this->ensureSchema('communications');

        Schema::create('communications.communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20);
            $table->string('type', 100);
            $table->text('recipient_address');
            $table->string('subject', 255)->nullable();
            $table->string('template', 150)->nullable();
            // Encrypted at rest (Laravel encrypted:array cast); stored as text
            // because ciphertext cannot be represented as JSONB.
            $table->text('payload')->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['channel', 'status']);
            $table->index(['type', 'created_at']);
        });

        // Global idempotency: a key may be reused across different logical
        // communications only if the producer supplies a different key. Partial
        // index skips NULL keys so keyless sends are always allowed.
        DB::statement(
            'CREATE UNIQUE INDEX communications_idempotency_key_unique
             ON communications.communications (idempotency_key)
             WHERE idempotency_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('communications.communications');
    }
};
