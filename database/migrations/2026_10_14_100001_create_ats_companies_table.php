<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ats_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 32);
            // Board token / account slug / company identifier on the ATS.
            $table->string('identifier');
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('last_job_count')->default(0);
            $table->timestampTz('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'identifier']);
            $table->index(['platform', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ats_companies');
    }
};
