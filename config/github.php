<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GitHub import
|--------------------------------------------------------------------------
|
| Only the official REST API and public data are used. Requests carry the
| user's own OAuth token when their account is linked (rate limits are per
| token), otherwise GITHUB_API_TOKEN; with neither, GitHub allows 60 requests an
| hour per IP, so the import reads fewer repositories. Responses are cached
| and revalidated with ETags: a 304 does not count against an authorised
| rate limit.
|
*/

return [

    'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),

    // A fine-grained token with no permissions is enough (public data only).
    'token' => env('GITHUB_API_TOKEN'),

    'timeout' => (int) env('GITHUB_TIMEOUT', 15),

    // Response cache (ETag + body) lifetime.
    'cache_ttl_seconds' => (int) env('GITHUB_CACHE_TTL', 7 * 86400),

    // Own, non-fork repositories pushed to within this window.
    'recent_months' => (int) env('GITHUB_RECENT_MONTHS', 36),

    'max_repositories' => (int) env('GITHUB_MAX_REPOSITORIES', 20),
    'anonymous_max_repositories' => 8,

    // READMEs are kept (truncated) as context for the AI summary.
    'readme_repositories' => 8,
    'readme_max_chars' => 1500,

    'max_manifest_bytes' => 256 * 1024,

    // A language counts for a repository above this share of its code.
    'min_language_share' => 0.05,

    'manifests' => [
        'composer.json' => 'composer',
        'package.json' => 'npm',
        'requirements.txt' => 'pypi',
        'pyproject.toml' => 'pypi',
        'Pipfile' => 'pypi',
        'Gemfile' => 'rubygems',
        'go.mod' => 'go',
        'Cargo.toml' => 'cargo',
        'pom.xml' => 'maven',
        'build.gradle' => 'maven',
        'build.gradle.kts' => 'maven',
    ],

    // Root files that are evidence by themselves.
    'marker_files' => [
        'Dockerfile' => 'docker',
        'docker-compose.yml' => 'docker',
        'docker-compose.yaml' => 'docker',
        'compose.yaml' => 'docker',
        'compose.yml' => 'docker',
        'Chart.yaml' => 'helm',
        'serverless.yml' => 'serverless',
        'tailwind.config.js' => 'tailwindcss',
        'tailwind.config.ts' => 'tailwindcss',
        'vite.config.ts' => 'vite',
        'vite.config.js' => 'vite',
        'phpunit.xml' => 'phpunit',
        'phpunit.xml.dist' => 'phpunit',
        'artisan' => 'laravel',
        '.gitlab-ci.yml' => 'gitlab-ci',
        'Jenkinsfile' => 'jenkins',
    ],

];
