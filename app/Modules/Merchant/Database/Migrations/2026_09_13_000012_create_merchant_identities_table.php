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

        Schema::create('merchant.merchant_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->unique()
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->string('id_type', 20);
            $table->string('id_number', 50);
            $table->string('full_name', 100);
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_identities');
    }
};
