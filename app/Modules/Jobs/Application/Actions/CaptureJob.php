<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Application\Actions;

use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Contracts\JobRepository;
use App\Modules\Jobs\Domain\Contracts\WorkModeClassifier;
use App\Modules\Jobs\Domain\Data\JobData;
use App\Modules\Jobs\Domain\Data\JobDraft;
use App\Modules\Jobs\Domain\Services\CanonicalUrl;
use App\Modules\Jobs\Domain\Services\SuspicionDetector;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use App\Modules\Shared\Domain\Contracts\HtmlSanitizer;
use App\Modules\Shared\Infrastructure\Http\PublicUrlGuard;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Stores a posting a person found on a page we do not ingest (the browser
 * extension sends it). It goes through the same sanitising, classification and
 * moderation as any source, and a posting we already have is reused, not
 * duplicated. Unlike a feed it is not filtered by work mode or age: the person
 * chose it.
 */
final readonly class CaptureJob
{
    public const string SOURCE = 'extension';

    public function __construct(
        private WorkModeClassifier $classifier,
        private HtmlSanitizer $sanitizer,
        private SuspicionDetector $suspicion,
        private JobRepository $jobs,
        private PublicUrlGuard $urlGuard,
        private Dispatcher $events,
    ) {}

    /**
     * @return JobListing|null null when the address is not a public web page
     */
    public function handle(string $url, string $title, string $company, ?string $description, ?string $location, ?DateTimeImmutable $postedAt): ?JobListing
    {
        if (preg_match('#^https?://#i', $url) !== 1 || ! $this->urlGuard->looksPublic($url)) {
            return null;
        }

        $canonical = CanonicalUrl::from($url);
        $existing = JobListing::query()->where('canonical_url', $canonical)->orderBy('id')->first();
        if ($existing !== null) {
            return $this->original($existing);
        }

        Source::query()->firstOrCreate(
            ['key' => self::SOURCE],
            ['name' => 'Browser capture', 'homepage_url' => (string) config('app.url'), 'requires_attribution' => false],
        );

        $draft = new JobDraft(
            source: self::SOURCE,
            externalId: hash('sha256', $canonical),
            title: trim($title),
            company: trim($company),
            description: $this->sanitizer->sanitize((string) $description),
            applyUrl: $url,
            locationText: $location,
            postedAt: $postedAt,
        );
        $job = JobData::fromDraft($draft, $this->classifier->classify($draft));
        $job = $job->withFlagReasons($this->suspicion->reasons($job));

        $result = $this->jobs->upsert(self::SOURCE, [$job], new DateTimeImmutable);
        $record = JobListing::query()->where('external_id', $job->externalId)->whereHas('source', fn ($query) => $query->where('key', self::SOURCE))->firstOrFail();

        if ($result->created !== []) {
            $this->events->dispatch(new JobsIngested(self::SOURCE, $result->created));
        }

        return $this->original($record);
    }

    /** A duplicate of a posting from another source points at the original; that is the one to show. */
    private function original(JobListing $job): JobListing
    {
        return $job->duplicate_of_id !== null ? JobListing::query()->findOrFail($job->duplicate_of_id) : $job;
    }
}
