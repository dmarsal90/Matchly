<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Disabled by default in local so the Vite dev server (HMR over a
    | different origin) keeps working. Scripts are allowed through a
    | per-request nonce that Vite and the root Blade view share.
    |
    */

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', env('APP_ENV') !== 'local'),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),
        'connect_src' => array_values(array_filter(explode(',', (string) env('SECURITY_CSP_CONNECT_SRC', '')))),
        'font_src' => ['https://fonts.bunny.net'],
        'style_src' => ['https://fonts.bunny.net'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    */

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', env('APP_ENV') === 'production'),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
    ],

];
