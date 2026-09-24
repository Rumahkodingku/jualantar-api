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

        Schema::create('merchant.product_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('merchant.products')
                ->restrictOnDelete();
            $table->text('storage_key');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->string('alt_text', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'product_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX product_media_primary_unique
             ON merchant.product_media (product_id)
             WHERE is_primary = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.product_media');
    }
};
