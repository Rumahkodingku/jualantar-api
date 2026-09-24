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

        Schema::create('merchant.product_modifier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->restrictOnDelete();
            $table->foreignUuid('product_id')
                ->constrained('merchant.products')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('selection_type', 20);
            $table->integer('min_selection');
            $table->integer('max_selection')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('status', 20)->default('inactive');
            $table->integer('display_order');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'display_order']);
            $table->index(['merchant_id', 'product_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX product_modifier_groups_product_name_unique
             ON merchant.product_modifier_groups (product_id, lower(name))
             WHERE deleted_at IS NULL'
        );

        DB::statement(
            'ALTER TABLE merchant.product_modifier_groups
             ADD CONSTRAINT product_modifier_groups_min_selection_check
             CHECK (min_selection >= 0)'
        );

        DB::statement(
            'ALTER TABLE merchant.product_modifier_groups
             ADD CONSTRAINT product_modifier_groups_max_selection_check
             CHECK (max_selection IS NULL OR max_selection >= GREATEST(min_selection, 1))'
        );

        DB::statement(
            "ALTER TABLE merchant.product_modifier_groups
             ADD CONSTRAINT product_modifier_groups_single_max_check
             CHECK (selection_type <> 'single' OR max_selection = 1)"
        );

        DB::statement(
            'ALTER TABLE merchant.product_modifier_groups
             ADD CONSTRAINT product_modifier_groups_required_check
             CHECK (is_required = (min_selection >= 1))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.product_modifier_groups');
    }
};
