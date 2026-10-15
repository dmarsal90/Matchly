<?php

declare(strict_types=1);

/*
 * Every user-facing text lives in lang/{es,en,pt}: the three languages must
 * offer the same keys, with the same :placeholders, and Vue templates must
 * not contain literal text.
 */

const LOCALES = ['es', 'en', 'pt'];

/** Groups published by laravel-lang: kept in sync by `php artisan lang:update`, not by hand. */
const VENDOR_GROUPS = ['actions', 'auth', 'http-statuses', 'pagination', 'passwords', 'validation'];

/**
 * @return array<string, string> dotted key => text
 */
function flattenTranslations(array $lines, string $prefix = ''): array
{
    $flat = [];
    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        if (is_array($value)) {
            $flat += flattenTranslations($value, $path);
        } else {
            $flat[$path] = (string) $value;
        }
    }

    return $flat;
}

/**
 * @return list<string>
 */
function placeholders(string $text): array
{
    preg_match_all('/:([a-z_]+)/', $text, $matches);
    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

it('offers the same translation groups in every language', function (): void {
    $groups = fn (string $locale): array => array_map(fn (string $path): string => basename($path, '.php'), glob(lang_path("{$locale}/*.php")) ?: []);

    foreach (LOCALES as $locale) {
        expect($groups($locale))->toEqualCanonicalizing($groups('es'));
    }
});

it('offers the same keys and placeholders in every language', function (string $group): void {
    $reference = flattenTranslations(require lang_path("es/{$group}.php"));

    foreach (['en', 'pt'] as $locale) {
        $other = flattenTranslations(require lang_path("{$locale}/{$group}.php"));

        expect(array_keys($other))->toEqualCanonicalizing(array_keys($reference), "{$locale}/{$group}.php keys differ from es");

        foreach ($reference as $key => $text) {
            expect(placeholders($other[$key]))->toBe(placeholders($text), "Placeholders of {$group}.{$key} differ in {$locale}");
        }
    }
})->with(fn (): array => array_values(array_filter(
    // Datasets are built before the app boots: no lang_path() here.
    array_map(fn (string $path): string => basename($path, '.php'), glob(__DIR__.'/../../lang/es/*.php') ?: []),
    fn (string $group): bool => ! in_array($group, VENDOR_GROUPS, true),
)));

it('keeps the JSON translations aligned', function (): void {
    $keys = fn (string $locale): array => array_keys(json_decode((string) file_get_contents(lang_path("{$locale}.json")), true));

    expect($keys('en'))->toEqualCanonicalizing($keys('es'))
        ->and($keys('pt'))->toEqualCanonicalizing($keys('es'));
});

it('has no literal text in Vue templates', function (): void {
    // Text that is the same in every language.
    $allowed = '/^(?:[\s\p{P}\p{S}\d]*|https?:\/\/\S*|UTC|—|·)$/u';
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = $file->getPathname();
        // shadcn-vue primitives render no copy of their own.
        if ($file->getExtension() !== 'vue' || str_contains($path, '/components/ui/')) {
            continue;
        }

        if (preg_match('#<template>(.*)</template>#s', (string) file_get_contents($path), $template) !== 1) {
            continue;
        }

        $markup = (string) preg_replace(['/<!--.*?-->/s', '/\{\{.*?\}\}/s'], '', $template[1]);

        // Literal (unbound) attributes people read or hear.
        preg_match_all('/\s(?:placeholder|title|aria-label|alt|label|description|loading-label|separator)="([^"]*)"/', $markup, $attributes);
        foreach ($attributes[1] as $value) {
            if (preg_match('/\p{L}{2,}/u', $value) === 1 && preg_match($allowed, trim($value)) !== 1) {
                $offenders[] = str_replace(resource_path('js').'/', '', $path).": {$value}";
            }
        }

        // Text between tags (tags matched with quoted attribute values, which may contain ">").
        foreach (preg_split('/<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/', $markup) ?: [] as $text) {
            $text = trim($text);
            if ($text !== '' && preg_match('/\p{L}{2,}/u', $text) === 1 && preg_match($allowed, $text) !== 1) {
                $offenders[] = str_replace(resource_path('js').'/', '', $path).': '.mb_substr((string) preg_replace('/\s+/', ' ', $text), 0, 80);
            }
        }
    }

    expect($offenders)->toBe([]);
});
