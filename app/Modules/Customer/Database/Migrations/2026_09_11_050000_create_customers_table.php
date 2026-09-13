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
        $this->ensureSchema('customer');

        Schema::create('customer.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Cross-module reference to identity_access.users: no physical FK.
            $table->uuid('user_id')->unique();
            $table->string('username', 150)->unique();
            $table->string('full_name', 150);
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 30)->nullable();
            $table->text('profile_image')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer.customers');
    }
};
