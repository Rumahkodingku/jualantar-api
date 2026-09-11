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

        Schema::create('geography.regencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')
                ->constrained('geography.provinces')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('code', 13)->unique();
            $table->string('name', 100);
            $table->enum('type', [
                'regency',
                'city',
            ]);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('province_id');
            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geography.regencies');
    }
};
