<?php

use App\Shared\Database\Concerns\CreatesSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-outlet overrides of a master variant's active status.
 *
 * A row is the deviation from the master status, not a copy of it: the master
 * status stays the ceiling and the absence of a row means the item simply
 * follows the master. Only an `inactive` row is ever written, so an outlet
 * manager can hide a variant from their outlet but can never promote one the
 * owner turned off.
 *
 * The physical foreign keys stay `restrict` on purpose, like
 * `merchant.outlet_products`: every delete in this module is a soft delete, so
 * the cleanup of a master's overrides is done by the master delete actions.
 */
return new class extends Migration
{
    use CreatesSchema;

    public function up(): void
    {
        $this->ensureSchema('merchant');

        Schema::create('merchant.outlet_product_variants', function (Blueprint $table) {
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
            $table->foreignUuid('product_variant_id')
                ->constrained('merchant.product_variants')
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
            'CREATE UNIQUE INDEX outlet_product_variants_outlet_variant_unique
             ON merchant.outlet_product_variants (outlet_id, product_variant_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.outlet_product_variants');
    }
};
