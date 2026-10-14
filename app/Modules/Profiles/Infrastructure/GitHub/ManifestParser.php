<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\GitHub;

/**
 * Lists the dependencies declared in a project manifest. Parsing is
 * tolerant and text-based (no TOML/XML/Gradle evaluation): the names are
 * only looked up in the package → skill map, so a stray extra name is
 * harmless while a manifest that fails to parse would lose real evidence.
 */
final class ManifestParser
{
    /**
     * @return list<string> lower-cased package names
     */
    public function packages(string $file, string $contents): array
    {
        $packages = match (strtolower($file)) {
            'composer.json' => $this->jsonKeys($contents, ['require', 'require-dev']),
            'package.json' => $this->jsonKeys($contents, ['dependencies', 'devDependencies', 'peerDependencies']),
            'requirements.txt' => $this->requirements($contents),
            'pyproject.toml', 'pipfile' => $this->pythonToml($contents),
            'gemfile' => $this->matches('/^\s*gem\s+[\'"]([A-Za-z0-9_.-]+)[\'"]/m', $contents),
            'go.mod' => $this->goModules($contents),
            'cargo.toml' => $this->tomlTableKeys($contents, '/^\[(?:dev-|build-)?dependencies\]$/'),
            'pom.xml' => [
                ...$this->matches('#<groupId>\s*([A-Za-z0-9_.-]+)\s*</groupId>#', $contents),
                ...$this->matches('#<artifactId>\s*([A-Za-z0-9_.-]+)\s*</artifactId>#', $contents),
            ],
            'build.gradle', 'build.gradle.kts' => [
                ...$this->matches('/[\'"]([A-Za-z0-9_.-]+):[A-Za-z0-9_.-]+(?::[^\'"]*)?[\'"]/', $contents),
                ...$this->matches('/\bid\s*\(?\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $contents),
            ],
            default => [],
        };

        return array_values(array_unique(array_map(
            fn (string $package): string => strtolower(trim($package)),
            array_filter($packages, fn (string $package): bool => $package !== ''),
        )));
    }

    /**
     * @param  list<string>  $sections
     * @return list<string>
     */
    private function jsonKeys(string $contents, array $sections): array
    {
        $data = json_decode($contents, true);
        if (! is_array($data)) {
            return [];
        }

        $keys = [];
        foreach ($sections as $section) {
            if (is_array($data[$section] ?? null)) {
                $keys = [...$keys, ...array_map('strval', array_keys($data[$section]))];
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function requirements(string $contents): array
    {
        $packages = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || str_starts_with($line, '-')) {
                continue;
            }
            if (preg_match('/^([A-Za-z0-9][A-Za-z0-9_.-]*)/', $line, $match) === 1) {
                $packages[] = str_replace('_', '-', $match[1]);
            }
        }

        return $packages;
    }

    /**
     * PEP 621 dependency strings ("fastapi>=0.110") plus Poetry/Pipfile tables.
     *
     * @return list<string>
     */
    private function pythonToml(string $contents): array
    {
        $fromStrings = $this->matches('/["\']([A-Za-z0-9][A-Za-z0-9_.-]*)\s*(?:\[[^\]]*\])?\s*(?:[<>=~!;][^"\']*)?["\']/', $contents);
        $fromTables = $this->tomlTableKeys($contents, '/^\[(?:tool\.poetry\.(?:group\.[^.]+\.)?(?:dev-)?dependencies|packages|dev-packages)\]$/');

        return array_map(fn (string $name): string => str_replace('_', '-', $name), [...$fromStrings, ...$fromTables]);
    }

    /**
     * @return list<string>
     */
    private function goModules(string $contents): array
    {
        $modules = $this->matches('/^\s*require\s+(\S+)\s+v/m', $contents);

        if (preg_match_all('/require\s*\((.*?)\)/s', $contents, $blocks) > 0) {
            foreach ($blocks[1] as $block) {
                $modules = [...$modules, ...$this->matches('/^\s*(\S+)\s+v/m', $block)];
            }
        }

        return $modules;
    }

    /**
     * Keys ("name = ...") of the TOML tables whose header matches.
     *
     * @return list<string>
     */
    private function tomlTableKeys(string $contents, string $headerPattern): array
    {
        $keys = [];
        $inside = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, '[')) {
                $inside = preg_match($headerPattern, $line) === 1;

                continue;
            }
            if ($inside && preg_match('/^([A-Za-z0-9][A-Za-z0-9_.-]*)\s*=/', $line, $match) === 1) {
                $keys[] = $match[1];
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private function matches(string $pattern, string $contents): array
    {
        return preg_match_all($pattern, $contents, $matches) > 0 ? $matches[1] : [];
    }
}
