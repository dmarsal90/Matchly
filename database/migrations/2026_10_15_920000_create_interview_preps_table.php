<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The interview preparation pack of one application: questions with STAR
 * outlines built from the person's own CV, honest answers for skill gaps and
 * questions to ask the employer. The content is personal, so it is encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_preps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('language', 8);
            // ai | rules
            $table->string('source', 8)->nullable();
            // Encrypted cast: questions, gaps and questions to ask.
            $table->text('content')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_preps');
    }
};
