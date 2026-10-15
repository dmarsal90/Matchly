<?php

declare(strict_types=1);

use App\Modules\AI\Application\AiGateway;
use App\Modules\AI\Domain\Enums\AiTask;
use App\Modules\AI\Domain\Exceptions\AiUnavailable;
use App\Modules\AI\Infrastructure\Models\AiUsage;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const SUMMARY = ['summary' => 'Builds Laravel apps.', 'technologies' => ['Laravel'], 'project_types' => ['web application'], 'highlights' => []];

beforeEach(function (): void {
    config([
        'ai.enabled' => true,
        'ai.retry_delay_ms' => 0,
        'ai.retries' => 1,
        'ai.fallback_order' => ['anthropic', 'groq'],
        'ai.providers.anthropic.api_key' => 'server-anthropic',
        'ai.providers.groq.api_key' => 'server-groq',
        'ai.providers.gemini.api_key' => null,
        'ai.providers.ollama.base_url' => null,
        'ai.budget.user_monthly_usd' => 1.0,
        'ai.budget.system_monthly_usd' => 1.0,
    ]);
});

function anthropicAnswer(array $input, int $inputTokens = 1000, int $outputTokens = 100): array
{
    return [
        'content' => [['type' => 'tool_use', 'name' => 'submit_result', 'input' => $input]],
        'usage' => ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens, 'cache_read_input_tokens' => 2000, 'cache_creation_input_tokens' => 0],
    ];
}

function openAiAnswer(string $content): array
{
    return ['choices' => [['message' => ['content' => $content]]], 'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 80]];
}

function gateway(): AiGateway
{
    return app(AiGateway::class);
}

it('calls Anthropic with a forced tool, a cached system prompt and the task schema', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(SUMMARY))]);
    $user = User::factory()->create();

    $response = gateway()->run(AiTask::SummarizeGithub, '[{"name":"shop"}]', $user);

    expect($response->data)->toBe(SUMMARY)
        ->and($response->provider)->toBe('anthropic')
        ->and($response->model)->toBe(config('ai.providers.anthropic.models.fast'));

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'server-anthropic')
            && $body['tool_choice'] === ['type' => 'tool', 'name' => 'submit_result']
            && $body['system'][0]['cache_control'] === ['type' => 'ephemeral']
            && ! isset($body['tools'][0]['input_schema']['$schema'])
            && $body['tools'][0]['input_schema']['required'] === ['summary', 'technologies', 'project_types', 'highlights'];
    });

    $usage = AiUsage::query()->sole();
    expect($usage->user_id)->toBe($user->id)
        ->and($usage->task)->toBe('summarize_github')
        ->and($usage->key_owner)->toBe('server')
        ->and($usage->success)->toBeTrue()
        ->and($usage->cache_read_tokens)->toBe(2000)
        // 1000 × $1 + 100 × $5 + 2000 × $0.10 per million.
        ->and($usage->cost_usd)->toBe(0.0017);
});

it('falls back to the next provider when one keeps failing', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529),
        'api.groq.com/*' => Http::response(openAiAnswer('```json'."\n".json_encode(SUMMARY)."\n```")),
    ]);

    $response = gateway()->run(AiTask::SummarizeGithub, 'input', User::factory()->create());

    expect($response->provider)->toBe('groq')
        // Retried once on Anthropic (retryable), then Groq.
        ->and(AiUsage::query()->where('provider', 'anthropic')->where('success', false)->count())->toBe(2)
        ->and(AiUsage::query()->where('provider', 'groq')->where('success', true)->count())->toBe(1);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'groq.com/openai/v1/chat/completions')
        && $request->hasHeader('Authorization', 'Bearer server-groq')
        && $request->data()['response_format'] === ['type' => 'json_object']
        && str_contains($request->data()['messages'][0]['content'], 'JSON Schema'));
});

it('does not retry errors that cannot succeed', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'invalid key'], 401),
        'api.groq.com/*' => Http::response(openAiAnswer((string) json_encode(SUMMARY))),
    ]);

    gateway()->run(AiTask::SummarizeGithub, 'input', User::factory()->create());

    expect(AiUsage::query()->where('provider', 'anthropic')->count())->toBe(1);
});

it('rejects answers that break the schema, retrying once before moving on', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(anthropicAnswer(['summary' => 'missing fields'])),
        'api.groq.com/*' => Http::response(openAiAnswer('not json at all')),
    ]);

    try {
        gateway()->run(AiTask::SummarizeGithub, 'input', User::factory()->create());
        test()->fail('Expected the call to fail.');
    } catch (AiUnavailable $exception) {
        expect($exception->reason)->toBe(AiUnavailable::FAILED);
    }

    expect(AiUsage::query()->where('success', false)->count())->toBe(4);
});

it('puts the user\'s own key first and does not charge it to the budget', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(SUMMARY))]);
    $user = User::factory()->create();
    $user->forceFill(['anthropic_api_key' => 'sk-ant-user-key-123456789'])->save();

    gateway()->run(AiTask::SummarizeGithub, 'input', $user);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'sk-ant-user-key-123456789'));
    expect(AiUsage::query()->sole()->key_owner)->toBe('user');
});

it('stops paid server calls once the monthly budget is spent', function (): void {
    Http::fake();
    $user = User::factory()->create();
    AiUsage::query()->create(['user_id' => $user->id, 'task' => 'structure_cv', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 1.5, 'success' => true]);
    // Someone else's spending does not count.
    AiUsage::query()->create(['user_id' => null, 'task' => 'extract_job_requirements', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 0.1, 'success' => true]);

    expect(fn () => gateway()->run(AiTask::SummarizeGithub, 'input', $user))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailable::BUDGET_EXCEEDED));
    Http::assertNothingSent();
});

it('keeps free models available over budget', function (): void {
    config(['ai.providers.groq.models.fast' => 'some-model:free']);
    Http::fake(['api.groq.com/*' => Http::response(openAiAnswer((string) json_encode(SUMMARY)))]);
    $user = User::factory()->create();
    AiUsage::query()->create(['user_id' => $user->id, 'task' => 'structure_cv', 'provider' => 'anthropic', 'model' => 'x', 'key_owner' => 'server', 'cost_usd' => 5, 'success' => true]);

    expect(gateway()->run(AiTask::SummarizeGithub, 'input', $user)->provider)->toBe('groq');
});

it('charges system tasks to the shared system budget, never to a user', function (): void {
    $answer = json_decode((string) file_get_contents(base_path('tests/Fixtures/ai/job_requirements.json')), true);
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer($answer))]);

    gateway()->run(AiTask::ExtractJobRequirements, 'posting', User::factory()->create());

    expect(AiUsage::query()->sole()->user_id)->toBeNull();
});

it('limits calls per user', function (): void {
    config(['ai.user_rate_limit_per_hour' => 1]);
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(SUMMARY))]);
    $user = User::factory()->create();

    gateway()->run(AiTask::SummarizeGithub, 'input', $user);

    expect(fn () => gateway()->run(AiTask::SummarizeGithub, 'input', $user))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailable::RATE_LIMITED));
});

it('reports when no provider is configured or AI is off', function (): void {
    config(['ai.providers.anthropic.api_key' => null, 'ai.providers.groq.api_key' => null]);
    expect(fn () => gateway()->run(AiTask::SummarizeGithub, 'input'))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailable::NO_PROVIDER))
        ->and(gateway()->available())->toBeFalse();

    config(['ai.enabled' => false]);
    expect(fn () => gateway()->run(AiTask::SummarizeGithub, 'input'))
        ->toThrow(fn (AiUnavailable $exception) => expect($exception->reason)->toBe(AiUnavailable::DISABLED));
});

it('speaks the Gemini API with the key in a header', function (): void {
    config(['ai.fallback_order' => ['gemini'], 'ai.providers.gemini.api_key' => 'gemini-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode(SUMMARY)]]]]],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
    ])]);

    expect(gateway()->run(AiTask::SummarizeGithub, 'input')->provider)->toBe('gemini');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-goog-api-key', 'gemini-key')
        && ! str_contains($request->url(), 'gemini-key')
        && $request->data()['generationConfig']['responseMimeType'] === 'application/json');
});

it('talks to a local Ollama without a key', function (): void {
    config(['ai.fallback_order' => ['ollama'], 'ai.providers.ollama.base_url' => 'http://ollama.test:11434/v1']);
    Http::fake(['ollama.test:11434/*' => Http::response(openAiAnswer((string) json_encode(SUMMARY)))]);

    expect(gateway()->run(AiTask::SummarizeGithub, 'input')->provider)->toBe('ollama');
    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
});

it('cuts oversized input', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(anthropicAnswer(SUMMARY))]);

    gateway()->run(AiTask::SummarizeGithub, str_repeat('a', 50000), User::factory()->create());

    Http::assertSent(fn (Request $request): bool => mb_strlen($request->data()['messages'][0]['content']) <= AiTask::SummarizeGithub->maxInputChars() + 1);
});
