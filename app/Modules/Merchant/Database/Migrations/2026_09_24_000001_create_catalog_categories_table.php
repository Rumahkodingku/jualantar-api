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

        Schema::create('merchant.catalog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'status']);
            $table->index(['merchant_id', 'display_order']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX catalog_categories_merchant_name_unique
             ON merchant.catalog_categories (merchant_id, lower(name))
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.catalog_categories');
    }
};
