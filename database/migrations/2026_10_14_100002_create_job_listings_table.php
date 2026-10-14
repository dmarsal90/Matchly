<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * "job_listings" rather than "jobs": that table belongs to Laravel's queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        if ($pgsql) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        }

        Schema::create('job_listings', function (Blueprint $table) use ($pgsql): void {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('external_id');
            $table->foreignId('duplicate_of_id')->nullable()->constrained('job_listings')->nullOnDelete();

            $table->string('title', 500);
            $table->string('company');
            $table->string('company_logo_url', 1000)->nullable();
            $table->longText('description');
            $table->string('apply_url', 2000);
            $table->string('source_url', 2000)->nullable();
            $table->string('canonical_url', 2000);
            $table->char('fingerprint', 32);
            $table->char('content_hash', 64);

            $table->string('category', 40)->nullable();
            $table->string('role', 40)->nullable();
            $table->string('work_mode', 16);
            $table->string('remote_scope', 24)->nullable();
            $table->jsonb('allowed_regions')->default('[]');
            $table->string('location', 500)->nullable();
            $table->string('employment_type', 24)->nullable();
            $table->string('seniority', 16)->nullable();

            $table->decimal('salary_min', 14, 2)->nullable();
            $table->decimal('salary_max', 14, 2)->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('salary_period', 8)->nullable();
            // Annualised figures make salary filters comparable across periods.
            $table->decimal('salary_annual_min', 14, 2)->nullable();
            $table->decimal('salary_annual_max', 14, 2)->nullable();
            $table->string('salary_text')->nullable();

            $table->jsonb('tags')->default('[]');
            $table->jsonb('requirements')->nullable();
            $table->timestampTz('requirements_extracted_at')->nullable();

            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            // Listing order: publication date, or first sighting when the source gives none.
            $table->timestampTz('sort_at')->storedAs('COALESCE(posted_at, first_seen_at)');

            $table->string('status', 16)->default('open');
            $table->string('close_reason', 32)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->jsonb('flag_reasons')->nullable();
            $table->timestampTz('link_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'external_id']);
            $table->index(['status', 'sort_at', 'id']);
            $table->index('fingerprint');
            $table->index(['status', 'link_checked_at']);
            $table->index(['category', 'status']);
            $table->index(['role', 'status']);

            if (! $pgsql) {
                $table->index('canonical_url');
            }
        });

        if ($pgsql) {
            // Hash index: canonical URLs can exceed the btree row size limit.
            DB::statement('CREATE INDEX job_listings_canonical_url_index ON job_listings USING hash (canonical_url)');
            DB::statement('CREATE INDEX job_listings_tags_gin ON job_listings USING gin (tags jsonb_path_ops)');
            DB::statement('CREATE INDEX job_listings_allowed_regions_gin ON job_listings USING gin (allowed_regions jsonb_path_ops)');
            DB::statement('CREATE INDEX job_listings_open_sort_index ON job_listings (sort_at DESC, id DESC) WHERE status = \'open\' AND duplicate_of_id IS NULL');

            // 'simple' configuration: listings are multilingual, stemming for one language would hurt the others.
            DB::statement(<<<'SQL'
                ALTER TABLE job_listings ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                    setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(company, '')), 'B') ||
                    setweight(to_tsvector('simple', coalesce(tags::text, '')), 'B') ||
                    setweight(to_tsvector('simple', left(regexp_replace(coalesce(description, ''), '<[^>]+>', ' ', 'g'), 20000)), 'D')
                ) STORED
            SQL);
            DB::statement('CREATE INDEX job_listings_search_vector_gin ON job_listings USING gin (search_vector)');
            DB::statement('CREATE INDEX job_listings_title_trgm ON job_listings USING gin (title gin_trgm_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
