<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What a person knows about a company, kept to show real interest in the cover
 * letter and the interview. Each fact has its own source; only approved facts
 * are ever used. Notes belong to the user and are shared by every job of that
 * company. The fact is personal, so it is encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Normalised name: "ACME, Inc." and "Acme Inc" are the same company.
            $table->string('company_key', 120);
            $table->string('company', 160);
            // Encrypted cast.
            $table->text('fact');
            $table->string('source_url', 2048)->nullable();
            // manual | web
            $table->string('origin', 8);
            // Suggestions found on the web wait for the person's approval.
            $table->boolean('approved')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'company_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_notes');
    }
};
