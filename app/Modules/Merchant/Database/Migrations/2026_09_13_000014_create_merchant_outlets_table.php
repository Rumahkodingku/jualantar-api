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

        Schema::create('merchant.merchant_outlets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('address');
            // Cross-module references to geography: no physical FK.
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('regency_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('village_id');
            $table->string('postal_code', 10);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('service_area_type', 20);
            $table->decimal('service_radius_km', 5, 2)->nullable();
            $table->jsonb('operating_hours')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('village_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_outlets');
    }
};
