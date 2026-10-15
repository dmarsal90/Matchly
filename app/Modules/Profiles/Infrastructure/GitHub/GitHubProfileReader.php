<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\GitHub;

use App\Modules\Profiles\Domain\Exceptions\ImportFailed;
use App\Modules\Profiles\Domain\Services\EvidenceLevel;
use App\Modules\Profiles\Domain\Services\SkillCatalog;
use Carbon\CarbonImmutable;

/**
 * Reads a user's public GitHub activity and turns it into skill evidence:
 * own, non-fork repositories pushed to recently, their languages, topics,
 * dependency manifests and marker files (Dockerfile, artisan…). READMEs are
 * kept as short excerpts for the AI summary; nothing private is requested.
 */
final readonly class GitHubProfileReader
{
    private const int MAX_SAMPLE_REPOSITORIES = 5;

    /**
     * @param  array<string, string>  $manifests  file name => ecosystem
     * @param  array<string, string>  $markerFiles  file name => skill slug
     * @param  list<string>  $ignoredLanguages
     */
    public function __construct(
        private GitHubClient $client,
        private SkillCatalog $catalog,
        private ManifestParser $manifestParser,
        private array $manifests,
        private array $markerFiles,
        private array $ignoredLanguages,
        private int $recentMonths,
        private int $maxRepositories,
        private int $anonymousMaxRepositories,
        private int $readmeRepositories,
        private int $readmeMaxChars,
        private int $maxManifestBytes,
        private float $minLanguageShare,
    ) {}

    /**
     * @return array{username: string, name: string|null, profile_url: string, public_repos: int, authenticated: bool, repositories: list<array<string, mixed>>, skills: list<array<string, mixed>>, fetched_at: string}
     */
    public function read(string $username, ?string $token = null): array
    {
        $client = $this->client->withToken($token);

        $user = $client->user($username);
        if ($user === null || ($user['type'] ?? 'User') !== 'User') {
            throw ImportFailed::because(ImportFailed::NOT_FOUND, "GitHub user {$username} not found.");
        }

        $login = (string) ($user['login'] ?? $username);
        $limit = $client->authenticated() ? $this->maxRepositories : $this->anonymousMaxRepositories;
        $since = CarbonImmutable::now()->subMonths($this->recentMonths);

        $candidates = array_filter(
            $client->repositories($login),
            fn (array $repo): bool => ($repo['fork'] ?? false) === false
                && strcasecmp((string) ($repo['owner']['login'] ?? $login), $login) === 0
                && is_string($repo['pushed_at'] ?? null)
                && CarbonImmutable::parse($repo['pushed_at'])->greaterThanOrEqualTo($since),
        );
        $candidates = array_slice(array_values($candidates), 0, $limit);

        if ($candidates === []) {
            throw ImportFailed::because(ImportFailed::NO_REPOSITORIES, "GitHub user {$login} has no recent own repositories.");
        }

        $repositories = [];
        foreach ($candidates as $index => $repo) {
            $repositories[] = $this->readRepository($client, $login, $repo, withReadme: $client->authenticated() && $index < $this->readmeRepositories);
        }

        return [
            'username' => $login,
            'name' => is_string($user['name'] ?? null) ? $user['name'] : null,
            'profile_url' => (string) ($user['html_url'] ?? "https://github.com/{$login}"),
            'public_repos' => (int) ($user['public_repos'] ?? count($repositories)),
            'authenticated' => $client->authenticated(),
            'repositories' => array_map(function (array $repo): array {
                unset($repo['skills']);

                return $repo;
            }, $repositories),
            'skills' => $this->aggregate($repositories),
            'fetched_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $repo
     * @return array<string, mixed>
     */
    private function readRepository(GitHubClient $client, string $owner, array $repo, bool $withReadme): array
    {
        $name = (string) $repo['name'];
        /** @var array<string, array{signals: list<string>}> $skills */
        $skills = [];
        $add = function (?string $slug, string $signal) use (&$skills): void {
            if ($slug !== null && ! in_array($signal, $skills[$slug]['signals'] ?? [], true)) {
                $skills[$slug]['signals'][] = $signal;
            }
        };

        $languages = $client->languages($owner, $name);
        $total = array_sum($languages);
        foreach ($languages as $language => $bytes) {
            if ($total > 0 && $bytes / $total >= $this->minLanguageShare && ! in_array($language, $this->ignoredLanguages, true)) {
                $add($this->catalog->normalize($language)?->slug, 'language');
            }
        }

        // Topics are free tags: only known skills count.
        foreach ((array) ($repo['topics'] ?? []) as $topic) {
            $skill = is_string($topic) ? $this->catalog->normalize($topic) : null;
            $add($skill?->known === true ? $skill->slug : null, 'topic');
        }

        $manifests = [];
        foreach ($client->rootContents($owner, $name) as $entry) {
            if ($entry['type'] !== 'file') {
                continue;
            }

            $add($this->markerFiles[$entry['name']] ?? null, 'file');

            $ecosystem = $this->manifests[$entry['name']] ?? null;
            if ($ecosystem === null || $entry['size'] > $this->maxManifestBytes) {
                continue;
            }

            $contents = $client->file($owner, $name, $entry['name']);
            if ($contents === null) {
                continue;
            }

            $manifests[] = $entry['name'];
            foreach ($this->manifestParser->packages($entry['name'], $contents) as $package) {
                $add($this->catalog->forPackage($ecosystem, $package), 'dependency');
            }
        }

        $readme = $withReadme ? $client->readme($owner, $name) : null;

        return [
            'name' => $name,
            'url' => (string) ($repo['html_url'] ?? "https://github.com/{$owner}/{$name}"),
            'description' => is_string($repo['description'] ?? null) ? mb_substr($repo['description'], 0, 300) : null,
            'languages' => $languages,
            'topics' => array_values(array_filter((array) ($repo['topics'] ?? []), 'is_string')),
            'manifests' => $manifests,
            'stars' => (int) ($repo['stargazers_count'] ?? 0),
            'archived' => (bool) ($repo['archived'] ?? false),
            'created_at' => (string) ($repo['created_at'] ?? $repo['pushed_at']),
            'pushed_at' => (string) $repo['pushed_at'],
            'readme_excerpt' => $readme !== null ? $this->excerpt($readme) : null,
            'skills' => $skills,
        ];
    }

    /**
     * One evidence entry per skill across repositories.
     *
     * @param  list<array<string, mixed>>  $repositories
     * @return list<array<string, mixed>>
     */
    private function aggregate(array $repositories): array
    {
        $currentYear = CarbonImmutable::now()->year;
        $bySkill = [];

        foreach ($repositories as $repo) {
            /** @var array<string, array{signals: list<string>}> $skills */
            $skills = $repo['skills'];
            $pushed = CarbonImmutable::parse((string) $repo['pushed_at']);
            $created = CarbonImmutable::parse((string) $repo['created_at']);

            foreach ($skills as $slug => $found) {
                $entry = $bySkill[$slug] ?? ['repos' => [], 'signals' => [], 'first' => $created, 'last' => $pushed];
                $entry['repos'][] = (string) $repo['name'];
                $entry['signals'] = array_values(array_unique([...$entry['signals'], ...$found['signals']]));
                $entry['first'] = $created->lessThan($entry['first']) ? $created : $entry['first'];
                $entry['last'] = $pushed->greaterThan($entry['last']) ? $pushed : $entry['last'];
                $bySkill[$slug] = $entry;
            }
        }

        $skills = [];
        foreach ($bySkill as $slug => $entry) {
            $lastUsed = $entry['last']->year;
            $years = round(max(0.5, $entry['first']->diffInMonths($entry['last']) / 12) * 2) / 2;

            $skills[] = [
                'skill' => $slug,
                'name' => $this->catalog->label($slug, (string) $slug),
                'level' => EvidenceLevel::fromRepositories(count($entry['repos']), $lastUsed, $currentYear),
                'years' => min(15.0, $years),
                'last_used_year' => $lastUsed,
                'evidence' => [
                    'repository_count' => count($entry['repos']),
                    'repositories' => array_slice($entry['repos'], 0, self::MAX_SAMPLE_REPOSITORIES),
                    'signals' => $entry['signals'],
                ],
            ];
        }

        usort($skills, fn (array $a, array $b): int => [$b['evidence']['repository_count'], $a['name']] <=> [$a['evidence']['repository_count'], $b['name']]);

        return $skills;
    }

    private function excerpt(string $readme): string
    {
        // Drop images, badges, HTML and link targets: only the prose is useful.
        $text = (string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/<[^>]+>/', '/\[([^\]]*)\]\([^)]*\)/', '/[ \t]+/'], ['', '', '$1', ' '], $readme);
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", $text));

        return mb_substr($text, 0, $this->readmeMaxChars);
    }
}
