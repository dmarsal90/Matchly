<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Infrastructure\Persistence;

use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Data\JobData;
use App\Modules\Jobs\Domain\Data\UpsertResult;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Services\CanonicalUrl;
use App\Modules\Jobs\Domain\Services\JobFingerprint;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentJobRepository implements JobRepository
{
    public function __construct(private ConnectionInterface $db) {}

    public function upsert(string $source, array $jobs, DateTimeImmutable $seenAt): UpsertResult
    {
        if ($jobs === []) {
            return new UpsertResult;
        }

        // Paginated sources can list the same posting twice in one run: last copy wins.
        $jobs = array_values(array_column(array_map(fn (JobData $job): array => ['id' => $job->externalId, 'job' => $job], $jobs), 'job', 'id'));

        $sourceId = $this->sourceId($source);
        $seenAt = CarbonImmutable::instance($seenAt);

        /** @var array<string, JobListing> $existing */
        $existing = JobListing::query()
            ->where('source_id', $sourceId)
            ->whereIn('external_id', array_map(fn (JobData $job): string => $job->externalId, $jobs))
            ->get()
            ->keyBy('external_id')
            ->all();

        return $this->db->transaction(function () use ($jobs, $existing, $sourceId, $seenAt): UpsertResult {
            $created = [];
            $changed = [];
            $unchanged = 0;
            $duplicates = 0;
            $reopened = 0;

            foreach ($jobs as $job) {
                $record = $existing[$job->externalId] ?? null;

                if ($record === null) {
                    $record = $this->create($sourceId, $job, $seenAt);
                    $existing[$job->externalId] = $record;

                    if ($record->duplicate_of_id !== null) {
                        $duplicates++;
                    } elseif ($record->status === JobStatus::Open) {
                        $created[] = $record->id;
                    }

                    continue;
                }

                $wasClosed = $record->status === JobStatus::Closed && $record->close_reason === CloseReason::RemovedFromSource;
                $contentChanged = $record->content_hash !== $job->contentHash();

                $record->fill($this->attributes($job));
                $record->last_seen_at = $seenAt;

                if ($contentChanged) {
                    // Derived data belongs to the old text.
                    $record->requirements = null;
                    $record->requirements_extracted_at = null;
                }

                if ($wasClosed) {
                    $record->status = JobStatus::Open;
                    $record->close_reason = null;
                    $record->closed_at = null;
                    $reopened++;
                }

                $record->save();

                if ($contentChanged || $wasClosed) {
                    $changed[] = $record->id;
                } else {
                    $unchanged++;
                }
            }

            return new UpsertResult($created, $changed, $unchanged, $duplicates, $reopened);
        });
    }

    public function closeNotSeenSince(string $source, DateTimeImmutable $since, CloseReason $reason): int
    {
        return $this->closeWhere(
            JobListing::query()
                ->where('source_id', $this->sourceId($source))
                ->where('last_seen_at', '<', $since),
            $reason,
        );
    }

    public function closeOlderThan(DateTimeImmutable $cutoff): int
    {
        return $this->closeWhere(JobListing::query()->where('sort_at', '<', $cutoff), CloseReason::Expired);
    }

    public function close(int $jobId, CloseReason $reason): void
    {
        $this->closeWhere(JobListing::query()->whereKey($jobId), $reason);
    }

    private function create(int $sourceId, JobData $job, CarbonImmutable $seenAt): JobListing
    {
        $record = new JobListing;
        $record->fill([
            ...$this->attributes($job),
            'source_id' => $sourceId,
            'external_id' => $job->externalId,
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'status' => $job->flagReasons === [] ? JobStatus::Open : JobStatus::Flagged,
        ]);
        $record->duplicate_of_id = $this->findOriginal($sourceId, $record->canonical_url, $record->fingerprint);
        $record->save();

        return $record;
    }

    /**
     * The same posting already published by another source (same canonical
     * apply URL, or same normalized title + company). The first one seen wins.
     */
    private function findOriginal(int $sourceId, string $canonicalUrl, string $fingerprint): ?int
    {
        /** @var int|null $id */
        $id = JobListing::query()
            ->where('source_id', '!=', $sourceId)
            ->whereNull('duplicate_of_id')
            ->where('status', JobStatus::Open)
            ->where(fn (Builder $query) => $query->where('canonical_url', $canonicalUrl)->orWhere('fingerprint', $fingerprint))
            ->orderBy('first_seen_at')
            ->value('id');

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(JobData $job): array
    {
        return [
            'title' => mb_substr($job->title, 0, 500),
            'company' => mb_substr($job->company, 0, 255),
            'company_logo_url' => $job->companyLogoUrl !== null && mb_strlen($job->companyLogoUrl) <= 1000 ? $job->companyLogoUrl : null,
            'description' => $job->description,
            'apply_url' => $job->applyUrl,
            'source_url' => $job->sourceUrl,
            'canonical_url' => CanonicalUrl::from($job->applyUrl),
            'fingerprint' => JobFingerprint::make($job->title, $job->company),
            'content_hash' => $job->contentHash(),
            'category' => $job->category,
            'role' => $job->role,
            'work_mode' => $job->workMode,
            'remote_scope' => $job->remoteScope,
            'allowed_regions' => $job->allowedRegions,
            'location' => $job->location !== null ? mb_substr($job->location, 0, 500) : null,
            'employment_type' => $job->employmentType,
            'seniority' => $job->seniority,
            'salary_min' => $job->salary?->min,
            'salary_max' => $job->salary?->max,
            'salary_currency' => $job->salary?->currency,
            'salary_period' => $job->salary?->period,
            'salary_annual_min' => $job->salary?->annualMin(),
            'salary_annual_max' => $job->salary?->annualMax(),
            'salary_text' => $job->salaryText !== null ? mb_substr($job->salaryText, 0, 255) : null,
            'tags' => $job->tags,
            // A future date is a source bug; clamp it so the job does not stick to the top.
            'posted_at' => $job->postedAt !== null ? CarbonImmutable::instance($job->postedAt)->min(CarbonImmutable::now()) : null,
            'flag_reasons' => $job->flagReasons === [] ? null : $job->flagReasons,
        ];
    }

    /**
     * @param  Builder<JobListing>  $query
     */
    private function closeWhere(Builder $query, CloseReason $reason): int
    {
        return $query->where('status', JobStatus::Open)->update([
            'status' => JobStatus::Closed->value,
            'close_reason' => $reason->value,
            'closed_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    private function sourceId(string $key): int
    {
        /** @var int $id */
        $id = Source::query()->where('key', $key)->valueOrFail('id');

        return $id;
    }
}
