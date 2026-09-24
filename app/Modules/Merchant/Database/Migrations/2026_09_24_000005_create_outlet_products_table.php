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

        Schema::create('merchant.outlet_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('outlet_id')
                ->constrained('merchant.merchant_outlets')
                ->restrictOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('merchant.products')
                ->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->string('availability_status', 20)->default('available');
            $table->string('unavailable_reason', 255)->nullable();
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'outlet_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX outlet_products_outlet_product_unique
             ON merchant.outlet_products (outlet_id, product_id)
             WHERE deleted_at IS NULL'
        );

        DB::statement(
            "ALTER TABLE merchant.outlet_products
             ADD CONSTRAINT outlet_products_unavailable_reason_check
             CHECK (availability_status = 'unavailable' OR unavailable_reason IS NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.outlet_products');
    }
};
