<?php

declare(strict_types=1);

namespace App\Modules\Jobs\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Actions\RecordJobView;
use App\Modules\Jobs\Application\Queries\JobFilters;
use App\Modules\Jobs\Application\Queries\JobSearchQuery;
use App\Modules\Jobs\Application\Queries\ViewerJobState;
use App\Modules\Jobs\Domain\Enums\DateRange;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobGroup;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\JobSort;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Http\Requests\JobSearchRequest;
use App\Modules\Jobs\Http\Resources\JobListingResource;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Providers\Infrastructure\Models\Source;
use BackedEnum;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public on purpose: job listings are never gated behind sign-up (several
 * sources' terms forbid using their jobs to collect accounts).
 */
final class JobController
{
    public function index(JobSearchRequest $request, JobSearchQuery $search, ViewerJobState $viewerState, #[CurrentUser] ?User $user): Response
    {
        $filters = $request->toFilters();
        $jobs = $search->paginate($filters, $request->page());
        $ids = array_values(array_map(fn (JobListing $job): int => $job->id, $jobs->items()));
        [$tracked, $viewed] = $viewerState->for($user, $ids);

        return Inertia::render('jobs/Index', [
            'jobs' => [
                'data' => array_map(
                    fn (JobListing $job): array => (new JobListingResource($job))->withViewerState($tracked, $viewed)->resolve($request),
                    $jobs->items(),
                ),
                'meta' => [
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'per_page' => $jobs->perPage(),
                    'total' => $jobs->total(),
                    'from' => $jobs->firstItem(),
                    'to' => $jobs->lastItem(),
                ],
            ],
            'groups' => $filters->groupBy !== null ? $search->groupCounts($filters) : null,
            'filters' => $this->echo($filters),
            'options' => Inertia::once(fn (): array => $this->options($search)),
            'viewer' => $user !== null ? [
                'onboarding_completed' => $user->profile?->onboarding_completed_at !== null,
                'search_query' => $user->profile?->searchProfile()?->toListingQuery() ?: null,
            ] : null,
        ]);
    }

    public function show(Request $request, JobListing $job, RecordJobView $recordView, ViewerJobState $viewerState, #[CurrentUser] ?User $user): Response|RedirectResponse
    {
        if ($job->duplicate_of_id !== null) {
            return to_route('jobs.show', ['job' => $job->duplicate_of_id], 301);
        }

        // Held for moderation: invisible to everyone but admins.
        abort_if($job->status === JobStatus::Flagged && ($user === null || ! $user->is_admin), 404);

        $job->load('source:id,key,name,homepage_url,requires_attribution');

        if ($user !== null) {
            $recordView->handle($user, $job);
        }

        [$tracked, $viewed] = $viewerState->for($user, [$job->id]);

        return Inertia::render('jobs/Show', [
            'job' => (new JobListingResource($job))->withViewerState($tracked, $viewed)->withDescription()->resolve($request),
            'isOpen' => $job->status === JobStatus::Open,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function echo(JobFilters $filters): array
    {
        $values = fn (array $enums): array => array_map(fn (BackedEnum $enum): string|int => $enum->value, $enums);

        return [
            'q' => $filters->search,
            'work_mode' => $values($filters->workModes),
            'scope' => $values($filters->remoteScopes),
            'type' => $values($filters->employmentTypes),
            'role' => $values($filters->roles),
            'category' => $values($filters->categories),
            'seniority' => $values($filters->seniorities),
            'source' => $filters->sources,
            'tag' => $filters->tags,
            'posted' => $filters->dateRange?->value,
            'from' => $filters->postedFrom?->format('Y-m-d'),
            'to' => $filters->postedTo?->format('Y-m-d'),
            'salary_min' => $filters->salaryMin,
            'currency' => $filters->salaryCurrency,
            'sort' => $filters->effectiveSort()->value,
            'group' => $filters->groupBy?->value,
            'per_page' => $filters->perPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(JobSearchQuery $search): array
    {
        $cases = fn (string $enum): array => array_column($enum::cases(), 'value');

        return [
            'work_mode' => $cases(WorkMode::class),
            'scope' => $cases(RemoteScope::class),
            'type' => $cases(EmploymentType::class),
            'role' => $cases(JobRole::class),
            'category' => $cases(JobCategory::class),
            'seniority' => $cases(Seniority::class),
            'posted' => $cases(DateRange::class),
            'sort' => $cases(JobSort::class),
            'group' => $cases(JobGroup::class),
            'per_page' => JobSearchRequest::PER_PAGE_OPTIONS,
            'default_per_page' => (int) config('jobs.per_page'),
            'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'BRL', 'INR', 'PLN'],
            'source' => Source::query()->where('enabled', true)->orderBy('name')->get(['key', 'name'])->toArray(),
            'tag' => $search->popularTags(),
        ];
    }
}
