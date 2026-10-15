<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResult;
use Illuminate\Http\Client\PendingRequest;

/**
 * OpenAI-style chat completions with JSON mode: Groq, OpenRouter, Mistral
 * and a local Ollama all speak it.
 */
final class OpenAiCompatibleProvider extends HttpAiProvider
{
    public function complete(AiCompletion $completion): AiResult
    {
        $key = $this->key($completion);

        $json = $this->post($this->url('/chat/completions'), [
            'model' => $completion->model,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemWithSchema($completion)],
                ['role' => 'user', 'content' => $completion->input],
            ],
            'max_tokens' => $completion->maxOutputTokens,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ], fn (PendingRequest $request): PendingRequest => $key !== '' ? $request->withToken($key) : $request);

        $usage = (array) ($json['usage'] ?? []);

        return new AiResult(
            data: AiResult::decode((string) ($json['choices'][0]['message']['content'] ?? '')),
            inputTokens: (int) ($usage['prompt_tokens'] ?? 0),
            outputTokens: (int) ($usage['completion_tokens'] ?? 0),
            cacheReadTokens: (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
        );
    }
}
