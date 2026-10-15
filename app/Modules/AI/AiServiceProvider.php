<?php

declare(strict_types=1);

namespace App\Modules\AI;

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Application\BudgetGuard;
use App\Modules\AI\Application\CostCalculator;
use App\Modules\AI\Application\Privacy\AiUsageDataContributor;
use App\Modules\AI\Application\PromptRepository;
use App\Modules\AI\Domain\Contracts\AiProvider;
use App\Modules\AI\Infrastructure\Providers\AnthropicProvider;
use App\Modules\AI\Infrastructure\Providers\GeminiProvider;
use App\Modules\AI\Infrastructure\Providers\OpenAiCompatibleProvider;
use App\Modules\AI\Infrastructure\Schema\JsonSchemaValidator;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class AiServiceProvider extends ServiceProvider
{
    /** Adapter per driver; adding a vendor means one class and one config entry. */
    private const array DRIVERS = [
        'anthropic' => AnthropicProvider::class,
        'gemini' => GeminiProvider::class,
        'openai' => OpenAiCompatibleProvider::class,
    ];

    public function register(): void
    {
        $this->app->tag([AiUsageDataContributor::class], 'personal-data');

        $this->app->singleton(PromptRepository::class, fn (): PromptRepository => new PromptRepository(
            app_path('Modules/AI/Prompts'),
            (string) config('ai.prompts_version', 'v1'),
        ));

        $this->app->bind(CostCalculator::class, fn (): CostCalculator => new CostCalculator((array) config('ai.pricing', [])));

        $this->app->bind(BudgetGuard::class, fn (): BudgetGuard => new BudgetGuard(
            (float) config('ai.budget.user_monthly_usd'),
            (float) config('ai.budget.system_monthly_usd'),
        ));

        $this->app->bind(AiGateway::class, fn (Application $app): AiGateway => new AiGateway(
            providers: $this->providers($app),
            fallbackOrder: array_values((array) config('ai.fallback_order', [])),
            prompts: $app->make(PromptRepository::class),
            validator: new JsonSchemaValidator,
            costs: $app->make(CostCalculator::class),
            budget: $app->make(BudgetGuard::class),
            limiter: $app->make(RateLimiter::class),
            logger: $app->make(LoggerInterface::class),
            enabled: (bool) config('ai.enabled'),
            retries: (int) config('ai.retries'),
            retryDelayMs: (int) config('ai.retry_delay_ms'),
            userRateLimitPerHour: (int) config('ai.user_rate_limit_per_hour'),
        ));
    }

    /**
     * @return array<string, AiProvider>
     */
    private function providers(Application $app): array
    {
        $providers = [];
        foreach ((array) config('ai.providers', []) as $name => $config) {
            $class = self::DRIVERS[$config['driver'] ?? ''] ?? null;
            if ($class !== null) {
                $providers[(string) $name] = new $class($app->make(Http::class), (string) $name, $config, (int) config('ai.timeout'));
            }
        }

        return $providers;
    }
}
