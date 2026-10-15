<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A named saved search the user wants to hear about.
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // The listing query string it was saved from (see JobFiltersParser).
            $table->jsonb('filters');
            $table->string('frequency', 12);
            $table->boolean('email')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'frequency']);
        });

        // One row per alert and job: a job is never notified twice for the same alert.
        Schema::create('alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('matched_at');
            // Null while waiting for the daily/weekly digest.
            $table->timestamp('notified_at')->nullable();

            $table->unique(['saved_search_id', 'job_listing_id']);
            $table->index(['saved_search_id', 'notified_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('notification_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_deliveries');
        Schema::dropIfExists('saved_searches');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
