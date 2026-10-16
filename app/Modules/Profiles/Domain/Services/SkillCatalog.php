<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Domain\Services;

use App\Modules\Profiles\Domain\Data\NormalizedSkill;
use Illuminate\Support\Str;

/**
 * Canonical skills and their synonyms (config/skills.php). Turns any
 * spelling into one slug ("Vue.js", "VueJS", "vue 3" → "vue"), finds known
 * skills mentioned in free text and maps manifest packages to skills.
 */
final class SkillCatalog
{
    private const int MAX_NAME_LENGTH = 60;

    /** @var array<string, string> lookup key => slug */
    private array $index = [];

    /** @var array<string, string>|null slug => detection regex */
    private ?array $patterns = null;

    /**
     * @param  array<string, array{label: string, type?: string, aliases?: list<string>, detect?: list<string>, roles?: list<string>}>  $catalog
     * @param  array<string, array<string, string>>  $packages  ecosystem => package => slug
     */
    public function __construct(
        private readonly array $catalog,
        private readonly array $packages = [],
    ) {
        foreach ($catalog as $slug => $entry) {
            foreach ([$slug, $entry['label'], ...$entry['aliases'] ?? []] as $name) {
                foreach (self::keys($name) as $key) {
                    $this->index[$key] ??= $slug;
                }
            }
        }
    }

    public function normalize(string $name): ?NormalizedSkill
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return null;
        }

        foreach (self::keys($name) as $key) {
            if (isset($this->index[$key])) {
                return $this->known($this->index[$key]);
            }
        }

        $slug = Str::slug(str_replace(['#', '+'], ['sharp', 'plus'], $name));
        if ($slug === '') {
            return null;
        }

        return new NormalizedSkill(mb_substr($slug, 0, 64), $name, known: false);
    }

    public function find(string $slug): ?NormalizedSkill
    {
        return isset($this->catalog[$slug]) ? $this->known($slug) : null;
    }

    /** The catalog type of a skill (language, framework...), null when it is not in the catalog. */
    public function type(string $slug): ?string
    {
        return isset($this->catalog[$slug]['type']) ? (string) $this->catalog[$slug]['type'] : null;
    }

    public function label(string $slug, string $fallback): string
    {
        return $this->catalog[$slug]['label'] ?? $fallback;
    }

    /**
     * @return list<string>
     */
    public function rolesFor(string $slug): array
    {
        return $this->catalog[$slug]['roles'] ?? [];
    }

    /**
     * Display names of every known skill, for autocompletion.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        $labels = array_values(array_map(fn (array $entry): string => $entry['label'], $this->catalog));
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /**
     * Known skills mentioned in a text, with how often each appears. Only
     * catalog entries are searched for: free text is too noisy to invent
     * skills from.
     *
     * @return array<string, int> slug => mentions
     */
    public function detectIn(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $found = [];
        foreach ($this->patterns() as $slug => $pattern) {
            $count = preg_match_all($pattern, $text);
            if ($count > 0) {
                $found[$slug] = $count;
            }
        }

        arsort($found);

        return $found;
    }

    /** Skill a dependency points to ("laravel/framework" → laravel), if any. */
    public function forPackage(string $ecosystem, string $package): ?string
    {
        $map = $this->packages[$ecosystem] ?? [];
        $package = strtolower(trim($package));

        if (isset($map[$package])) {
            return $map[$package];
        }

        foreach ($map as $pattern => $slug) {
            if (str_ends_with($pattern, '*') && str_starts_with($package, substr($pattern, 0, -1))) {
                return $slug;
            }
        }

        return null;
    }

    private function known(string $slug): NormalizedSkill
    {
        return new NormalizedSkill($slug, $this->catalog[$slug]['label'], known: true);
    }

    /**
     * Lookup keys for a name: as written, without a version suffix and
     * squashed (no dots, spaces or hyphens), so spelling variants meet.
     *
     * @return list<string>
     */
    private static function keys(string $name): array
    {
        $key = mb_strtolower(trim($name));
        $unversioned = trim((string) preg_replace('/[\s-]*v?\d+(\.\d+)*(\.x)?$/', '', $key));

        $keys = [$key];
        foreach ([$key, $unversioned] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $keys[] = $candidate;
            $keys[] = str_replace(['.', ' ', '-', '_'], '', $candidate);
        }

        return array_values(array_unique(array_filter($keys, fn (string $value): bool => $value !== '')));
    }

    /**
     * @return array<string, string>
     */
    private function patterns(): array
    {
        if ($this->patterns !== null) {
            return $this->patterns;
        }

        $this->patterns = [];
        foreach ($this->catalog as $slug => $entry) {
            $terms = $entry['detect'] ?? [$entry['label'], ...$entry['aliases'] ?? []];
            // One- and two-letter terms ("C", "TS", "wp") are noise in prose.
            $terms = array_filter($terms, fn (string $term): bool => mb_strlen($term) > 2 || preg_match('/[#+]/', $term) === 1);
            if ($terms === []) {
                continue;
            }

            $alternatives = implode('|', array_map(fn (string $term): string => preg_quote($term, '/'), $terms));
            // Word-ish boundaries that also respect symbols: "C++" must not match inside "C++17x", "Java" not inside "JavaScript".
            $this->patterns[$slug] = '/(?<![\p{L}\p{N}_+#.\/-])(?:'.$alternatives.')(?![\p{L}\p{N}_+#]|\.[\p{L}\p{N}])/iu';
        }

        return $this->patterns;
    }
}
