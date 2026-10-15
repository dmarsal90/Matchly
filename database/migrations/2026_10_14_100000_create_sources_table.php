<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('homepage_url');
            $table->string('terms_url')->nullable();
            $table->boolean('requires_attribution')->default(true);
            // Admin kill switch, on top of the per-source flag in config/jobs.php.
            $table->boolean('enabled')->default(true);
            $table->timestampTz('last_fetched_at')->nullable();
            $table->timestampTz('last_succeeded_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
