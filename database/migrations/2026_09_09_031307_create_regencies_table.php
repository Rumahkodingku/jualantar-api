<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')
                ->constrained('provinces')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 13)->unique();
            $table->string('name', 100);
            $table->enum('type', [
                'regency',
                'city',
            ]);
            $table->timestamps();
            $table->index('province_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regencies');
    }
};
