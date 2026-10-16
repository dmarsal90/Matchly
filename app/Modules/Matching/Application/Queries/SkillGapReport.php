<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Queries;

use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Infrastructure\Models\JobMatch;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

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

    public function __construct(private SkillCatalog $catalog) {}

    /**
     * @return array{jobs: int, tracked: int, skills: list<array{skill: string, name: string, jobs: int, required: int, tracked: int, only_gap: int}>}
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
            ->get(['job_listing_id', 'result']);

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

        $ranked = array_values($skills);
        usort($ranked, fn (array $a, array $b): int => [$b['only_gap'], $b['required'], $b['jobs'], $a['name']] <=> [$a['only_gap'], $a['required'], $a['jobs'], $b['name']]);

        return ['jobs' => $matches->count(), 'tracked' => $matches->filter(fn (JobMatch $match): bool => isset($tracked[$match->job_listing_id]))->count(), 'skills' => array_slice($ranked, 0, self::MAX_SKILLS)];
    }
}
