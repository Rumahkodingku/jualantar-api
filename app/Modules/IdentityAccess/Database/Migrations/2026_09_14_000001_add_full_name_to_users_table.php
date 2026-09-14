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
        $this->ensureSchema('identity_access');

        Schema::table('identity_access.users', function (Blueprint $table) {
            $table->string('full_name', 150)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('identity_access.users', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
