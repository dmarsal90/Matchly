<?php

declare(strict_types=1);

use App\Modules\AI\Application\CostCalculator;
use App\Modules\AI\Domain\Data\AiResult;
use App\Modules\AI\Infrastructure\Schema\JsonSchemaValidator;

it('decodes JSON objects from model text', function (string $text, ?array $expected): void {
    expect(AiResult::decode($text))->toBe($expected);
})->with([
    'plain' => ['{"a":1}', ['a' => 1]],
    'fenced' => ["```json\n{\"a\":1}\n```", ['a' => 1]],
    'with prose' => ['Sure! Here it is: {"a": {"b": 2}} Hope it helps.', ['a' => ['b' => 2]]],
    'a list is not an object' => ['[1,2]', null],
    'garbage' => ['no json here', null],
]);

it('prices calls per million tokens, cache reads at their own rate', function (): void {
    $calculator = new CostCalculator(['claude-haiku-5-5' => ['input' => 1.0, 'output' => 5.0, 'cache_read' => 0.1, 'cache_write' => 1.25]]);

    expect($calculator->cost('claude-haiku-5-5', new AiResult([], inputTokens: 1000, outputTokens: 200, cacheReadTokens: 5000, cacheWriteTokens: 0)))->toBe(0.0025)
        ->and($calculator->cost('llama:free', new AiResult([], inputTokens: 100000)))->toBe(0.0)
        ->and($calculator->isFree('llama:free'))->toBeTrue()
        ->and($calculator->isFree('claude-haiku-5-5'))->toBeFalse();
});

it('validates answers against the task schemas', function (): void {
    $schema = json_decode((string) file_get_contents(__DIR__.'/../../../app/Modules/AI/Prompts/v1/summarize_github.schema.json'), true);
    $validator = new JsonSchemaValidator;

    expect($validator->validate(['summary' => 'x', 'technologies' => ['PHP'], 'project_types' => [], 'highlights' => []], $schema))->toBeNull()
        ->and($validator->validate(['summary' => 'x', 'technologies' => 'PHP', 'project_types' => [], 'highlights' => []], $schema))->toBeString()
        ->and($validator->validate(['summary' => 'x'], $schema))->toBeString()
        ->and($validator->validate(['summary' => 'x', 'technologies' => [], 'project_types' => [], 'highlights' => [], 'extra' => 1], $schema))->toBeString();
});

it('ships valid prompt and schema files for every task', function (): void {
    foreach (App\Modules\AI\Domain\Enums\AiTask::cases() as $task) {
        $base = __DIR__."/../../../app/Modules/AI/Prompts/v1/{$task->value}";

        expect(trim((string) file_get_contents("{$base}.md")))->not->toBe('')
            ->and(json_decode((string) file_get_contents("{$base}.schema.json"), true))->toBeArray()->toHaveKey('type', 'object');
    }
});
