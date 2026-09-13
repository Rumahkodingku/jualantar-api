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

        Schema::create('merchant.merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->unique();
            $table->uuid('legal_entity_id')->nullable();
            // Registration creates an empty draft first; service, business_name,
            // slug and type are filled in during the wizard and validated at submit.
            $table->uuid('service_id')->nullable();
            $table->string('business_name', 100)->nullable();
            $table->string('slug', 100)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('type', 20)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('rejection_stage', 50)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamps();

            $table->foreign('legal_entity_id')
                ->references('id')
                ->on('merchant.legal_entities')
                ->nullOnDelete();

            $table->index('service_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchants');
    }
};
