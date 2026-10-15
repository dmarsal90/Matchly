<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResult;
use Illuminate\Http\Client\PendingRequest;

/**
 * Anthropic Messages API. The answer is forced through a tool whose input
 * schema is the task's JSON schema (native structured output), and the
 * system prompt is marked for prompt caching: it is identical across calls
 * of a task, so repeated calls read it from cache at a fraction of the price.
 */
final class AnthropicProvider extends HttpAiProvider
{
    private const string TOOL = 'submit_result';

    public function complete(AiCompletion $completion): AiResult
    {
        $json = $this->post($this->url('/v1/messages'), [
            'model' => $completion->model,
            'max_tokens' => $completion->maxOutputTokens,
            'system' => [[
                'type' => 'text',
                'text' => $completion->system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [['role' => 'user', 'content' => $completion->input]],
            'tools' => [[
                'name' => self::TOOL,
                'description' => 'Submit the result. Always call this tool exactly once.',
                // The tool schema is the task schema minus its meta keyword.
                'input_schema' => array_diff_key($completion->schema, ['$schema' => true]),
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
        ], fn (PendingRequest $request): PendingRequest => $request->withHeaders([
            'x-api-key' => $this->key($completion),
            'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
        ]));

        $data = null;
        foreach ((array) ($json['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                $data = $block['input'];
            }
        }

        $usage = (array) ($json['usage'] ?? []);

        return new AiResult(
            data: $data,
            inputTokens: (int) ($usage['input_tokens'] ?? 0),
            outputTokens: (int) ($usage['output_tokens'] ?? 0),
            cacheReadTokens: (int) ($usage['cache_read_input_tokens'] ?? 0),
            cacheWriteTokens: (int) ($usage['cache_creation_input_tokens'] ?? 0),
        );
    }
}
