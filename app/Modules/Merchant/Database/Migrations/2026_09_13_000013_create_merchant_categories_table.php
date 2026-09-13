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

        Schema::create('merchant.merchant_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            // Cross-module reference to service.categories: no physical FK.
            $table->uuid('category_id');
            $table->timestamps();

            $table->unique(['merchant_id', 'category_id']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_categories');
    }
};
