<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
 * phpunit.xml pins SQLite in memory. CI also runs the suite on PostgreSQL, the
 * production database: TEST_DB_CONNECTION=pgsql TEST_DB_DATABASE=app_testing.
 * RefreshDatabase wipes the target, so the name must say it is for tests.
 */
$connection = getenv('TEST_DB_CONNECTION');
if (is_string($connection) && $connection !== '') {
    $database = (string) getenv('TEST_DB_DATABASE');
    if (! str_contains($database, 'test')) {
        fwrite(STDERR, "TEST_DB_DATABASE must name a test database (got \"{$database}\").\n");
        exit(1);
    }

    foreach (['DB_CONNECTION' => $connection, 'DB_DATABASE' => $database] as $name => $value) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
