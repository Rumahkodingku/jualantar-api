<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 150);
            $table->enum('category', [
                'persero',
                'private',
                'regional',
                'foreign',
            ]);
            $table->text('address')->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
