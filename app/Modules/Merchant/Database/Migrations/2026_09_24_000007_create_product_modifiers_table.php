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

        Schema::create('merchant.product_modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('modifier_group_id')
                ->constrained('merchant.product_modifier_groups')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['modifier_group_id', 'display_order']);
            $table->index(['merchant_id', 'modifier_group_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX product_modifiers_group_name_unique
             ON merchant.product_modifiers (modifier_group_id, lower(name))
             WHERE deleted_at IS NULL'
        );

        DB::statement(
            'ALTER TABLE merchant.product_modifiers
             ADD CONSTRAINT product_modifiers_price_check
             CHECK (price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.product_modifiers');
    }
};
