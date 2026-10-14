<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Questionnaire answers live next to the rest of the career profile.
        Schema::table('profiles', function (Blueprint $table): void {
            $table->jsonb('target_roles')->default('[]');
            $table->string('seniority', 16)->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->jsonb('regions')->default('[]');
            $table->smallInteger('utc_offset_min')->nullable();
            $table->smallInteger('utc_offset_max')->nullable();
            $table->jsonb('dealbreakers')->default('[]');
            $table->text('dealbreaker_notes')->nullable();
            $table->jsonb('search_profile')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        // One row per skill and source: the merged profile is computed from these.
        Schema::create('user_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('skill', 64);
            $table->string('name', 80);
            $table->unsignedTinyInteger('level')->nullable();
            $table->decimal('years', 4, 1)->nullable();
            $table->unsignedSmallInteger('last_used_year')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source', 'skill']);
        });

        // Data read from a source, waiting for the user's review (and kept once confirmed).
        Schema::create('profile_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('status', 16);
            $table->string('reference', 100)->nullable();
            $table->text('payload')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_imports');
        Schema::dropIfExists('user_skills');

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'target_roles', 'seniority', 'years_experience', 'regions', 'utc_offset_min', 'utc_offset_max',
                'dealbreakers', 'dealbreaker_notes', 'search_profile', 'onboarding_completed_at',
            ]);
        });
    }
};
