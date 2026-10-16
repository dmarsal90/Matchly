<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Learning\Domain\Services\CourseCatalog;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The skills the user's best-fitting and tracked jobs ask for and the profile
 * lacks, ranked by how many jobs they would open up. Built only from the
 * stored match results, so it costs one query and never calls AI.
 */
final readonly class SkillGapReport
{
    /** Best-scoring open jobs looked at besides the tracked ones. */
    private const int TOP_MATCHES = 100;

    private const int MIN_SCORE = 50;

    private const int MAX_SKILLS = 15;

    /** Jobs shown as "closest to a perfect fit". */
    private const int CLOSEST_JOBS = 3;

    public function __construct(private SkillCatalog $catalog, private CourseCatalog $courses) {}

    /**
     * @return array{jobs: int, tracked: int, skills: list<array{skill: string, name: string, jobs: int, required: int, tracked: int, only_gap: int, courses: list<array{title: string, provider: string, url: string, price: string, curated: bool}>, hours: int}>, closest: list<array{id: int, slug: string, title: string, company: string, score: int, missing: list<array{skill: string, name: string, required: bool, courses: list<array{title: string, provider: string, url: string, price: string, curated: bool}>, hours: int}>}>}
     */
    public function for(int $userId): array
    {
        $trackedIds = Application::query()->where('user_id', $userId)->pluck('job_listing_id')->map(intval(...))->all();
        $open = JobListing::query()->visible()->select('id');

        $matches = JobMatch::query()
            ->where('user_id', $userId)
            ->where('eligible', true)
            ->whereIn('job_listing_id', $open)
            ->where(fn ($query) => $query
                ->whereIn('job_listing_id', $trackedIds)
                ->orWhereIn('id', JobMatch::query()
                    ->select('id')
                    ->where('user_id', $userId)
                    ->where('eligible', true)
                    ->where('score', '>=', self::MIN_SCORE)
                    ->whereIn('job_listing_id', $open)
                    ->orderByDesc('score')
                    ->limit(self::TOP_MATCHES)))
            ->get(['job_listing_id', 'score', 'result']);

        /** @var array<string, array{skill: string, name: string, jobs: int, required: int, tracked: int, only_gap: int}> $skills */
        $skills = [];
        $tracked = array_flip($trackedIds);

        foreach ($matches as $match) {
            $missing = array_filter((array) ($match->result['missing_skills'] ?? []), fn (mixed $skill): bool => is_array($skill) && is_string($skill['skill'] ?? null));
            $requiredMissing = array_filter($missing, fn (array $skill): bool => (bool) ($skill['required'] ?? false));

            foreach ($missing as $skill) {
                $slug = $skill['skill'];
                $skills[$slug] ??= ['skill' => $slug, 'name' => $this->catalog->label($slug, (string) ($skill['name'] ?? $slug)), 'jobs' => 0, 'required' => 0, 'tracked' => 0, 'only_gap' => 0];
                $skills[$slug]['jobs']++;
                $skills[$slug]['required'] += ($skill['required'] ?? false) ? 1 : 0;
                $skills[$slug]['tracked'] += isset($tracked[$match->job_listing_id]) ? 1 : 0;
                // The one thing standing between the person and this job's requirements.
                $skills[$slug]['only_gap'] += count($requiredMissing) === 1 && ($skill['required'] ?? false) ? 1 : 0;
            }
        }

        $closest = $this->closest($matches);
        $ranked = array_values($skills);
        usort($ranked, fn (array $a, array $b): int => [$b['only_gap'], $b['required'], $b['jobs'], $a['name']] <=> [$a['only_gap'], $a['required'], $a['jobs'], $b['name']]);

        return [
            'jobs' => $matches->count(),
            'tracked' => $matches->filter(fn (JobMatch $match): bool => isset($tracked[$match->job_listing_id]))->count(),
            'skills' => array_map(fn (array $skill): array => [...$skill, 'courses' => $this->coursesFor($skill['skill'], $skill['name']), 'hours' => $this->hoursFor($skill['skill'])], array_slice($ranked, 0, self::MAX_SKILLS)),
            'closest' => $closest,
        ];
    }

    /**
     * The jobs the person is nearest to meeting in full: fewest skills still
     * missing (required or not), best score first. For each, what to learn.
     *
     * @param  Collection<int, JobMatch>  $matches
     * @return list<array{id: int, slug: string, title: string, company: string, score: int, missing: list<array{skill: string, name: string, required: bool, courses: list<array{title: string, provider: string, url: string, price: string, curated: bool}>, hours: int}>}>
     */
    private function closest(Collection $matches): array
    {
        $withGaps = $matches->filter(fn (JobMatch $match): bool => array_filter((array) ($match->result['missing_skills'] ?? []), fn (mixed $skill): bool => is_array($skill) && is_string($skill['skill'] ?? null)) !== []);
        $ranked = $withGaps->sortBy([
            fn (JobMatch $a, JobMatch $b): int => count((array) $a->result['missing_skills']) <=> count((array) $b->result['missing_skills']),
            fn (JobMatch $a, JobMatch $b): int => $b->score <=> $a->score,
        ])->take(self::CLOSEST_JOBS);

        $jobs = JobListing::query()->whereKey($ranked->pluck('job_listing_id')->all())->get(['id', 'title', 'company'])->keyBy('id');

        $closest = [];
        foreach ($ranked as $match) {
            $job = $jobs->get($match->job_listing_id);
            if ($job === null) {
                continue;
            }
            $missing = [];
            foreach ((array) $match->result['missing_skills'] as $skill) {
                if (is_array($skill) && is_string($skill['skill'] ?? null)) {
                    $name = $this->catalog->label($skill['skill'], (string) ($skill['name'] ?? $skill['skill']));
                    $missing[] = ['skill' => $skill['skill'], 'name' => $name, 'required' => (bool) ($skill['required'] ?? false), 'courses' => $this->coursesFor($skill['skill'], $name), 'hours' => $this->hoursFor($skill['skill'])];
                }
            }
            // Required skills first: they are what stands between the person and the job.
            usort($missing, fn (array $a, array $b): int => $b['required'] <=> $a['required']);
            $closest[] = ['id' => $job->id, 'slug' => Str::slug(Str::limit($job->title.' '.$job->company, 80, '')), 'title' => $job->title, 'company' => $job->company, 'score' => $match->score, 'missing' => $missing];
        }

        return $closest;
    }

    /**
     * @return list<array{title: string, provider: string, url: string, price: string, curated: bool}>
     */
    private function coursesFor(string $slug, string $name): array
    {
        return array_map(fn ($course): array => $course->toArray(), $this->courses->for($slug, $name));
    }

    /** A rough size for the study plan, by skill type; see config/courses.php. */
    private function hoursFor(string $slug): int
    {
        $hours = (array) config('courses.hours', []);

        return (int) ($hours[$this->catalog->type($slug) ?? 'default'] ?? $hours['default'] ?? 20);
    }
}
