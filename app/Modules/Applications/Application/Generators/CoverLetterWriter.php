<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Domain\Enums\LetterTone;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\JobSnapshotFactory;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Illuminate\Support\Str;
use Locale;

/**
 * Writes the cover letter in the posting's language. The AI version is
 * checked for claims the profile does not back (skills it lacks, more years
 * than it has) and those are reported as warnings for the person to fix.
 * Without AI, a translated template built from the profile is used.
 */
final readonly class CoverLetterWriter
{
    /** Languages the template exists in; the AI can write in any. */
    public const array TEMPLATE_LANGUAGES = ['es', 'en', 'pt'];

    public function __construct(
        private AiGateway $ai,
        private SkillCatalog $catalog,
    ) {}

    public function write(User $user, ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience, bool $useAi): GeneratedLetter
    {
        $aiError = null;

        if ($useAi) {
            try {
                $response = $this->ai->run(AiTask::CoverLetter, $this->input($resume, $job, $requirements, $language, $tone, $length, $yearsExperience), $user);
                $text = trim(implode("\n\n", [
                    (string) $response->data['greeting'],
                    trim((string) $response->data['body']),
                    (string) $response->data['closing']."\n".$resume->contact['name'],
                ]));

                return new GeneratedLetter($text, ContentSource::Ai, $this->warnings($text, $resume, $yearsExperience));
            } catch (AiUnavailable $exception) {
                $aiError = $exception->reason;
            }
        }

        return new GeneratedLetter($this->template($resume, $job, $requirements, $language, $tone, $yearsExperience), ContentSource::Rules, aiError: $aiError);
    }

    /**
     * Claims in a letter that the profile does not back: skills it lacks and
     * more years of experience than it states.
     *
     * @return list<array{key: string, params: array<string, string>}>
     */
    public function warnings(string $letter, ResumeData $resume, ?int $yearsExperience): array
    {
        $warnings = [];

        $own = array_keys($this->catalog->detectIn($resume->plainText()));
        foreach ($resume->skills as $skill) {
            $own[] = $this->catalog->normalize($skill)->slug ?? '';
        }
        $unbacked = array_diff(array_keys($this->catalog->detectIn($letter)), $own);
        if ($unbacked !== []) {
            $names = array_map(fn (string $slug): string => $this->catalog->label($slug, $slug), $unbacked);
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            $warnings[] = ['key' => 'unbacked_skills', 'params' => ['skills' => implode(', ', $names)]];
        }

        if ($yearsExperience !== null && preg_match_all('/\b(\d{1,2})\s*\+?\s*(?:years?|años|anos|ans|jahre)\b/iu', $letter, $matches) > 0) {
            $claimed = max(0, ...array_map(intval(...), $matches[1]));
            if ($claimed > $yearsExperience) {
                $warnings[] = ['key' => 'years_claim', 'params' => ['claimed' => (string) $claimed, 'have' => (string) $yearsExperience]];
            }
        }

        return $warnings;
    }

    private function input(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience): string
    {
        return (string) json_encode([
            'language' => Locale::getDisplayLanguage($language, 'en') ?: $language,
            'tone' => $tone->value,
            'length_words' => $length->words(),
            'candidate' => [
                'name' => $resume->contact['name'],
                'headline' => $resume->headline,
                'summary' => $resume->summary,
                'years_of_experience' => $yearsExperience,
                'skills' => $resume->skills,
                'experience' => array_map(fn (array $position): array => [
                    'title' => $position['title'],
                    'company' => $position['company'],
                    'from' => $position['started_on'],
                    'to' => $position['finished_on'] ?? 'present',
                    'highlights' => array_slice($position['bullets'], 0, 3),
                ], array_slice($resume->experience, 0, 5)),
                'education' => array_map(fn (array $item): string => trim(($item['degree'] ?? '').' · '.$item['school'], ' ·'), $resume->education),
            ],
            'job' => [
                'title' => $job->title,
                'company' => $job->company,
                'requirements' => [
                    'required_skills' => array_column($requirements->requiredSkills, 'name'),
                    'nice_to_have_skills' => array_column($requirements->niceToHaveSkills, 'name'),
                ],
                'description' => Str::limit(JobSnapshotFactory::plainText($job->description), 3000),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function template(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, ?int $yearsExperience): string
    {
        $locale = in_array($language, self::TEMPLATE_LANGUAGES, true) ? $language : 'en';
        $t = fn (string $key, array $params = []): string => (string) __("quick_apply.letter.{$key}", $params, $locale);

        // Only skills the CV actually has, in the order the job lists them.
        $jobSkills = [...array_column($requirements->requiredSkills, 'skill'), ...array_column($requirements->niceToHaveSkills, 'skill')];
        $own = [];
        foreach ($resume->skills as $skill) {
            $own[$this->catalog->normalize($skill)->slug ?? mb_strtolower($skill)] = $skill;
        }
        $matched = array_slice(array_values(array_filter(array_map(fn (string $slug): ?string => $own[$slug] ?? null, $jobSkills))), 0, 4);
        $current = $resume->experience[0] ?? null;

        $paragraphs = [$t('intro', ['title' => $job->title, 'company' => $job->company])];

        if ($current !== null && $current['company'] !== null) {
            $paragraphs[] = $t($current['finished_on'] === null ? 'current_role' : 'last_role', ['title' => $current['title'], 'company' => $current['company']])
                .($yearsExperience !== null && $yearsExperience > 0 ? ' '.trans_choice('quick_apply.letter.years', $yearsExperience, ['count' => $yearsExperience], $locale) : '');
        } elseif ($resume->headline !== null) {
            $paragraphs[] = $t('headline', ['headline' => $resume->headline]);
        }

        if ($matched !== []) {
            $paragraphs[] = $t('skills', ['skills' => $this->listing($matched, $t('and'))]);
        }

        $paragraphs[] = $t("close.{$tone->value}", ['company' => $job->company]);

        return implode("\n\n", [
            $t("greeting.{$tone->value}"),
            ...$paragraphs,
            $t("sign_off.{$tone->value}")."\n".$resume->contact['name'],
        ]);
    }

    /**
     * @param  list<string>  $items
     */
    private function listing(array $items, string $and): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items)." {$and} {$last}";
    }
}
