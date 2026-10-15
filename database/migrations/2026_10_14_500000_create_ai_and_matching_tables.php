<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The user's own Anthropic key, encrypted by the model cast.
        Schema::table('users', function (Blueprint $table): void {
            $table->text('anthropic_api_key')->nullable();
        });

        // One row per AI call (successful or not): tokens, cost and latency.
        Schema::create('ai_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task', 40);
            $table->string('provider', 24);
            $table->string('model', 100);
            // Whose key paid for it: "server" counts against budgets, "user" does not.
            $table->string('key_owner', 8)->default('server');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->boolean('success');
            $table->string('error')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['key_owner', 'created_at']);
        });

        Schema::create('job_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_listing_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('level', 12);
            $table->boolean('eligible');
            $table->jsonb('result');
            $table->timestamp('computed_at');

            $table->unique(['user_id', 'job_listing_id']);
            $table->index(['user_id', 'eligible', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_matches');
        Schema::dropIfExists('ai_usages');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('anthropic_api_key');
        });
    }
};
