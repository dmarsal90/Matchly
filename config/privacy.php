<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Policy version
    |--------------------------------------------------------------------------
    |
    | Stored with every consent. Bump it when the privacy policy changes in a
    | way that requires asking users again.
    |
    */

    'policy_version' => env('PRIVACY_POLICY_VERSION', '2026-10'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Applied daily by `privacy:prune`.
    |
    */

    'retention' => [
        // Accounts that never verified their e-mail.
        'unverified_account_days' => (int) env('PRIVACY_UNVERIFIED_ACCOUNT_DAYS', 30),
        // Extracted CV text of accounts inactive for this long (null keeps it).
        'inactive_cv_text_months' => env('PRIVACY_INACTIVE_CV_TEXT_MONTHS') !== null ? (int) env('PRIVACY_INACTIVE_CV_TEXT_MONTHS') : 24,
        // Imports never reviewed (or failed) are discarded after this many days.
        'unreviewed_import_days' => (int) env('PRIVACY_UNREVIEWED_IMPORT_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | User files
    |--------------------------------------------------------------------------
    |
    | Disk for CVs and imports. Files are encrypted by the application before
    | they reach the disk (local in development, S3 in production).
    |
    */

    'files_disk' => env('USER_FILES_DISK', 'local'),

    'cv' => [
        'max_kilobytes' => 10240,
        'max_documents' => 5,
        // Decompressed size limit for DOCX (zip) files.
        'max_uncompressed_bytes' => 50 * 1024 * 1024,
    ],

];
