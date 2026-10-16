<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the person wrote when practising an interview question, and the
 * feedback on it. Personal and free text, so both are encrypted. Keyed by a
 * hash of the question: regenerating the pack keeps the answers to questions
 * that come back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->char('question_key', 40);
            $table->text('answer');
            // Encrypted cast: strengths and improvements.
            $table->text('feedback')->nullable();
            // ai | rules
            $table->string('feedback_source', 8)->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'question_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_answers');
    }
};
