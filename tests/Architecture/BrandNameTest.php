<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * The product name lives in APP_NAME (.env) and the default of config/app.php.
 * Everything else (UI, mails, titles, metadata, PDFs) must read it from config
 * or receive it as the :app translation placeholder.
 */
it('never hardcodes the product name outside configuration', function (): void {
    $root = dirname(__DIR__, 2);
    $brand = (string) config('app.name');

    expect($brand)->not->toBe('');

    $finder = Finder::create()
        ->files()
        ->in(array_map(fn (string $dir): string => "{$root}/{$dir}", [
            'app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'tests',
        ]))
        ->exclude(['cache', 'js/actions', 'js/routes', 'js/wayfinder'])
        ->notName(['php_*.json', '*.sqlite'])
        ->ignoreDotFiles(false);

    $offenders = [];

    foreach ($finder as $file) {
        $relative = str_replace($root.'/', '', $file->getRealPath() ?: $file->getPathname());

        if ($relative === 'config/app.php') {
            continue;
        }

        if (stripos($file->getContents(), $brand) !== false) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([], 'Hardcoded product name found in: '.implode(', ', $offenders));
});

it('defines the product name exactly once in config/app.php', function (): void {
    $contents = (string) file_get_contents(dirname(__DIR__, 2).'/config/app.php');

    expect(substr_count(strtolower($contents), strtolower((string) config('app.name'))))->toBeLessThanOrEqual(1);
});
