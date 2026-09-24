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
        $this->ensureSchema('merchant');

        Schema::create('merchant.products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('category_id')
                ->constrained('merchant.catalog_categories')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('product_type', 20);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('status', 20)->default('inactive');
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'category_id']);
            $table->index(['merchant_id', 'product_type']);
        });

        DB::statement(
            "ALTER TABLE merchant.products
             ADD CONSTRAINT products_type_price_check
             CHECK ((product_type = 'simple' AND price IS NOT NULL)
                 OR (product_type = 'variable' AND price IS NULL))"
        );

        DB::statement(
            'ALTER TABLE merchant.products
             ADD CONSTRAINT products_price_non_negative_check
             CHECK (price IS NULL OR price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.products');
    }
};
