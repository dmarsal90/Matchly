<?php

declare(strict_types=1);

namespace App\Modules\Matching\Application\Actions;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Application\PromptRepository;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Carbon\CarbonImmutable;

/**
 * Asks the AI for the structured requirements of a posting (once per job:
 * the result is stored) and normalises skill names through the catalog so
 * "Vue.js" in a posting meets "Vue" in a profile.
 */
final readonly class RefineJobRequirements
{
    public function __construct(
        private AiGateway $ai,
        private PromptRepository $prompts,
        private SkillCatalog $catalog,
    ) {}

    /**
     * @return bool whether the job's requirements changed
     *
     * @throws AiUnavailable
     */
    public function handle(JobListing $job): bool
    {
        if (($job->requirements['source'] ?? null) === JobRequirements::SOURCE_AI
            && ($job->requirements['prompt_version'] ?? null) === $this->prompts->version()) {
            return false;
        }

        $response = $this->ai->run(AiTask::ExtractJobRequirements, $this->input($job));
        $data = $response->data;

        $requirements = JobRequirements::fromArray([
            ...$data,
            'source' => JobRequirements::SOURCE_AI,
            'prompt_version' => $this->prompts->version(),
            'required_skills' => $this->skills((array) ($data['required_skills'] ?? []), withYears: true),
            'nice_to_have_skills' => $this->skills((array) ($data['nice_to_have_skills'] ?? []), withYears: false),
        ]);

        $job->forceFill(['requirements' => $requirements->toArray(), 'requirements_extracted_at' => CarbonImmutable::now()])->saveQuietly();

        return true;
    }

    private function input(JobListing $job): string
    {
        return "Job title: {$job->title}\nCompany: {$job->company}\nLocation: ".($job->location ?? 'not stated')
            ."\n\nPosting:\n".JobSnapshotFactory::plainText($job->description);
    }

    /**
     * @param  array<mixed>  $skills
     * @return list<array<string, mixed>>
     */
    private function skills(array $skills, bool $withYears): array
    {
        $normalised = [];
        foreach ($skills as $skill) {
            $name = is_array($skill) ? (string) ($skill['name'] ?? '') : '';
            $found = $this->catalog->normalize($name);
            if ($found === null || isset($normalised[$found->slug])) {
                continue;
            }

            $normalised[$found->slug] = ['skill' => $found->slug, 'name' => $found->label]
                + ($withYears ? ['min_years' => is_array($skill) && is_numeric($skill['min_years'] ?? null) ? (float) $skill['min_years'] : null] : []);
        }

        return array_values($normalised);
    }
}
