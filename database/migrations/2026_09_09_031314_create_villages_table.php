<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')
                ->constrained('districts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 13)->unique();
            $table->string('name', 100);
            $table->enum('type', [
                'village',
                'urban_village',
            ]);
            $table->timestamps();
            $table->index('district_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
