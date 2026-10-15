<?php

declare(strict_types=1);

namespace App\Modules\Matching\Domain\Services;

use App\Modules\Jobs\Domain\Enums\Region;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Matching\Domain\Data\JobRequirements;
use App\Modules\Matching\Domain\Data\JobSnapshot;
use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

/**
 * Free, instant requirement extraction from the posting text. Less nuanced
 * than the AI, but always available, so every job can be scored the moment
 * it arrives. Only catalog skills are recognised: free text is too noisy to
 * invent requirements from.
 */
final readonly class RuleBasedRequirementExtractor
{
    private const int MAX_SKILLS = 25;

    private const string NICE = '/\b(nice[- ]to[- ]have|bonus|a plus|is a plus|plus\b|preferred|preferably|ideally|desirable|advantage|deseable|valorable|se valorar|valoramos|diferencial|desejável|diferencial)\b/iu';

    private const array LANGUAGE_WORDS = [
        'english' => 'en', 'inglés' => 'en', 'ingles' => 'en', 'inglês' => 'en',
        'spanish' => 'es', 'español' => 'es', 'espanol' => 'es', 'castellano' => 'es', 'espanhol' => 'es',
        'portuguese' => 'pt', 'portugués' => 'pt', 'português' => 'pt',
        'german' => 'de', 'alemán' => 'de', 'deutsch' => 'de', 'alemão' => 'de',
        'french' => 'fr', 'francés' => 'fr', 'français' => 'fr', 'francês' => 'fr',
        'italian' => 'it', 'italiano' => 'it',
        'dutch' => 'nl', 'neerlandés' => 'nl',
        'polish' => 'pl', 'japanese' => 'ja', 'mandarin' => 'zh', 'chinese' => 'zh', 'arabic' => 'ar', 'russian' => 'ru',
    ];

    private const array TIMEZONES = [
        'pst' => -8, 'pdt' => -7, 'mst' => -7, 'cst' => -6, 'est' => -5, 'edt' => -4, 'brt' => -3, 'art' => -3,
        'gmt' => 0, 'wet' => 0, 'bst' => 1, 'cet' => 1, 'cest' => 2, 'eet' => 2, 'ist' => 5, 'sgt' => 8, 'jst' => 9, 'aest' => 10,
    ];

    private const array STOPWORDS = [
        'en' => ['the', 'and', 'with', 'you', 'our', 'for', 'will', 'are'],
        'es' => ['el', 'la', 'los', 'con', 'para', 'que', 'una', 'del'],
        'pt' => ['o', 'os', 'com', 'para', 'que', 'uma', 'você', 'não'],
        'de' => ['der', 'die', 'und', 'mit', 'wir', 'für', 'sie', 'ist'],
        'fr' => ['le', 'les', 'avec', 'pour', 'nous', 'vous', 'une', 'des'],
    ];

    public function __construct(private SkillCatalog $catalog) {}

    public function extract(JobSnapshot $job): JobRequirements
    {
        $text = $job->title."\n".$job->text;
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R|(?<=[.;!?])\s+/u', $text) ?: [])));

        [$required, $nice] = $this->skills($job, $lines);

        return new JobRequirements(
            source: JobRequirements::SOURCE_RULES,
            requiredSkills: $required,
            niceToHaveSkills: $nice,
            minYearsExperience: $this->overallYears($lines),
            seniority: $job->seniority,
            languages: $this->languages($lines),
            degreeRequired: $this->degreeRequired($lines),
            degreeField: $this->degreeField($text),
            certifications: $this->certifications($text),
            locationExclusive: $this->exclusive($job, $text),
            countries: array_values(array_filter($job->allowedRegions, fn (string $value): bool => strlen($value) === 2)),
            regions: array_values(array_filter(array_map(Region::tryFrom(...), $job->allowedRegions))),
            utcOffsetMin: ($range = $this->timezones($text))[0],
            utcOffsetMax: $range[1],
            asksFor: [
                'cover_letter' => $this->mentions($text, '/\b(cover letter|motivation(al)? letter|carta de presentaci[oó]n|carta de motivaci[oó]n|carta de apresenta[cç][aã]o)\b/iu'),
                'github' => $this->mentions($text, '/\bgithub\b/iu'),
                'portfolio' => $this->mentions($text, '/\b(portfolio|portafolio|portf[oó]lio|dribbble|behance)\b/iu'),
                'salary_expectations' => $this->mentions($text, '/\b(salary (expectations?|requirements?)|expected salary|expectativa salarial|pretensi[oó]n salarial|pretens[aã]o salarial)\b/iu'),
                'additional_questions' => $this->mentions($text, '/\b(screening questions|application questions|answer the (following|questions)|questions below|responde (a )?las siguientes preguntas)\b/iu'),
            ],
            language: $this->postingLanguage($text),
        );
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: list<array{skill: string, name: string, min_years: float|null}>, 1: list<array{skill: string, name: string}>}
     */
    private function skills(JobSnapshot $job, array $lines): array
    {
        $inRequiredContext = [];
        $inNiceContext = [];
        $years = [];

        foreach ($lines as $line) {
            $found = array_keys($this->catalog->detectIn($line));
            if ($found === []) {
                continue;
            }

            if (preg_match(self::NICE, $line) === 1) {
                $inNiceContext = [...$inNiceContext, ...$found];
            } else {
                $inRequiredContext = [...$inRequiredContext, ...$found];
            }

            // "3+ years of Python": years attach to the skills named right after them.
            if (preg_match_all('/(\d{1,2})\s*\+?\s*(?:-\s*\d{1,2}\s*)?(?:years?|yrs|años|anos|ans|jahre)\b(.{0,60})/iu', $line, $matches, PREG_SET_ORDER) > 0) {
                foreach ($matches as $match) {
                    foreach (array_keys($this->catalog->detectIn($match[2])) as $slug) {
                        $years[$slug] = max($years[$slug] ?? 0, (int) $match[1]);
                    }
                }
            }
        }

        $requiredSlugs = array_values(array_unique($inRequiredContext));
        $niceSlugs = array_values(array_diff(array_unique($inNiceContext), $requiredSlugs));

        // Provider tags are hints, not requirements.
        foreach ($job->tags as $tag) {
            $skill = $this->catalog->normalize($tag);
            if ($skill !== null && $skill->known && ! in_array($skill->slug, $requiredSlugs, true) && ! in_array($skill->slug, $niceSlugs, true)) {
                $niceSlugs[] = $skill->slug;
            }
        }

        $required = array_map(fn (string $slug): array => [
            'skill' => $slug,
            'name' => $this->catalog->label($slug, $slug),
            'min_years' => isset($years[$slug]) && $years[$slug] <= 20 ? (float) $years[$slug] : null,
        ], array_slice($requiredSlugs, 0, self::MAX_SKILLS));

        $nice = array_map(fn (string $slug): array => [
            'skill' => $slug,
            'name' => $this->catalog->label($slug, $slug),
        ], array_slice($niceSlugs, 0, self::MAX_SKILLS));

        return [$required, $nice];
    }

    /**
     * @param  list<string>  $lines
     */
    private function overallYears(array $lines): ?int
    {
        $years = null;
        foreach ($lines as $line) {
            if (preg_match(self::NICE, $line) === 1 || preg_match('/experience|experiencia|experiência|erfahrung|expérience/iu', $line) !== 1) {
                continue;
            }
            if (preg_match_all('/(\d{1,2})\s*\+?\s*(?:-\s*\d{1,2}\s*)?(?:years?|yrs|años|anos|ans|jahre)\b/iu', $line, $matches) > 0) {
                foreach ($matches[1] as $value) {
                    if ((int) $value <= 20) {
                        $years = max($years ?? 0, (int) $value);
                    }
                }
            }
        }

        return $years;
    }

    /**
     * @param  list<string>  $lines
     * @return list<array{code: string, level: LanguageLevel|null, required: bool}>
     */
    private function languages(array $lines): array
    {
        $languages = [];
        $names = implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), array_keys(self::LANGUAGE_WORDS)));

        foreach ($lines as $line) {
            if (preg_match_all('/\b('.$names.')\b/iu', $line, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            // Only lines that talk about speaking it, not e.g. "English-speaking customers".
            if (preg_match('/\b(fluent|fluency|native|proficien|speak|written|spoken|level|c1|c2|b2|b1|business|professional|excellent|good|strong|fluid|nivel|fluido|fluente|nativo|hablar|dominio|avanzado|avançado|idioma|language)\w*/iu', $line) !== 1) {
                continue;
            }

            $nice = preg_match(self::NICE, $line) === 1;
            foreach ($matches[1] as [$word, $offset]) {
                $code = self::LANGUAGE_WORDS[mb_strtolower($word)];
                $around = mb_strtolower(substr($line, max(0, $offset - 40), 100));
                $languages[$code] ??= ['code' => $code, 'level' => $this->level($around), 'required' => ! $nice];
            }
        }

        return array_values($languages);
    }

    private function level(string $text): ?LanguageLevel
    {
        return match (true) {
            (bool) preg_match('/\b(native|nativ|mother tongue|lengua materna)/u', $text) => LanguageLevel::Native,
            (bool) preg_match('/\bc2\b/', $text) => LanguageLevel::C2,
            (bool) preg_match('/\b(c1|fluent|fluency|fluid|fluido|fluente|excellent|excelente|advanced|avanzado|avançado)/u', $text) => LanguageLevel::C1,
            (bool) preg_match('/\b(b2|business|professional|profesional|profissional|good|strong|buen)/u', $text) => LanguageLevel::B2,
            (bool) preg_match('/\b(b1|intermediate|intermedio)/u', $text) => LanguageLevel::B1,
            (bool) preg_match('/\b(a2|basic|básico|basico)/u', $text) => LanguageLevel::A2,
            default => null,
        };
    }

    /**
     * @param  list<string>  $lines
     */
    private function degreeRequired(array $lines): bool
    {
        foreach ($lines as $line) {
            if (preg_match('/\b(bachelor|master\'?s|degree|b\.?sc|m\.?sc|ph\.?d|licenciatura|grado universitario|t[ií]tulo universitario|ingenier[ií]a|gradua[cç][aã]o)\b/iu', $line) === 1
                && preg_match(self::NICE, $line) !== 1
                && preg_match('/\b(or equivalent|equivalent experience|o experiencia equivalente)\b/iu', $line) !== 1) {
                return true;
            }
        }

        return false;
    }

    private function degreeField(string $text): ?string
    {
        if (preg_match('/\b(?:degree|bachelor\'?s?|master\'?s?|b\.?sc|m\.?sc)\s+(?:degree\s+)?in\s+([A-Z][\w &-]{2,50}?)(?=\s+(?:required|preferred|is|or|and|with|from)\b|[.,;\n(]|$)/u', $text, $match) === 1) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function certifications(string $text): array
    {
        preg_match_all('/\b(AWS Certified [A-Z][\w -]{2,40}|Certified Kubernetes (?:Administrator|Application Developer|Security Specialist)|CKA|CKAD|PMP|PRINCE2|CISSP|CISM|CompTIA \w+|Scrum Master|PSM ?I{1,3}|CSM|Google Cloud Certified[\w -]{0,40}|Azure (?:Fundamentals|Administrator|Developer|Solutions Architect)[\w -]{0,20})\b/u', $text, $matches);

        return array_slice(array_values(array_unique(array_map('trim', $matches[1]))), 0, 10);
    }

    private function exclusive(JobSnapshot $job, string $text): bool
    {
        if ($job->remoteScope === RemoteScope::CountryRestricted) {
            return true;
        }

        return $job->allowedRegions !== [] && preg_match('/\b(only|must (be|reside|live)|residents?|based in|located in|authori[sz]ed to work|work authori[sz]ation|right to work|citizens?|eligible to work|solo (residentes|candidatos)|residir en)\b/iu', $text) === 1;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function timezones(string $text): array
    {
        $offsets = [];
        if (preg_match_all('/\b(?:utc|gmt)\s*([+\-−–]\s*\d{1,2})(?::\d{2})?/iu', $text, $matches) > 0) {
            foreach ($matches[1] as $value) {
                $offsets[] = (int) str_replace(['−', '–', ' '], ['-', '-', ''], $value);
            }
        }
        if (preg_match_all('/\b(pst|pdt|mst|cst|est|edt|brt|bst|cet|cest|eet|sgt|jst|aest)\b/u', strtolower($text), $matches) > 0) {
            foreach ($matches[1] as $zone) {
                $offsets[] = self::TIMEZONES[$zone];
            }
        }

        $offsets = array_values(array_filter($offsets, fn (int $offset): bool => $offset >= -12 && $offset <= 14));

        return $offsets === [] ? [null, null] : [min($offsets), max($offsets)];
    }

    private function postingLanguage(string $text): ?string
    {
        $words = array_count_values(preg_split('/[^\p{L}]+/u', mb_strtolower($text)) ?: []);
        $scores = [];
        foreach (self::STOPWORDS as $language => $stopwords) {
            $scores[$language] = array_sum(array_map(fn (string $word): int => $words[$word] ?? 0, $stopwords));
        }
        arsort($scores);

        return reset($scores) > 2 ? (string) array_key_first($scores) : null;
    }

    private function mentions(string $text, string $pattern): bool
    {
        return preg_match($pattern, $text) === 1;
    }
}
