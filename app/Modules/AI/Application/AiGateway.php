<?php

declare(strict_types=1);

namespace App\Modules\AI\Application;

use App\Modules\AI\Domain\Contracts\AiProvider;
use App\Modules\AI\Domain\Data\AiCompletion;
use App\Modules\AI\Domain\Data\AiResponse;
use App\Modules\AI\Domain\Data\AiResult;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiProviderFailed;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\AI\Infrastructure\Models\AiUsage;
use App\Modules\AI\Infrastructure\Schema\JsonSchemaValidator;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * The single entry point to AI. For each task it loads the versioned prompt,
 * walks the providers in fallback order (the user's own key first when they
 * have one), retries transient failures, validates the answer against the
 * task's JSON schema, enforces rate limits and budgets, and logs every call
 * with its tokens and cost.
 */
final class AiGateway
{
    /**
     * @param  array<string, AiProvider>  $providers  name => adapter
     * @param  list<string>  $fallbackOrder
     */
    public function __construct(
        private readonly array $providers,
        private readonly array $fallbackOrder,
        private readonly PromptRepository $prompts,
        private readonly JsonSchemaValidator $validator,
        private readonly CostCalculator $costs,
        private readonly BudgetGuard $budget,
        private readonly RateLimiter $limiter,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
        private readonly int $retries,
        private readonly int $retryDelayMs,
        private readonly int $userRateLimitPerHour,
    ) {}

    /** Whether any provider could take a call for this user right now. */
    public function available(?User $user = null): bool
    {
        return $this->enabled && $this->candidates($user) !== [];
    }

    /**
     * @throws AiUnavailable
     */
    public function run(AiTask $task, string $input, ?User $user = null): AiResponse
    {
        if (! $this->enabled) {
            throw new AiUnavailable(AiUnavailable::DISABLED);
        }

        $userId = $task->isSystem() ? null : $user?->id;
        if ($userId !== null && ! $this->limiter->attempt("ai:user:{$userId}", $this->userRateLimitPerHour, fn (): bool => true, 3600)) {
            throw new AiUnavailable(AiUnavailable::RATE_LIMITED);
        }

        $candidates = $this->candidates($task->isSystem() ? null : $user);
        if ($candidates === []) {
            throw new AiUnavailable(AiUnavailable::NO_PROVIDER);
        }

        $prompt = $this->prompts->get($task);
        $input = Str::limit($input, $task->maxInputChars(), '…');
        $budgetExceeded = $this->budget->exceeded($userId);
        $lastError = '';

        foreach ($candidates as [$provider, $userKey]) {
            $model = $provider->model($task->tier());
            $keyOwner = $userKey !== null ? AiUsage::USER_KEY : AiUsage::SERVER_KEY;

            // Over budget, only free models (and the user's own key) remain.
            if ($keyOwner === AiUsage::SERVER_KEY && $budgetExceeded && ! $this->costs->isFree($model)) {
                $lastError = AiUnavailable::BUDGET_EXCEEDED;

                continue;
            }

            $completion = new AiCompletion($prompt['system'], $input, $prompt['schema'], $model, $task->maxOutputTokens(), $userKey);

            for ($attempt = 0; $attempt <= $this->retries; $attempt++) {
                $started = hrtime(true);

                try {
                    $result = $provider->complete($completion);
                } catch (AiProviderFailed $exception) {
                    $this->log($task, $userId, $provider, $model, $keyOwner, new AiResult(null), false, $exception->getMessage(), $started);
                    // The most preferred provider's failure is the one worth showing.
                    $lastError = $lastError === '' ? $exception->getMessage() : $lastError;

                    if (! $exception->retryable) {
                        break;
                    }
                    $this->pause($attempt);

                    continue;
                }

                $error = $result->data === null ? 'Answer is not a JSON object.' : $this->validator->validate($result->data, $prompt['schema']);
                $cost = $this->log($task, $userId, $provider, $model, $keyOwner, $result, $error === null, $error, $started);

                if ($error === null && $result->data !== null) {
                    return new AiResponse($result->data, $provider->name(), $model, $cost);
                }

                // A malformed answer gets one more try, then the next provider.
                $lastError = (string) $error;
                if ($attempt >= 1) {
                    break;
                }
            }
        }

        $this->logger->warning('AI task failed on every provider', ['task' => $task->value, 'error' => $lastError]);

        throw new AiUnavailable($lastError === AiUnavailable::BUDGET_EXCEEDED ? AiUnavailable::BUDGET_EXCEEDED : AiUnavailable::FAILED, $lastError);
    }

    /**
     * Providers able to take the call, in order; the user's own key goes first.
     *
     * @return list<array{0: AiProvider, 1: string|null}>
     */
    private function candidates(?User $user): array
    {
        $userKey = $user?->anthropic_api_key;
        $own = [];
        $server = [];

        foreach ($this->fallbackOrder as $name) {
            $provider = $this->providers[$name] ?? null;
            if ($provider === null) {
                continue;
            }

            if ($provider->acceptsUserKeys() && filled($userKey)) {
                $own[] = [$provider, (string) $userKey];
            }
            if ($provider->available()) {
                $server[] = [$provider, null];
            }
        }

        return [...$own, ...$server];
    }

    private function log(AiTask $task, ?int $userId, AiProvider $provider, string $model, string $keyOwner, AiResult $result, bool $success, ?string $error, int|float $started): float
    {
        $cost = $this->costs->cost($model, $result);

        AiUsage::query()->create([
            'user_id' => $userId,
            'task' => $task->value,
            'provider' => $provider->name(),
            'model' => $model,
            'key_owner' => $keyOwner,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cache_read_tokens' => $result->cacheReadTokens,
            'cache_write_tokens' => $result->cacheWriteTokens,
            'cost_usd' => $cost,
            'success' => $success,
            'error' => $error !== null ? Str::limit($error, 250) : null,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        return $cost;
    }

    private function pause(int $attempt): void
    {
        if ($this->retryDelayMs > 0) {
            usleep($this->retryDelayMs * 1000 * (2 ** $attempt));
        }
    }
}
