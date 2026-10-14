<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A user's pipeline for one job: saved (the "favourite" star) → prepared →
 * applied → interview → rejected. One row per user and job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->string('status', 16)->default('saved');
            // Encrypted cast: private notes about an application process.
            $table->text('notes')->nullable();
            $table->timestampTz('status_changed_at');
            $table->timestampTz('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'job_listing_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('job_views', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->timestampTz('viewed_at');

            $table->primary(['user_id', 'job_listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_views');
        Schema::dropIfExists('applications');
    }
};
