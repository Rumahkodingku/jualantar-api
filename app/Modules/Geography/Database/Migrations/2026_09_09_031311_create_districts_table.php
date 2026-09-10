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

        Schema::create('geography.districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regency_id')
                ->constrained('geography.regencies')
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
        Schema::dropIfExists('geography.districts');
    }
};
