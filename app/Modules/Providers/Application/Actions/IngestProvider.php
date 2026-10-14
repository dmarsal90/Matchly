<?php

declare(strict_types=1);

namespace App\Modules\Providers\Application\Actions;

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Contracts\WorkModeClassifier;
use App\Modules\Jobs\Domain\Data\JobData;
use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Data\UpsertResult;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Services\SuspicionDetector;
use App\Modules\Providers\Application\ProviderRegistry;
use App\Modules\Providers\Domain\Contracts\IngestionLog;
use App\Modules\Providers\Domain\Contracts\JobNormalizer;
use App\Modules\Providers\Domain\Contracts\ReportsPartialFailures;
use App\Modules\Providers\Domain\Data\IngestionReport;
use App\Modules\Providers\Domain\Data\RawJob;
use App\Modules\Shared\Domain\Contracts\HtmlSanitizer;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Fetches one source and stores its jobs: normalize → freshness filter →
 * sanitize → classify → moderation flags → idempotent upsert in batches →
 * close postings the source no longer lists → JobsIngested.
 */
final readonly class IngestProvider
{
    private const int BATCH_SIZE = 100;

    private const int MAX_URL_LENGTH = 2000;

    public const string SKIP_INCOMPLETE = 'incomplete';

    public const string SKIP_INVALID_URL = 'invalid_url';

    public const string SKIP_STALE = 'stale';

    public const string SKIP_WORK_MODE = 'work_mode';

    public const string SKIP_NORMALIZER_ERROR = 'normalizer_error';

    public function __construct(
        private ProviderRegistry $registry,
        private WorkModeClassifier $classifier,
        private HtmlSanitizer $sanitizer,
        private SuspicionDetector $suspicion,
        private JobRepository $jobs,
        private IngestionLog $log,
        private Dispatcher $events,
        private Config $config,
        private LoggerInterface $logger,
        private PublicUrlGuard $urlGuard,
    ) {}

    public function handle(string $key): IngestionReport
    {
        $provider = $this->registry->provider($key);
        $normalizer = $this->registry->normalizer($key);
        $definition = $provider->definition();

        $startedAt = CarbonImmutable::now();
        $runId = $this->log->start($definition);

        try {
            $cutoff = $startedAt->subDays((int) $this->config->get('jobs.max_age_days'));
            /** @var list<string> $acceptedModes */
            $acceptedModes = (array) $this->config->get('jobs.accepted_work_modes', []);

            $fetched = 0;
            $skipReasons = [];
            $flagged = 0;
            $batch = [];
            $result = new UpsertResult;

            foreach ($provider->fetch() as $raw) {
                $fetched++;
                $job = $this->toJob($normalizer, $raw, $cutoff, $acceptedModes);

                if (is_string($job)) {
                    $skipReasons[$job] = ($skipReasons[$job] ?? 0) + 1;

                    continue;
                }

                if ($job->flagReasons !== []) {
                    $flagged++;
                }

                $batch[] = $job;
                if (count($batch) >= self::BATCH_SIZE) {
                    $result = $result->merge($this->jobs->upsert($key, $batch, $startedAt));
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $result = $result->merge($this->jobs->upsert($key, $batch, $startedAt));
            }

            $failures = $provider instanceof ReportsPartialFailures ? $provider->failures() : [];

            // Only a complete, non-empty fetch proves a posting is gone; a partial
            // one may just have failed to reach the company that lists it.
            $closed = $failures === [] && $fetched > 0
                ? $this->jobs->closeNotSeenSince($key, $startedAt, CloseReason::RemovedFromSource)
                : 0;

            $report = new IngestionReport(
                source: $key,
                fetched: $fetched,
                createdIds: $result->created,
                updated: count($result->changed),
                unchanged: $result->unchanged,
                skipped: array_sum($skipReasons),
                duplicates: $result->duplicates,
                flagged: $flagged,
                closed: $closed,
                failures: $failures,
                skipReasons: $skipReasons,
            );

            $this->log->finish($runId, $report);
        } catch (Throwable $exception) {
            $this->log->fail($runId, $exception);

            throw $exception;
        }

        if ($result->created !== [] || $result->changed !== []) {
            $this->events->dispatch(new JobsIngested($key, $result->created, $result->changed));
        }

        return $report;
    }

    /**
     * @param  list<string>  $acceptedModes
     * @return JobData|string the job, or the reason it was skipped
     */
    private function toJob(JobNormalizer $normalizer, RawJob $raw, CarbonImmutable $cutoff, array $acceptedModes): JobData|string
    {
        try {
            $draft = $normalizer->normalize($raw);
        } catch (Throwable $exception) {
            // One malformed posting must never sink the whole run.
            $this->logger->warning('Job normalization failed', [
                'source' => $raw->source,
                'external_id' => $raw->externalId,
                'error' => $exception->getMessage(),
            ]);

            return self::SKIP_NORMALIZER_ERROR;
        }

        if ($draft === null) {
            return self::SKIP_INCOMPLETE;
        }

        $problem = $this->problem($draft, $cutoff);
        if ($problem !== null) {
            return $problem;
        }

        $draft = $draft->withDescription($this->sanitizer->sanitize($draft->description));
        $classification = $this->classifier->classify($draft);

        if (! in_array($classification->workMode->value, $acceptedModes, true)) {
            return self::SKIP_WORK_MODE;
        }

        $job = JobData::fromDraft($draft, $classification);

        return $job->withFlagReasons($this->suspicion->reasons($job));
    }

    private function problem(JobDraft $draft, CarbonImmutable $cutoff): ?string
    {
        return match (true) {
            trim($draft->title) === '', trim($draft->company) === '' => self::SKIP_INCOMPLETE,
            filter_var($draft->applyUrl, FILTER_VALIDATE_URL) === false,
            preg_match('#^https?://#i', $draft->applyUrl) !== 1,
            mb_strlen($draft->applyUrl) > self::MAX_URL_LENGTH,
            // Feeds are untrusted: never store links to internal hosts (SSRF via the link checker, phishing).
            ! $this->urlGuard->looksPublic($draft->applyUrl),
            $draft->sourceUrl !== null && mb_strlen($draft->sourceUrl) > self::MAX_URL_LENGTH => self::SKIP_INVALID_URL,
            // Too old for the freshness window: never stored.
            $draft->postedAt !== null && $draft->postedAt < $cutoff => self::SKIP_STALE,
            default => null,
        };
    }
}
