<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Professional profile. Deliberately no age, gender, nationality or photo:
 * matching must never use them. `country` is where the person works from,
 * needed for remote eligibility, not their nationality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('phone')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('github_username', 39)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->jsonb('languages')->default('[]');
            $table->unsignedInteger('salary_min')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('salary_period', 8)->nullable();
            $table->jsonb('preferences')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
