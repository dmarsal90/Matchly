<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Applications\Domain\Services\DeterministicTailor;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Application\Support\SourceText;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Illuminate\Support\Str;

/**
 * Adapts the master CV to a job. With AI, bullets are reworded in the job's
 * vocabulary and a summary is written, but every change is checked against
 * the master CV: a reworded bullet that strays from its original, a skill
 * the CV does not have or a summary with new facts is discarded. Without
 * AI (or when it fails) the rule-based tailoring is used.
 */
final readonly class ResumeTailor
{
    private const float MIN_BULLET_OVERLAP = 0.6;

    private const float MIN_SUMMARY_OVERLAP = 0.7;

    public function __construct(
        private AiGateway $ai,
        private DeterministicTailor $rules,
        private SkillCatalog $catalog,
    ) {}

    public function tailor(User $user, ResumeData $master, JobListing $job, JobRequirements $requirements, string $language, bool $useAi): TailoredResume
    {
        $jobSkills = [...array_column($requirements->requiredSkills, 'skill'), ...array_column($requirements->niceToHaveSkills, 'skill')];
        $base = $this->rules->tailor($master, $jobSkills);

        if (! $useAi || $master->experience === [] && $master->skills === []) {
            return new TailoredResume($base, ContentSource::Rules);
        }

        try {
            $response = $this->ai->run(AiTask::TailorCv, $this->input($master, $job, $requirements, $language), $user);
        } catch (AiUnavailable $exception) {
            return new TailoredResume($base, ContentSource::Rules, $exception->reason);
        }

        return new TailoredResume($this->verified($master, $base, $response->data), ContentSource::Ai);
    }

    private function input(ResumeData $master, JobListing $job, JobRequirements $requirements, string $language): string
    {
        return (string) json_encode([
            'language' => $language,
            'cv' => [
                'headline' => $master->headline,
                'summary' => $master->summary,
                'skills' => $master->skills,
                'experience' => array_map(fn (array $position, int $index): array => [
                    'index' => $index,
                    'title' => $position['title'],
                    'company' => $position['company'],
                    'started_on' => $position['started_on'],
                    'finished_on' => $position['finished_on'],
                    'bullets' => $position['bullets'],
                ], $master->experience, array_keys($master->experience)),
            ],
            'job' => [
                'title' => $job->title,
                'company' => $job->company,
                'required_skills' => array_column($requirements->requiredSkills, 'name'),
                'nice_to_have_skills' => array_column($requirements->niceToHaveSkills, 'name'),
                'description' => Str::limit(JobSnapshotFactory::plainText($job->description), 3000),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function verified(ResumeData $master, ResumeData $base, array $data): ResumeData
    {
        $source = new SourceText($master->plainText());

        // Skills: only the CV's own, in the AI's order; the rest keep the rule-based order after them.
        $own = [];
        foreach ($master->skills as $skill) {
            $own[$this->slug($skill)] = $skill;
        }
        $skills = [];
        foreach ((array) ($data['skills'] ?? []) as $name) {
            $slug = is_string($name) ? $this->slug($name) : null;
            if ($slug !== null && isset($own[$slug]) && ! in_array($own[$slug], $skills, true)) {
                $skills[] = $own[$slug];
            }
        }
        $skills = [...$skills, ...array_values(array_diff($base->skills, $skills))];

        // Bullets: reworded ones must stay close to their own position's text.
        $experience = $base->experience;
        foreach ((array) ($data['experience'] ?? []) as $item) {
            $index = is_array($item) && is_int($item['index'] ?? null) ? $item['index'] : null;
            if ($index === null || ! isset($master->experience[$index])) {
                continue;
            }

            $original = $master->experience[$index];
            $positionText = new SourceText(implode("\n", [$original['title'], (string) $original['company'], ...$original['bullets']]));
            $bullets = array_values(array_filter(
                (array) ($item['bullets'] ?? []),
                fn (mixed $bullet): bool => is_string($bullet) && $positionText->overlap($bullet) >= self::MIN_BULLET_OVERLAP,
            ));

            if ($bullets !== []) {
                $experience[$index] = [...$original, 'bullets' => $bullets];
            }
        }

        $summary = is_string($data['summary'] ?? null) && $source->overlap($data['summary']) >= self::MIN_SUMMARY_OVERLAP
            ? $data['summary']
            : $base->summary;

        $keywords = array_values(array_filter((array) ($data['keywords'] ?? []), fn (mixed $keyword): bool => is_string($keyword) && $source->contains($keyword)));

        return $base->with([
            'summary' => $summary,
            'skills' => $skills,
            'experience' => $experience,
            'keywords' => $keywords !== [] ? $keywords : $base->keywords,
        ]);
    }

    private function slug(string $name): string
    {
        return $this->catalog->normalize($name)->slug ?? mb_strtolower($name);
    }
}
