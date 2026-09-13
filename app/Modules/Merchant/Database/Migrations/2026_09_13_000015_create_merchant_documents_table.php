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

        Schema::create('merchant.merchant_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')
                ->constrained('merchant.merchants')
                ->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('file_name', 255);
            $table->string('object_key', 500);
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');
            $table->timestamps();

            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant.merchant_documents');
    }
};
