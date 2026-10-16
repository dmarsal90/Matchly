<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Generators;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Applications\Application\Queries\ApprovedCompanyFacts;
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
        private ApprovedCompanyFacts $companyFacts,
    ) {}

    public function write(User $user, ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience, bool $useAi): GeneratedLetter
    {
        $aiError = null;
        // Only what the person approved about the company; never anything the AI "knows".
        $facts = $this->companyFacts->for($user->id, $job->company);

        if ($useAi) {
            try {
                $text = $this->writeWithAi($user, $resume, $job, $requirements, $language, $tone, $length, $yearsExperience, $facts);
                $warnings = $this->warnings($text, $resume, $yearsExperience);
                if ($this->fit($resume, $requirements)['level'] === 'low') {
                    $warnings[] = ['key' => 'weak_fit', 'params' => []];
                }

                return new GeneratedLetter($text, ContentSource::Ai, $warnings);
            } catch (AiUnavailable $exception) {
                $aiError = $exception->reason;
            }
        }

        return new GeneratedLetter($this->template($resume, $job, $requirements, $language, $tone, $length, $yearsExperience, $facts), ContentSource::Rules, aiError: $aiError);
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

    /**
     * Asks the AI for the letter and, when the body lands outside the chosen
     * length, asks again with what was wrong (twice at most); the closest
     * attempt wins.
     *
     * @param  list<string>  $facts
     *
     * @throws AiUnavailable
     */
    private function writeWithAi(User $user, ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience, array $facts): string
    {
        $input = $this->input($resume, $job, $requirements, $language, $tone, $length, $yearsExperience, $facts);
        $best = null;
        $bestGap = PHP_INT_MAX;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->ai->run(AiTask::CoverLetter, $input, $user);
            $body = trim((string) $response->data['body']);
            $words = self::wordCount($body);
            $gap = max(0, $length->min() - $words, $words - $length->max());

            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = trim(implode("\n\n", [
                    $this->greeting((string) $response->data['greeting'], $job->company, $language, $tone),
                    $body,
                    (string) $response->data['closing']."\n".$resume->contact['name'],
                ]));
            }
            if ($gap === 0) {
                break;
            }

            $decoded = (array) json_decode($input, true);
            $decoded['revision'] = [
                'previous_body' => $body,
                'problem' => $words < $length->min()
                    ? "The body has {$words} words, which is too short. Rewrite it with between {$length->min()} and {$length->max()} words by adding relevant, true detail from candidate and job (no padding, no invented facts)."
                    : "The body has {$words} words, which is too long. Rewrite it with between {$length->min()} and {$length->max()} words, cutting what does not strengthen the application.",
            ];
            $input = (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $best;
    }

    /** Addresses the company's team by name when the AI left it out and the template has the language. */
    private function greeting(string $greeting, string $company, string $language, LetterTone $tone): string
    {
        if (mb_stripos($greeting, $company) !== false || ! in_array($language, self::TEMPLATE_LANGUAGES, true)) {
            return $greeting;
        }

        return (string) __("quick_apply.letter.greeting.{$tone->value}", ['company' => $company], $language);
    }

    public static function wordCount(string $text): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}\'’-]+/u', $text);
    }

    /**
     * How well the profile covers what the posting requires.
     *
     * @return array{level: string, matched: list<string>, missing: list<string>}
     */
    private function fit(ResumeData $resume, JobRequirements $requirements): array
    {
        $own = [];
        foreach ($resume->skills as $skill) {
            $own[$this->catalog->normalize($skill)->slug ?? mb_strtolower($skill)] = $skill;
        }

        $matched = [];
        $missing = [];
        foreach ([...$requirements->requiredSkills, ...$requirements->niceToHaveSkills] as $required) {
            $name = $own[$required['skill']] ?? null;
            if ($name !== null) {
                $matched[$required['skill']] = $name;
            } elseif (in_array($required, $requirements->requiredSkills, true)) {
                $missing[] = (string) $required['name'];
            }
        }

        $total = count($requirements->requiredSkills) + count($requirements->niceToHaveSkills);
        $level = $total === 0 || $matched === [] ? ($total === 0 ? 'unknown' : 'low') : (count($matched) / $total >= 0.5 ? 'high' : 'medium');

        return ['level' => $level, 'matched' => array_values($matched), 'missing' => $missing];
    }

    /**
     * @param  list<string>  $facts
     */
    private function input(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience, array $facts): string
    {
        $fit = $this->fit($resume, $requirements);

        return (string) json_encode([
            'language' => Locale::getDisplayLanguage($language, 'en') ?: $language,
            'tone' => $tone->value,
            'length_words' => $length->words(),
            'fit' => [
                'level' => $fit['level'],
                'matched_skills' => $fit['matched'],
                'missing_required_skills' => $fit['missing'],
            ],
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
                'company_facts' => $facts,
                'requirements' => [
                    'required_skills' => array_column($requirements->requiredSkills, 'name'),
                    'nice_to_have_skills' => array_column($requirements->niceToHaveSkills, 'name'),
                ],
                'description' => Str::limit(JobSnapshotFactory::plainText($job->description), 3000),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The letter without AI, from the profile only: the posting's role and the
     * skills both share, the real highlights of the latest roles and, if the
     * person approved any, what they like about the company. It is built in
     * the four-paragraph shape (opening, experience, fit, motivation and
     * invitation) and gets more real detail until it reaches the chosen length.
     *
     * @param  list<string>  $facts
     */
    private function template(ResumeData $resume, JobListing $job, JobRequirements $requirements, string $language, LetterTone $tone, LetterLength $length, ?int $yearsExperience, array $facts): string
    {
        $locale = in_array($language, self::TEMPLATE_LANGUAGES, true) ? $language : 'en';
        $t = fn (string $key, array $params = []): string => (string) __("quick_apply.letter.{$key}", $params, $locale);

        $fit = $this->fit($resume, $requirements);
        $skills = array_slice($fit['matched'], 0, 3);
        $skillList = $this->listing($skills, $t('and'));
        $company = $job->company;

        // 1. Opening: the role and what the person and the posting share.
        $opening = $skills !== []
            ? $t('intro_matched', ['title' => $job->title, 'company' => $company, 'skills' => $skillList])
            : $t('intro_generic', ['title' => $job->title, 'company' => $company]);

        // 2. Experience with evidence: the latest role and its highlights.
        $experience = [];
        $current = $resume->experience[0] ?? null;
        if ($current !== null && $current['company'] !== null) {
            $experience[] = $t($current['finished_on'] === null ? 'current_role' : 'last_role', ['title' => $current['title'], 'company' => $current['company']])
                .($yearsExperience !== null && $yearsExperience > 0 ? ' '.trans_choice('quick_apply.letter.years', $yearsExperience, ['count' => $yearsExperience], $locale) : '');
        }
        $perRole = ['short' => 1, 'medium' => 2, 'long' => 3][$length->value];
        $highlights = array_map(fn (string $bullet): string => $this->clip($bullet), array_slice($current['bullets'] ?? [], 0, $perRole + 1));
        if ($highlights !== []) {
            $experience[] = $t('highlights', ['items' => implode('; ', $highlights)]);
        }

        // 3. Fit with what the posting asks for.
        $fitParagraph = $skills !== [] ? $t('fit_matched', ['skills' => $skillList]) : $t('fit_generic');

        // 4. Motivation (the person's own words about the company) and the invitation.
        $area = $skills[0] ?? $current['title'] ?? $resume->headline ?? $job->title;
        $closing = [];
        foreach (array_slice($facts, 0, 2) as $fact) {
            $closing[] = $t('company_fact', ['company' => $company, 'fact' => $fact]);
        }
        $closing[] = $t('motivation', ['area' => $area]);
        $closing[] = $t("close.{$tone->value}", ['company' => $company]);
        if ($tone === LetterTone::Formal) {
            $closing[] = $t('thanks');
        }

        $text = fn (array $experience): string => implode("\n\n", [
            $t("greeting.{$tone->value}", ['company' => $company]),
            $opening,
            implode(' ', $experience),
            $fitParagraph,
            implode(' ', $closing),
            $t("sign_off.{$tone->value}")."\n".$resume->contact['name'],
        ]);

        // Below the chosen length, add more true detail: other highlights, earlier roles, a project, studies.
        $extras = [];
        foreach (array_slice($current['bullets'] ?? [], $perRole + 1, 2) as $bullet) {
            $extras[] = $t('highlights', ['items' => $this->clip($bullet)]);
        }
        foreach (array_slice($resume->experience, 1, 2) as $position) {
            if ($position['company'] !== null && ($position['bullets'][0] ?? null) !== null) {
                $extras[] = $t('earlier_role', ['title' => $position['title'], 'company' => $position['company'], 'item' => lcfirst($this->clip($position['bullets'][0]))]);
            }
        }
        if (($resume->projects[0]['title'] ?? null) !== null) {
            $extras[] = $t('project', ['project' => $resume->projects[0]['title']]);
        }
        if (($resume->education[0]['school'] ?? null) !== null) {
            $extras[] = $t('education', ['education' => trim(($resume->education[0]['degree'] ?? '').' · '.$resume->education[0]['school'], ' ·')]);
        }
        foreach ($extras as $extra) {
            if (self::wordCount($text($experience)) >= $length->min()) {
                break;
            }
            $experience[] = $extra;
        }

        return $text($experience);
    }

    /** A highlight as one sentence fragment: no trailing full stop, at most 180 characters. */
    private function clip(string $bullet): string
    {
        return rtrim(Str::limit(trim($bullet), 180, '…'), '.;');
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
