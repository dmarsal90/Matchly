<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The history of one application: status changes, interview rounds, notes and
 * follow-ups, each with the date it happened. Rows go with their application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            // The status after a status change; null for the other types.
            $table->string('status', 16)->nullable();
            // Interview round number (1 = first interview).
            $table->unsignedSmallInteger('round')->nullable();
            // Encrypted cast: what was said, who interviewed, how it felt.
            $table->text('note')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(['application_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
    }
};
