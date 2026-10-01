<?php

use App\Shared\Database\Concerns\CreatesSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-outlet overrides of a master modifier group's active status.
 *
 * Same deviation-not-copy contract as `merchant.outlet_product_variants`: the
 * master status is the ceiling and only an `inactive` row is ever written.
 */
return new class extends Migration
{
    use CreatesSchema;

    public function up(): void
    {
        $this->ensureSchema('merchant');

        Schema::create('merchant.outlet_product_modifier_groups', function (Blueprint $table) {
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
            $table->foreignUuid('product_modifier_group_id')
                ->constrained('merchant.product_modifier_groups')
                ->restrictOnDelete();
            $table->string('status', 20)->default('inactive');
            // Cross-module reference to IdentityAccess: no physical FK.
            $table->uuid('deactivated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'outlet_id', 'product_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX outlet_product_modifier_groups_outlet_group_unique
             ON merchant.outlet_product_modifier_groups (outlet_id, product_modifier_group_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.outlet_product_modifier_groups');
    }
};
