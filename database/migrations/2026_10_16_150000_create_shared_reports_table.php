<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Read-only copies of a person's insights report that they chose to share by
 * link. The link holds a random token (only its hash is stored); the report is
 * a snapshot, encrypted, that stops being served on expiry or revocation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            // Encrypted cast: the rendered report.
            $table->text('html');
            $table->timestampTz('expires_at');
            $table->timestampTz('last_viewed_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_reports');
    }
};
