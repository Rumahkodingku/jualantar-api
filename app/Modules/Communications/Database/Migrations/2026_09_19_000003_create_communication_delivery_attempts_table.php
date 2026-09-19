<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications.communication_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('communication_id');
            $table->unsignedInteger('attempt_number');
            $table->string('status', 30);
            $table->string('provider', 100);
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            // Same-module foreign key is permitted (both tables share the schema).
            // Names are explicit because schema-qualified auto names exceed
            // PostgreSQL's 63-character identifier limit and would collide.
            $table->foreign('communication_id', 'comm_delivery_attempts_communication_fk')
                ->references('id')
                ->on('communications.communications')
                ->cascadeOnDelete();

            $table->unique(
                ['communication_id', 'attempt_number'],
                'comm_delivery_attempts_communication_number_unique',
            );
            $table->index(
                ['communication_id', 'created_at'],
                'comm_delivery_attempts_communication_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications.communication_delivery_attempts');
    }
};
