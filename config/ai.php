<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AI providers
|--------------------------------------------------------------------------
|
| Providers are interchangeable behind the AiProvider contract and tried in
| `fallback_order`; a provider without credentials is skipped. Each task
| picks a model tier: "fast" (cheap: extraction, summaries) or "quality"
| (letters, CV tailoring). Every call is logged with its tokens and cost.
|
*/

$list = fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value))));

return [

    'enabled' => (bool) env('AI_ENABLED', true),

    'fallback_order' => $list((string) env('AI_FALLBACK_ORDER', 'anthropic,gemini,groq,openrouter,mistral,ollama')),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    // Retries per provider on rate limits / 5xx before moving to the next one.
    'retries' => (int) env('AI_RETRIES', 2),
    'retry_delay_ms' => (int) env('AI_RETRY_DELAY_MS', 1000),

    // Calls per user per hour, across tasks.
    'user_rate_limit_per_hour' => (int) env('AI_USER_RATE_LIMIT', 30),

    /*
    | Budgets (USD per calendar month). Per-user budgets apply to calls made
    | with the server's keys; calls with the user's own key are only logged.
    | System tasks (job requirement extraction) share the global budget.
    */
    'budget' => [
        'user_monthly_usd' => (float) env('AI_MONTHLY_BUDGET_USD_PER_USER', 2.00),
        'system_monthly_usd' => (float) env('AI_SYSTEM_MONTHLY_BUDGET_USD', 20.00),
    ],

    /*
    | Job requirement extraction: rules always run (free, instant); the AI
    | refines them. "lazy" asks the AI only for jobs someone opens, "all"
    | for every new job, "off" never.
    */
    'job_requirements' => env('AI_JOB_REQUIREMENTS', 'lazy'),

    'prompts_version' => env('AI_PROMPTS_VERSION', 'v1'),

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'version' => '2023-06-01',
            'models' => [
                'fast' => env('ANTHROPIC_MODEL_FAST', 'claude-haiku-5-5'),
                'quality' => env('ANTHROPIC_MODEL_QUALITY', 'claude-sonnet-5-5'),
            ],
            // Users may bring their own key (stored encrypted).
            'user_keys' => true,
        ],
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'models' => [
                'fast' => env('GEMINI_MODEL_FAST', 'gemini-2.5-flash'),
                'quality' => env('GEMINI_MODEL_QUALITY', 'gemini-2.5-pro'),
            ],
        ],
        'groq' => [
            'driver' => 'openai',
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'models' => [
                'fast' => env('GROQ_MODEL_FAST', 'llama-3.1-8b-instant'),
                'quality' => env('GROQ_MODEL_QUALITY', 'llama-3.3-70b-versatile'),
            ],
        ],
        'openrouter' => [
            'driver' => 'openai',
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            // Free models (":free"): no cost, lower limits.
            'models' => [
                'fast' => env('OPENROUTER_MODEL_FAST', 'meta-llama/llama-3.3-70b-instruct:free'),
                'quality' => env('OPENROUTER_MODEL_QUALITY', 'meta-llama/llama-3.3-70b-instruct:free'),
            ],
        ],
        'mistral' => [
            'driver' => 'openai',
            'api_key' => env('MISTRAL_API_KEY'),
            'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
            'models' => [
                'fast' => env('MISTRAL_MODEL_FAST', 'mistral-small-latest'),
                'quality' => env('MISTRAL_MODEL_QUALITY', 'mistral-large-latest'),
            ],
        ],
        'ollama' => [
            'driver' => 'openai',
            // Local models need no key; the base URL switches the provider on.
            'api_key' => null,
            'keyless' => true,
            'base_url' => env('OLLAMA_BASE_URL'),
            'models' => [
                'fast' => env('OLLAMA_MODEL_FAST', 'llama3.1:8b'),
                'quality' => env('OLLAMA_MODEL_QUALITY', 'llama3.1:8b'),
            ],
        ],
    ],

    /*
    | USD per million tokens. Models not listed cost 0 (free tiers, local).
    | cache_read applies to prompt-cache hits, cache_write to cache creation.
    */
    'pricing' => [
        'claude-haiku-5-5' => ['input' => 1.00, 'output' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25],
        'claude-sonnet-5-5' => ['input' => 3.00, 'output' => 15.00, 'cache_read' => 0.30, 'cache_write' => 3.75],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'llama-3.1-8b-instant' => ['input' => 0.05, 'output' => 0.08],
        'llama-3.3-70b-versatile' => ['input' => 0.59, 'output' => 0.79],
        'mistral-small-latest' => ['input' => 0.10, 'output' => 0.30],
        'mistral-large-latest' => ['input' => 2.00, 'output' => 6.00],
    ],

];
