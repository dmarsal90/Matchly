<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A prepared application for one job: letter, tailored CV and contact data. Kept as history.
        Schema::create('application_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('language', 8);
            $table->string('tone', 16);
            $table->string('length', 8);
            // Personal data, encrypted by the model casts.
            $table->text('cover_letter')->nullable();
            $table->text('resume')->nullable();
            $table->text('master_resume')->nullable();
            $table->string('letter_source', 12)->nullable();
            $table->string('resume_source', 12)->nullable();
            $table->jsonb('warnings')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_packages');
    }
};
