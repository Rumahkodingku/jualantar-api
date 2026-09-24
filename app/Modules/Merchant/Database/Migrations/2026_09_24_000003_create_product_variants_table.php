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

        Schema::create('merchant.product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('merchant.products')
                ->restrictOnDelete();
            $table->string('sku', 50)->nullable();
            $table->string('name', 100);
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'product_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX product_variants_merchant_sku_unique
             ON merchant.product_variants (merchant_id, lower(sku))
             WHERE sku IS NOT NULL AND deleted_at IS NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX product_variants_default_unique
             ON merchant.product_variants (product_id)
             WHERE is_default = true AND deleted_at IS NULL'
        );

        DB::statement(
            'ALTER TABLE merchant.product_variants
             ADD CONSTRAINT product_variants_price_non_negative_check
             CHECK (price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.product_variants');
    }
};
