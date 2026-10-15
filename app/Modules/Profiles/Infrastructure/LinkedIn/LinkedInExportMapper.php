<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\LinkedIn;

use App\Modules\Profiles\Domain\Enums\LanguageLevel;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use App\Modules\Profiles\Infrastructure\Imports\PositionSkills;
use App\Modules\Shared\Infrastructure\Localization\Languages;

/**
 * Maps the whitelisted CSVs of a LinkedIn export to structured profile data.
 *
 * Fairness: Profile.csv also carries birth date, address, zip code, maiden
 * name and messenger handles; none of them is read. Skills come from
 * Skills.csv and from known skills named in job positions, which also give
 * years of use and the last time they were used.
 */
final readonly class LinkedInExportMapper
{
    private const array COLUMNS = [
        'profile' => ['Headline', 'Summary', 'Industry', 'Geo Location', 'Websites'],
        'positions' => ['Company Name', 'Title', 'Description', 'Location', 'Started On', 'Finished On'],
        'education' => ['School Name', 'Start Date', 'End Date', 'Degree Name', 'Notes', 'Activities'],
        'skills' => ['Name'],
        'languages' => ['Name', 'Proficiency'],
        'certifications' => ['Name', 'Url', 'Authority', 'Started On', 'Finished On'],
        'projects' => ['Title', 'Description', 'Url', 'Started On', 'Finished On'],
    ];

    public function __construct(
        private SkillCatalog $catalog,
        private int $maxRows,
    ) {}

    /**
     * @param  array<string, string>  $files  section => CSV contents
     * @return array{profile: array<string, mixed>|null, positions: list<array<string, mixed>>, education: list<array<string, mixed>>, skills: list<array<string, mixed>>, languages: list<array<string, mixed>>, certifications: list<array<string, mixed>>, projects: list<array<string, mixed>>}
     */
    public function map(array $files): array
    {
        $rows = fn (string $section): array => isset($files[$section])
            ? CsvTable::rows($files[$section], self::COLUMNS[$section], $this->maxRows)
            : [];

        $positions = $this->positions($rows('positions'));

        return [
            'profile' => $this->profile($rows('profile')[0] ?? null),
            'positions' => $positions,
            'education' => $this->education($rows('education')),
            'skills' => $this->skills($rows('skills'), $positions),
            'languages' => $this->languages($rows('languages')),
            'certifications' => $this->certifications($rows('certifications')),
            'projects' => $this->projects($rows('projects')),
        ];
    }

    /**
     * @param  array<string, string>|null  $row
     * @return array<string, mixed>|null
     */
    private function profile(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        preg_match_all('#https?://[^\s,\]\[]+#i', $row['websites'], $urls);

        $profile = [
            'headline' => self::text($row['headline'], 200),
            'summary' => self::text($row['summary'], 3000),
            'industry' => self::text($row['industry'], 100),
            'location' => self::text($row['geo location'], 120),
            'websites' => array_slice(array_values(array_unique($urls[0])), 0, 5),
        ];

        return array_filter($profile, fn (mixed $value): bool => $value !== null && $value !== []) !== [] ? $profile : null;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array{title: string, company: string|null, description: string|null, location: string|null, started_on: string|null, finished_on: string|null}>
     */
    private function positions(array $rows): array
    {
        $positions = [];
        foreach ($rows as $row) {
            $title = self::text($row['title'], 150);
            if ($title === null) {
                continue;
            }

            $positions[] = [
                'title' => $title,
                'company' => self::text($row['company name'], 150),
                'description' => self::text($row['description'], 3000),
                'location' => self::text($row['location'], 120),
                'started_on' => LinkedInDate::parse($row['started on']),
                'finished_on' => LinkedInDate::parse($row['finished on']),
            ];
        }

        return $positions;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function education(array $rows): array
    {
        $education = [];
        foreach ($rows as $row) {
            $school = self::text($row['school name'], 150);
            if ($school === null) {
                continue;
            }

            $education[] = [
                'school' => $school,
                'degree' => self::text($row['degree name'], 150),
                'notes' => self::text($row['notes'], 1000),
                'started_on' => LinkedInDate::parse($row['start date']),
                'finished_on' => LinkedInDate::parse($row['end date']),
            ];
        }

        return $education;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<array{title: string, company: string|null, description: string|null, location: string|null, started_on: string|null, finished_on: string|null}>  $positions
     * @return list<array<string, mixed>>
     */
    private function skills(array $rows, array $positions): array
    {
        $listed = [];
        foreach ($rows as $row) {
            $skill = $this->catalog->normalize($row['name']);
            if ($skill !== null) {
                $listed[$skill->slug] ??= ['name' => $skill->label, 'evidence' => ['listed' => true]];
            }
        }

        return (new PositionSkills($this->catalog))->combine($listed, $positions, ['listed' => false]);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function languages(array $rows): array
    {
        $languages = [];
        foreach ($rows as $row) {
            $name = self::text($row['name'], 60);
            if ($name === null) {
                continue;
            }

            $languages[] = [
                'name' => $name,
                'code' => Languages::codeFor($name),
                'level' => self::languageLevel($row['proficiency'])?->value,
            ];
        }

        return $languages;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function certifications(array $rows): array
    {
        $certifications = [];
        foreach ($rows as $row) {
            $name = self::text($row['name'], 200);
            if ($name === null) {
                continue;
            }

            $certifications[] = [
                'name' => $name,
                'authority' => self::text($row['authority'], 150),
                'url' => self::url($row['url']),
                'started_on' => LinkedInDate::parse($row['started on']),
                'finished_on' => LinkedInDate::parse($row['finished on']),
            ];
        }

        return $certifications;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, mixed>>
     */
    private function projects(array $rows): array
    {
        $projects = [];
        foreach ($rows as $row) {
            $title = self::text($row['title'], 200);
            if ($title === null) {
                continue;
            }

            $projects[] = [
                'title' => $title,
                'description' => self::text($row['description'], 2000),
                'url' => self::url($row['url']),
                'started_on' => LinkedInDate::parse($row['started on']),
                'finished_on' => LinkedInDate::parse($row['finished on']),
            ];
        }

        return $projects;
    }

    /** LinkedIn writes proficiency in the export's language. */
    private static function languageLevel(string $proficiency): ?LanguageLevel
    {
        $value = mb_strtolower($proficiency);

        return match (true) {
            $value === '' => null,
            (bool) preg_match('/native|nativ|bilingu|biling/u', $value) => LanguageLevel::Native,
            (bool) preg_match('/full professional|profesional complet|profissional complet|professionnelle complète/u', $value) => LanguageLevel::C2,
            (bool) preg_match('/limited|limitad|básica profesional|basica profesional|limitée/u', $value) => LanguageLevel::B1,
            (bool) preg_match('/elementary|elemental|elementar|básic|basic|élémentaire/u', $value) => LanguageLevel::A2,
            (bool) preg_match('/professional|profesional|profissional|professionnel/u', $value) => LanguageLevel::C1,
            default => null,
        };
    }

    private static function text(string $value, int $max): ?string
    {
        $value = trim((string) preg_replace('/[ \t]+/', ' ', strip_tags($value)));

        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    private static function url(string $value): ?string
    {
        $value = trim($value);

        return preg_match('#^https?://\S+$#i', $value) === 1 && mb_strlen($value) <= 500 ? $value : null;
    }
}
