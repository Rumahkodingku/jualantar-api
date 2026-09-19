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
        $this->ensureSchema('notifications');

        Schema::create('notifications.notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Logical reference to an IdentityAccess user: no physical FK.
            $table->uuid('recipient_id');
            $table->string('type', 100);
            $table->string('title', 255);
            $table->text('body');
            $table->text('action_url')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->jsonb('data')->nullable();
            $table->string('deduplication_key', 255)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'created_at']);
            $table->index(['recipient_id', 'read_at', 'created_at']);
            $table->index(['recipient_id', 'type', 'created_at']);
            $table->index('expires_at');
        });

        // Recipient-scoped idempotency. A producer may reuse the same key for
        // different recipients, so uniqueness is scoped and only enforced when
        // a key is supplied (PostgreSQL partial index skips NULL keys).
        DB::statement(
            'CREATE UNIQUE INDEX notifications_recipient_dedup_unique
             ON notifications.notifications (recipient_id, deduplication_key)
             WHERE deduplication_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications.notifications');
    }
};
