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
        $this->ensureSchema('service');

        Schema::create('service.categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')
                ->constrained('service.services')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description');
            $table->string('icon', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'slug']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service.categories');
    }
};
