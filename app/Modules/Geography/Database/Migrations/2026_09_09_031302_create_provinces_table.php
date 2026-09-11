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
        $this->ensureSchema('geography');

        Schema::create('geography.provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 13)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geography.provinces');
    }
};
