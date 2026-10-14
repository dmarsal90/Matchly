<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 32);
            // The stored file is encrypted by the application; path holds no personal data.
            $table->string('path');
            $table->text('original_name');
            $table->string('mime_type', 120);
            $table->unsignedInteger('size');
            $table->char('checksum', 64);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 16)->default('uploaded');
            $table->longText('extracted_text')->nullable();
            // Encrypted JSON (ciphertext is not valid jsonb).
            $table->longText('parsed_data')->nullable();
            $table->timestampTz('extracted_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_documents');
    }
};
