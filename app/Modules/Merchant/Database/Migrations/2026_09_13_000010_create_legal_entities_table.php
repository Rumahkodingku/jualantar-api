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

        Schema::create('merchant.legal_entities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 20);
            $table->string('name', 150);
            $table->string('nib', 100)->unique();
            $table->string('npwp', 25)->unique();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('regency_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('village_id')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();

            $table->index('province_id');
            $table->index('regency_id');
            $table->index('district_id');
            $table->index('village_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.legal_entities');
    }
};
