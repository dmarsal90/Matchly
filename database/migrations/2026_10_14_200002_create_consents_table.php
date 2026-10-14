<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * GDPR consent ledger: one row per grant, closed by revoked_at. History is
 * kept as proof of consent; the current state is the open row per purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('policy_version', 20);
            $table->timestampTz('granted_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
