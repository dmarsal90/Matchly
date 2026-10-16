<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Companies a person wants to hear about when a new job from them shows up. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followed_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Normalised (CompanyKey) so "ACME, Inc." and "Acme" are one company.
            $table->string('company_key', 120);
            $table->string('company', 160);
            // Listings first seen after this are the news for this follow.
            $table->timestampTz('last_checked_at');
            $table->timestampTz('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'company_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followed_companies');
    }
};
