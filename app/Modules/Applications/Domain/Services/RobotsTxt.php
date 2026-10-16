<?php

declare(strict_types=1);

namespace App\Modules\Applications\Domain\Services;

/**
 * The part of robots.txt that matters to a polite crawler: the rules of the
 * group that names our agent, or of "*" when none does. The longest matching
 * rule wins and Allow beats Disallow when they tie.
 */
final readonly class RobotsTxt
{
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
