<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Application\Queries\ApprovedCompanyFacts;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Domain\Data\JobRequirements;
use Illuminate\Support\Str;
use Locale;

/**
 * A draft answer to an open question of an application form ("Why this role?").
 * With AI it is written from the CV and the posting; without it, a short
 * template stitched from the same real facts. The AI answer is checked against
 * the CV for skills it does not back, and the person reviews it either way.
 *
 * @phpstan-type Answer array{answer: string, source: ContentSource, warnings: list<array{key: string, params: array<string, string>}>}
 */
final readonly class FormAnswerWriter
{
    private const int MAX_WORDS = 150;

    public function __construct(
        private AiGateway $ai,
        private CoverLetterWriter $letters,
        private ApprovedCompanyFacts $companyFacts,
    ) {}

    /**
     * @return Answer
     */
    public function write(User $user, ResumeData $resume, JobListing $job, JobRequirements $requirements, string $question, string $language, bool $useAi): array
    {
        if ($useAi) {
            try {
                $response = $this->ai->run(AiTask::FormAnswer, (string) json_encode([
                    'language' => Locale::getDisplayLanguage($language, 'en') ?: $language,
                    'question' => $question,
                    'max_words' => self::MAX_WORDS,
                    'company_facts' => $this->companyFacts->for($user->id, $job->company),
                    'job' => [
                        'title' => $job->title,
                        'company' => $job->company,
                        'required_skills' => array_column($requirements->requiredSkills, 'name'),
                        'nice_to_have_skills' => array_column($requirements->niceToHaveSkills, 'name'),
                        'description' => Str::limit(trim(strip_tags($job->description)), 4000, ''),
                    ],
                    'candidate' => [
                        'headline' => $resume->headline,
                        'summary' => $resume->summary,
                        'skills' => $resume->skills,
                        'experience' => array_map(fn (array $position): array => ['title' => $position['title'], 'company' => $position['company'], 'bullets' => $position['bullets']], $resume->experience),
                        'education' => $resume->education,
                        'projects' => $resume->projects,
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $user);
                $answer = trim((string) $response->data['answer']);

                return ['answer' => $answer, 'source' => ContentSource::Ai, 'warnings' => $this->letters->warnings($answer, $resume, null)];
            } catch (AiUnavailable) {
                // Fall through to the template.
            }
        }

        return ['answer' => $this->template($resume, $job, $requirements, $language), 'source' => ContentSource::Rules, 'warnings' => []];
    }

    private function template(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language): string
    {
        $wanted = array_map(fn (string $name): string => mb_strtolower($name), [...array_column($requirements->requiredSkills, 'name'), ...array_column($requirements->niceToHaveSkills, 'name')]);
        $shared = array_slice(array_values(array_filter($resume->skills, fn (string $skill): bool => in_array(mb_strtolower($skill), $wanted, true))), 0, 3);

        $parts = [trans('form_answer.intro', ['job' => $job->title, 'company' => $job->company], $language)];
        if ($shared !== []) {
            $parts[] = trans('form_answer.skills', ['skills' => implode(', ', $shared)], $language);
        }
        $position = $resume->experience[0] ?? null;
        if ($position !== null && ($position['bullets'][0] ?? '') !== '') {
            $parts[] = trans('form_answer.experience', ['title' => $position['title'], 'bullet' => rtrim($position['bullets'][0], '. ')], $language);
        } elseif ($resume->headline !== null && $resume->headline !== '') {
            $parts[] = trans('form_answer.headline', ['headline' => $resume->headline], $language);
        }

        return implode(' ', $parts);
    }
}
