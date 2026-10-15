<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResult;
use Illuminate\Http\Client\PendingRequest;

/** Google Gemini generateContent with JSON output. The key goes in a header, never in the URL. */
final class GeminiProvider extends HttpAiProvider
{
    public function complete(AiCompletion $completion): AiResult
    {
        $json = $this->post($this->url('/models/'.rawurlencode($completion->model).':generateContent'), [
            'systemInstruction' => ['parts' => [['text' => $this->systemWithSchema($completion)]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $completion->input]]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'maxOutputTokens' => $completion->maxOutputTokens,
                'temperature' => 0.2,
            ],
        ], fn (PendingRequest $request): PendingRequest => $request->withHeaders(['x-goog-api-key' => $this->key($completion)]));

        $text = '';
        foreach ((array) ($json['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= is_array($part) ? (string) ($part['text'] ?? '') : '';
        }

        $usage = (array) ($json['usageMetadata'] ?? []);

        return new AiResult(
            data: AiResult::decode($text),
            inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            cacheReadTokens: (int) ($usage['cachedContentTokenCount'] ?? 0),
        );
    }
}
