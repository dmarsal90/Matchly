<?php

declare(strict_types=1);

namespace App\Modules\AI\Application;

use App\Modules\AI\Domain\Enums\AiTask;
use RuntimeException;

/**
 * Versioned prompts: Prompts/<version>/<task>.md (system prompt) and
 * <task>.schema.json (shape of the answer). Changing a prompt means a new
 * version folder, so results stay traceable to the prompt that made them.
 */
final class PromptRepository
{
    /** @var array<string, array{system: string, schema: array<string, mixed>}> */
    private array $loaded = [];

    public function __construct(private readonly string $directory, private readonly string $version) {}

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return array{system: string, schema: array<string, mixed>}
     */
    public function get(AiTask $task): array
    {
        return $this->loaded[$task->value] ??= $this->load($task);
    }

    /**
     * @return array{system: string, schema: array<string, mixed>}
     */
    private function load(AiTask $task): array
    {
        $base = "{$this->directory}/{$this->version}/{$task->value}";
        $system = @file_get_contents("{$base}.md");
        $schema = json_decode((string) @file_get_contents("{$base}.schema.json"), true);

        if ($system === false || ! is_array($schema)) {
            throw new RuntimeException("Prompt files for [{$task->value}] ({$this->version}) are missing or invalid.");
        }

        return ['system' => trim($system), 'schema' => $schema];
    }
}
