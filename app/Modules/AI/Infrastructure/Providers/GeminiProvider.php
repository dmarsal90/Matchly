<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Providers;

use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResult;
use App\Modules\AI\Domain\Exceptions\AiProviderFailed;
use Illuminate\Http\Client\PendingRequest;

/** Google Gemini generateContent with JSON output. The key goes in a header, never in the URL. */
final class GeminiProvider extends HttpAiProvider
{
    /**
     * Every Gemini model has its own free quota and its own busy moments, so a
     * rate limit or an overload moves on to the next model of the chain.
     */
    public function complete(AiCompletion $completion): AiResult
    {
        $models = array_values(array_unique([$completion->model, ...($this->config['fallback_models'] ?? [])]));
        $failure = new AiProviderFailed('gemini has no model configured.');

        foreach ($models as $model) {
            try {
                return $this->completeWith($completion, $model);
            } catch (AiProviderFailed $exception) {
                $failure = $exception;
                if (! $exception->retryable) {
                    throw $exception;
                }
            }
        }

        throw $failure;
    }

    private function completeWith(AiCompletion $completion, string $model): AiResult
    {
        $generation = [
            'responseMimeType' => 'application/json',
            'maxOutputTokens' => $completion->maxOutputTokens,
            'temperature' => 0.2,
        ];
        // Gemini 3 "thinks" by default and the thinking eats the output budget: keep it light.
        if (! str_starts_with($model, 'gemini-2')) {
            $generation['thinkingConfig'] = ['thinkingLevel' => 'low'];
        }

        $json = $this->post($this->url('/models/'.rawurlencode($model).':generateContent'), [
            'systemInstruction' => ['parts' => [['text' => $this->systemWithSchema($completion)]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $completion->input]]]],
            'generationConfig' => $generation,
        ], fn (PendingRequest $request): PendingRequest => $request->withHeaders(['x-goog-api-key' => $this->key($completion)]));

        $text = '';
        foreach ((array) ($json['candidates'][0]['content']['parts'] ?? []) as $part) {
            $text .= is_array($part) && empty($part['thought']) ? (string) ($part['text'] ?? '') : '';
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
