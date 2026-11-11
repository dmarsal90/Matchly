<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Services;

/**
 * The part of robots.txt that matters to a polite crawler: the rules of the
 * group that names our agent, or of "*" when none does. The longest matching
 * rule wins and Allow beats Disallow when they tie.
 */
final readonly class RobotsTxt
{
    // The file is written by the site we are checking: bound what it can make us compile and match.
    private const int MAX_RULES = 500;

    private const int MAX_PATH = 200;

    private const int MAX_WILDCARDS = 5;

    /**
     * @param  list<array{allow: bool, path: string}>  $rules
     */
    private function __construct(private array $rules) {}

    public static function parse(string $body, string $agent): self
    {
        $agent = strtolower($agent);
        /** @var array<string, list<array{allow: bool, path: string}>> $groups */
        $groups = [];
        $current = [];
        $collecting = false;
        $total = 0;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map(trim(...), explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Consecutive User-agent lines share one group.
                if ($collecting) {
                    $current = [];
                }
                $collecting = false;
                $current[] = strtolower($value);
                $groups += array_fill_keys($current, []);

                continue;
            }

            if (in_array($field, ['allow', 'disallow'], true) && $current !== []) {
                $collecting = true;
                if ($total >= self::MAX_RULES || strlen($value) > self::MAX_PATH || substr_count($value, '*') > self::MAX_WILDCARDS) {
                    continue;
                }
                $total++;
                foreach ($current as $name) {
                    $groups[$name][] = ['allow' => $field === 'allow', 'path' => $value];
                }
            }
        }

        $own = array_filter($groups, fn (array $_, string $name): bool => $name !== '*' && $name !== '' && str_contains($agent, $name), ARRAY_FILTER_USE_BOTH);

        return new self($own !== [] ? array_merge(...array_values($own)) : ($groups['*'] ?? []));
    }

    public function allows(string $path): bool
    {
        $best = null;

        foreach ($this->rules as $rule) {
            // An empty Disallow allows everything.
            if ($rule['path'] === '' || ! $this->matches($rule['path'], $path)) {
                continue;
            }
            if ($best === null || strlen($rule['path']) > strlen($best['path']) || (strlen($rule['path']) === strlen($best['path']) && $rule['allow'])) {
                $best = $rule;
            }
        }

        return $best === null || $best['allow'];
    }

    private function matches(string $pattern, string $path): bool
    {
        $regex = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';

        return preg_match($regex, $path) === 1;
    }
}
