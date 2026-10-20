<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Infrastructure\Classifiers\RuleBasedWorkModeClassifier;
use App\Modules\Providers\Infrastructure\Sources\Adzuna\AdzunaNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Adzuna\AdzunaProvider;
use App\Modules\Providers\Infrastructure\Sources\Arbeitnow\ArbeitnowNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Arbeitnow\ArbeitnowProvider;
use App\Modules\Providers\Infrastructure\Sources\Ats\Ashby\AshbyNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Ashby\AshbyProvider;
use App\Modules\Providers\Infrastructure\Sources\Ats\Greenhouse\GreenhouseNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Greenhouse\GreenhouseProvider;
use App\Modules\Providers\Infrastructure\Sources\Ats\Lever\LeverNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Lever\LeverProvider;
use App\Modules\Providers\Infrastructure\Sources\Ats\SmartRecruiters\SmartRecruitersNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\SmartRecruiters\SmartRecruitersProvider;
use App\Modules\Providers\Infrastructure\Sources\Ats\Workable\WorkableNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Ats\Workable\WorkableProvider;
use App\Modules\Providers\Infrastructure\Sources\Himalayas\HimalayasNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Himalayas\HimalayasProvider;
use App\Modules\Providers\Infrastructure\Sources\Jobicy\JobicyNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Jobicy\JobicyProvider;
use App\Modules\Providers\Infrastructure\Sources\Jooble\JoobleNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Jooble\JoobleProvider;
use App\Modules\Providers\Infrastructure\Sources\PuenteTalent\PuenteTalentNormalizer;
use App\Modules\Providers\Infrastructure\Sources\PuenteTalent\PuenteTalentProvider;
use App\Modules\Providers\Infrastructure\Sources\RemoteJobsOrg\RemoteJobsOrgNormalizer;
use App\Modules\Providers\Infrastructure\Sources\RemoteJobsOrg\RemoteJobsOrgProvider;
use App\Modules\Providers\Infrastructure\Sources\RemoteLanders\RemoteLandersNormalizer;
use App\Modules\Providers\Infrastructure\Sources\RemoteLanders\RemoteLandersProvider;
use App\Modules\Providers\Infrastructure\Sources\RemoteOk\RemoteOkNormalizer;
use App\Modules\Providers\Infrastructure\Sources\RemoteOk\RemoteOkProvider;
use App\Modules\Providers\Infrastructure\Sources\Remotive\RemotiveNormalizer;
use App\Modules\Providers\Infrastructure\Sources\Remotive\RemotiveProvider;
use App\Modules\Providers\Infrastructure\Sources\WeWorkRemotely\WeWorkRemotelyNormalizer;
use App\Modules\Providers\Infrastructure\Sources\WeWorkRemotely\WeWorkRemotelyProvider;
use App\Modules\Providers\Infrastructure\Sources\WorkingNomads\WorkingNomadsNormalizer;
use App\Modules\Providers\Infrastructure\Sources\WorkingNomads\WorkingNomadsProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Freshness
    |--------------------------------------------------------------------------
    |
    | Postings older than this are skipped at ingestion, closed by the hourly
    | clean-up and never shown in listings.
    |
    */

    'max_age_days' => (int) env('JOBS_MAX_AGE_DAYS', 30),

    'per_page' => (int) env('JOBS_PER_PAGE', 20),

    /*
    |--------------------------------------------------------------------------
    | Accepted work modes
    |--------------------------------------------------------------------------
    |
    | The platform is about remote work: on-site postings from mixed sources
    | (ATS boards, Adzuna, Arbeitnow) are dropped at ingestion.
    |
    */

    'accepted_work_modes' => [WorkMode::Remote->value, WorkMode::Hybrid->value],

    /*
    |--------------------------------------------------------------------------
    | Classifier
    |--------------------------------------------------------------------------
    |
    | Implementation of WorkModeClassifier. Swap for an LLM-backed one once the
    | AI module exists without touching the ingestion pipeline.
    |
    */

    'classifier' => RuleBasedWorkModeClassifier::class,

    /*
    |--------------------------------------------------------------------------
    | Link checks
    |--------------------------------------------------------------------------
    |
    | Open jobs are re-checked every `interval_days`; a 404/410 closes them.
    |
    */

    'link_check' => [
        'interval_days' => (int) env('JOBS_LINK_CHECK_INTERVAL_DAYS', 3),
        'batch_size' => 200,
        'per_host_per_minute' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing HTTP
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => 20,
        'connect_timeout' => 10,
        'retries' => 3,
        'retry_base_ms' => 500,
        // Throttling waits at most this long before failing the request.
        'max_throttle_wait_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Every source pairs a JobProvider (fetch) with a JobNormalizer (map to the
    | common model). `interval` is the minimum minutes between runs (respect
    | each API's terms), `rate_limit` the requests per minute and `cache_ttl`
    | how long raw responses are reused, in seconds.
    |
    | Adding a source: create the two classes under
    | app/Modules/Providers/Infrastructure/Sources/<Name> and add an entry here.
    |
    */

    'providers' => [

        'remotive' => [
            'enabled' => (bool) env('JOBS_REMOTIVE_ENABLED', true),
            'provider' => RemotiveProvider::class,
            'normalizer' => RemotiveNormalizer::class,
            // Terms: "max. 4 times a day".
            'interval' => 360,
            'rate_limit' => 2,
            'cache_ttl' => 3600,
            'options' => [
                'url' => 'https://remotive.com/api/remote-jobs',
            ],
        ],

        'remoteok' => [
            'enabled' => (bool) env('JOBS_REMOTEOK_ENABLED', true),
            'provider' => RemoteOkProvider::class,
            'normalizer' => RemoteOkNormalizer::class,
            'interval' => 120,
            'rate_limit' => 2,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://remoteok.com/api',
            ],
        ],

        'jobicy' => [
            'enabled' => (bool) env('JOBS_JOBICY_ENABLED', true),
            'provider' => JobicyProvider::class,
            'normalizer' => JobicyNormalizer::class,
            'interval' => 120,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://jobicy.com/api/v2/remote-jobs',
                'per_page' => 100,
                'max_pages' => 5,
            ],
        ],

        'himalayas' => [
            'enabled' => (bool) env('JOBS_HIMALAYAS_ENABLED', true),
            'provider' => HimalayasProvider::class,
            'normalizer' => HimalayasNormalizer::class,
            'interval' => 120,
            'rate_limit' => 20,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://himalayas.app/jobs/api',
                'per_page' => 20,
                'max_pages' => 50,
            ],
        ],

        'arbeitnow' => [
            'enabled' => (bool) env('JOBS_ARBEITNOW_ENABLED', true),
            'provider' => ArbeitnowProvider::class,
            'normalizer' => ArbeitnowNormalizer::class,
            'interval' => 120,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://www.arbeitnow.com/api/job-board-api',
                'max_pages' => 5,
            ],
        ],

        'puentetalent' => [
            'enabled' => (bool) env('JOBS_PUENTETALENT_ENABLED', true),
            'provider' => PuenteTalentProvider::class,
            'normalizer' => PuenteTalentNormalizer::class,
            'interval' => 360,
            'rate_limit' => 2,
            'cache_ttl' => 3600,
            'options' => [
                'url' => 'https://puentetalent.com/api/jobs',
                'pages' => 20,
            ],
        ],

        'remotelanders' => [
            'enabled' => (bool) env('JOBS_REMOTELANDERS_ENABLED', true),
            'provider' => RemoteLandersProvider::class,
            'normalizer' => RemoteLandersNormalizer::class,
            'interval' => 240,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://remotelanders.com/api/jobs',
                'max_pages' => 5,
            ],
        ],

        'remotejobsorg' => [
            'enabled' => (bool) env('JOBS_REMOTEJOBSORG_ENABLED', true),
            'provider' => RemoteJobsOrgProvider::class,
            'normalizer' => RemoteJobsOrgNormalizer::class,
            'interval' => 240,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://remotejobs.org/api/v1/jobs',
                'max_pages' => 6,
            ],
        ],

        'workingnomads' => [
            'enabled' => (bool) env('JOBS_WORKINGNOMADS_ENABLED', true),
            'provider' => WorkingNomadsProvider::class,
            'normalizer' => WorkingNomadsNormalizer::class,
            'interval' => 120,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://www.workingnomads.com/api/exposed_jobs/',
            ],
        ],

        'weworkremotely' => [
            'enabled' => (bool) env('JOBS_WEWORKREMOTELY_ENABLED', true),
            'provider' => WeWorkRemotelyProvider::class,
            'normalizer' => WeWorkRemotelyNormalizer::class,
            'interval' => 120,
            'rate_limit' => 10,
            'cache_ttl' => 1800,
            'options' => [
                'feeds' => [
                    'https://weworkremotely.com/remote-jobs.rss',
                    'https://weworkremotely.com/categories/remote-programming-jobs.rss',
                    'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
                    'https://weworkremotely.com/categories/remote-front-end-programming-jobs.rss',
                    'https://weworkremotely.com/categories/remote-back-end-programming-jobs.rss',
                    'https://weworkremotely.com/categories/remote-devops-sysadmin-jobs.rss',
                    'https://weworkremotely.com/categories/remote-design-jobs.rss',
                    'https://weworkremotely.com/categories/remote-product-jobs.rss',
                    'https://weworkremotely.com/categories/remote-customer-support-jobs.rss',
                    'https://weworkremotely.com/categories/remote-sales-and-marketing-jobs.rss',
                ],
            ],
        ],

        'adzuna' => [
            // Free key at https://developer.adzuna.com
            'enabled' => (bool) env('JOBS_ADZUNA_ENABLED', env('ADZUNA_APP_ID') !== null && env('ADZUNA_APP_ID') !== ''),
            'provider' => AdzunaProvider::class,
            'normalizer' => AdzunaNormalizer::class,
            'interval' => 360,
            'rate_limit' => 20,
            'cache_ttl' => 3600,
            'options' => [
                'app_id' => env('ADZUNA_APP_ID'),
                'app_key' => env('ADZUNA_APP_KEY'),
                'countries' => array_filter(explode(',', (string) env('ADZUNA_COUNTRIES', 'gb,us,de,es,fr,nl,ca,au,br'))),
                'what' => 'remote',
                'per_page' => 50,
                'max_pages' => 2,
            ],
        ],

        'jooble' => [
            // Free key at https://jooble.org/api/about
            'enabled' => (bool) env('JOBS_JOOBLE_ENABLED', env('JOOBLE_API_KEY') !== null && env('JOOBLE_API_KEY') !== ''),
            'provider' => JoobleProvider::class,
            'normalizer' => JoobleNormalizer::class,
            'interval' => 360,
            'rate_limit' => 10,
            'cache_ttl' => 3600,
            'options' => [
                'api_key' => env('JOOBLE_API_KEY'),
                'keywords' => 'remote',
                'max_pages' => 3,
            ],
        ],

        // Public ATS boards: one request per company listed in the ats_companies table.

        'greenhouse' => [
            'enabled' => (bool) env('JOBS_GREENHOUSE_ENABLED', true),
            'provider' => GreenhouseProvider::class,
            'normalizer' => GreenhouseNormalizer::class,
            'interval' => 180,
            'rate_limit' => 60,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://boards-api.greenhouse.io/v1/boards/{company}/jobs?content=true',
            ],
        ],

        'lever' => [
            'enabled' => (bool) env('JOBS_LEVER_ENABLED', true),
            'provider' => LeverProvider::class,
            'normalizer' => LeverNormalizer::class,
            'interval' => 180,
            'rate_limit' => 60,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://api.lever.co/v0/postings/{company}?mode=json',
            ],
        ],

        'ashby' => [
            'enabled' => (bool) env('JOBS_ASHBY_ENABLED', true),
            'provider' => AshbyProvider::class,
            'normalizer' => AshbyNormalizer::class,
            'interval' => 180,
            'rate_limit' => 60,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://api.ashbyhq.com/posting-api/job-board/{company}?includeCompensation=true',
            ],
        ],

        'workable' => [
            'enabled' => (bool) env('JOBS_WORKABLE_ENABLED', true),
            'provider' => WorkableProvider::class,
            'normalizer' => WorkableNormalizer::class,
            'interval' => 180,
            'rate_limit' => 30,
            'cache_ttl' => 1800,
            'options' => [
                'url' => 'https://apply.workable.com/api/v1/widget/accounts/{company}?details=true',
            ],
        ],

        'smartrecruiters' => [
            'enabled' => (bool) env('JOBS_SMARTRECRUITERS_ENABLED', true),
            'provider' => SmartRecruitersProvider::class,
            'normalizer' => SmartRecruitersNormalizer::class,
            'interval' => 360,
            'rate_limit' => 60,
            'cache_ttl' => 3600,
            'options' => [
                'list_url' => 'https://api.smartrecruiters.com/v1/companies/{company}/postings',
                'detail_url' => 'https://api.smartrecruiters.com/v1/companies/{company}/postings/{id}',
                'per_page' => 100,
                'max_pages' => 5,
            ],
        ],

    ],

];
