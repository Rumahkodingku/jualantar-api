<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regency_id')
                ->constrained('regencies')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 13)->unique();
            $table->string('name', 100);
            $table->timestamps();
            $table->index('regency_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
