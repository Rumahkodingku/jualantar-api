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

        Schema::create('merchant.merchant_outlet_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->foreignUuid('outlet_id')
                ->constrained('merchant.merchant_outlets')
                ->cascadeOnDelete();
            // Cross-module reference to IdentityAccess: no physical FK.
            $table->uuid('user_id');
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['outlet_id', 'user_id']);
            $table->index(['merchant_id', 'user_id']);
            $table->index(['merchant_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_outlet_users');
    }
};
