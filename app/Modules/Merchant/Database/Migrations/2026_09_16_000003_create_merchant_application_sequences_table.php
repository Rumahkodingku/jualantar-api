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

        Schema::create('merchant.merchant_application_sequences', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->integer('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_application_sequences');
    }
};
