<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Application\Actions;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Support\SourceText;

/**
 * An AI summary of public repositories (technologies, project types,
 * highlights) shown when the user reviews the GitHub import. Technologies
 * and repositories the input does not contain are dropped.
 */
final readonly class SummarizeGithubWithAi
{
    private const float MIN_SUMMARY_OVERLAP = 0.5;

    public function __construct(private AiGateway $ai) {}

    /**
     * @param  list<array<string, mixed>>  $repositories
     * @return array{summary: string|null, technologies: list<string>, project_types: list<string>, highlights: list<array{repository: string, note: string}>, provider: string}|null
     */
    public function handle(User $user, array $repositories): ?array
    {
        $input = (string) json_encode(array_map(fn (array $repo): array => [
            'name' => $repo['name'] ?? null,
            'description' => $repo['description'] ?? null,
            'languages' => $repo['languages'] ?? [],
            'topics' => $repo['topics'] ?? [],
            'manifests' => $repo['manifests'] ?? [],
            'readme_excerpt' => $repo['readme_excerpt'] ?? null,
        ], $repositories), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = $this->ai->run(AiTask::SummarizeGithub, $input, $user);
        } catch (AiUnavailable) {
            return null;
        }

        $source = new SourceText($input);
        $names = array_map(fn (array $repo): string => (string) ($repo['name'] ?? ''), $repositories);
        $data = $response->data;
        $summary = is_string($data['summary'] ?? null) && $source->overlap($data['summary']) >= self::MIN_SUMMARY_OVERLAP ? $data['summary'] : null;

        return [
            'summary' => $summary,
            'technologies' => array_values(array_filter((array) ($data['technologies'] ?? []), fn (mixed $name): bool => is_string($name) && $source->contains($name))),
            'project_types' => array_values(array_filter((array) ($data['project_types'] ?? []), 'is_string')),
            'highlights' => array_values(array_filter(
                (array) ($data['highlights'] ?? []),
                fn (mixed $item): bool => is_array($item) && in_array($item['repository'] ?? null, $names, true) && is_string($item['note'] ?? null),
            )),
            'provider' => $response->provider,
        ];
    }
}
