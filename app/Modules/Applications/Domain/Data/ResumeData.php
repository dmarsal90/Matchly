<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Data;

/**
 * A CV as structured data: built from the profile (master), tailored for a
 * job, edited by the user and rendered to PDF. fromArray() is also the
 * sanitiser of user edits, so lengths and shapes are enforced in one place.
 *
 * @phpstan-type Contact array{name: string, email: string|null, phone: string|null, location: string|null, links: list<array{label: string, url: string}>}
 * @phpstan-type Position array{title: string, company: string|null, location: string|null, started_on: string|null, finished_on: string|null, bullets: list<string>}
 * @phpstan-type Education array{school: string, degree: string|null, started_on: string|null, finished_on: string|null}
 * @phpstan-type Certification array{name: string, authority: string|null}
 * @phpstan-type Project array{title: string, description: string|null, url: string|null}
 * @phpstan-type Language array{code: string, level: string}
 */
final readonly class ResumeData
{
    private const int MAX_POSITIONS = 20;

    private const int MAX_BULLETS = 10;

    /**
     * @param  Contact  $contact
     * @param  list<Position>  $experience
     * @param  list<Education>  $education
     * @param  list<Certification>  $certifications
     * @param  list<Project>  $projects
     * @param  list<string>  $skills
     * @param  list<Language>  $languages
     * @param  list<string>  $keywords  job keywords the CV demonstrates (highlighted)
     */
    public function __construct(
        public array $contact,
        public ?string $headline = null,
        public ?string $summary = null,
        public array $experience = [],
        public array $education = [],
        public array $certifications = [],
        public array $projects = [],
        public array $skills = [],
        public array $languages = [],
        public array $keywords = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $contact = (array) ($data['contact'] ?? []);
        $rows = fn (string $key): array => array_values(array_filter((array) ($data[$key] ?? []), 'is_array'));

        return new self(
            contact: [
                'name' => self::text($contact['name'] ?? null, 120) ?? '',
                'email' => self::text($contact['email'] ?? null, 190),
                'phone' => self::text($contact['phone'] ?? null, 40),
                'location' => self::text($contact['location'] ?? null, 120),
                'links' => array_values(array_filter(array_map(
                    fn (mixed $link): ?array => is_array($link) && is_string($link['url'] ?? null) && preg_match('#^https?://\S+$#i', $link['url']) === 1
                        ? ['label' => self::text($link['label'] ?? null, 40) ?? '', 'url' => mb_substr($link['url'], 0, 300)]
                        : null,
                    array_slice((array) ($contact['links'] ?? []), 0, 6),
                ))),
            ],
            headline: self::text($data['headline'] ?? null, 200),
            summary: self::text($data['summary'] ?? null, 1200),
            experience: array_slice(array_values(array_filter(array_map(fn (array $position): ?array => ($title = self::text($position['title'] ?? null, 150)) === null ? null : [
                'title' => $title,
                'company' => self::text($position['company'] ?? null, 150),
                'location' => self::text($position['location'] ?? null, 120),
                'started_on' => self::date($position['started_on'] ?? null),
                'finished_on' => self::date($position['finished_on'] ?? null),
                'bullets' => self::lines($position['bullets'] ?? [], self::MAX_BULLETS, 400),
            ], $rows('experience')))), 0, self::MAX_POSITIONS),
            education: array_values(array_filter(array_map(fn (array $item): ?array => ($school = self::text($item['school'] ?? null, 150)) === null ? null : [
                'school' => $school,
                'degree' => self::text($item['degree'] ?? null, 150),
                'started_on' => self::date($item['started_on'] ?? null),
                'finished_on' => self::date($item['finished_on'] ?? null),
            ], array_slice($rows('education'), 0, 10)))),
            certifications: array_values(array_filter(array_map(fn (array $item): ?array => ($name = self::text($item['name'] ?? null, 200)) === null ? null : [
                'name' => $name,
                'authority' => self::text($item['authority'] ?? null, 150),
            ], array_slice($rows('certifications'), 0, 15)))),
            projects: array_values(array_filter(array_map(fn (array $item): ?array => ($title = self::text($item['title'] ?? null, 200)) === null ? null : [
                'title' => $title,
                'description' => self::text($item['description'] ?? null, 600),
                'url' => is_string($item['url'] ?? null) && preg_match('#^https?://\S+$#i', $item['url']) === 1 ? mb_substr($item['url'], 0, 300) : null,
            ], array_slice($rows('projects'), 0, 10)))),
            skills: self::lines($data['skills'] ?? [], 40, 60),
            languages: array_values(array_filter(array_map(
                fn (mixed $language): ?array => is_array($language) && is_string($language['code'] ?? null) && is_string($language['level'] ?? null)
                    ? ['code' => mb_substr($language['code'], 0, 3), 'level' => mb_substr($language['level'], 0, 8)]
                    : null,
                array_slice((array) ($data['languages'] ?? []), 0, 15),
            ))),
            keywords: self::lines($data['keywords'] ?? [], 30, 60),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contact' => $this->contact,
            'headline' => $this->headline,
            'summary' => $this->summary,
            'experience' => $this->experience,
            'education' => $this->education,
            'certifications' => $this->certifications,
            'projects' => $this->projects,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'keywords' => $this->keywords,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->toArray(), ...$changes]);
    }

    /** All the CV's facts as text: what tailored content is checked against. */
    public function plainText(): string
    {
        $parts = [$this->headline, $this->summary, ...$this->skills];
        foreach ($this->experience as $position) {
            $parts = [...$parts, $position['title'], $position['company'], $position['started_on'], $position['finished_on'], ...$position['bullets']];
        }
        foreach ($this->education as $item) {
            $parts = [...$parts, $item['school'], $item['degree']];
        }
        foreach ($this->certifications as $item) {
            $parts = [...$parts, $item['name'], $item['authority']];
        }
        foreach ($this->projects as $item) {
            $parts = [...$parts, $item['title'], $item['description']];
        }

        return implode("\n", array_filter($parts, fn (?string $part): bool => $part !== null && $part !== ''));
    }

    public function isEmpty(): bool
    {
        return $this->experience === [] && $this->education === [] && $this->skills === [] && $this->summary === null && $this->headline === null;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim((string) preg_replace('/[ \t]+/u', ' ', strip_tags($value)));

        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(19|20)\d{2}(-(0[1-9]|1[0-2]))?$/', $value) === 1 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private static function lines(mixed $values, int $maxItems, int $maxLength): array
    {
        $lines = [];
        foreach ((array) $values as $value) {
            $line = self::text($value, $maxLength);
            if ($line !== null && ! in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        return array_slice($lines, 0, $maxItems);
    }
}
