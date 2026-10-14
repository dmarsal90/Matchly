<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LinkedIn data export import
|--------------------------------------------------------------------------
|
| No scraping and no automation of LinkedIn: users upload the ZIP that
| LinkedIn's official "Get a copy of your data" feature sends them. The ZIP
| is read in place (never extracted to disk, never stored) and only the
| whitelisted CSV files below are opened; messages, connections and the
| rest are skipped without being read.
|
*/

return [

    // Linked from the guide; LinkedIn itself is never called.
    'export_url' => 'https://www.linkedin.com/mypreferences/d/download-my-data',

    'max_upload_kilobytes' => (int) env('LINKEDIN_MAX_UPLOAD_KB', 50 * 1024),

    // Declared size of everything inside the archive (zip bomb guard).
    'max_uncompressed_bytes' => 512 * 1024 * 1024,

    // Real bytes read from any single whitelisted CSV.
    'max_file_bytes' => 5 * 1024 * 1024,

    // Above this compression ratio an entry is treated as a bomb.
    'max_compression_ratio' => 100,

    'max_entries' => 10000,

    // Basename (case-insensitive) => section.
    'files' => [
        'profile.csv' => 'profile',
        'positions.csv' => 'positions',
        'education.csv' => 'education',
        'skills.csv' => 'skills',
        'languages.csv' => 'languages',
        'certifications.csv' => 'certifications',
        'projects.csv' => 'projects',
    ],

    // Rows read per file; real profiles are far below this.
    'max_rows' => 500,

];
