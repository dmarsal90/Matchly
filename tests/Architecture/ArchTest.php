<?php

declare(strict_types=1);

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('every application file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('domain layers stay framework-agnostic')
    ->expect(modulesLayer('Domain'))
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Http',
        'Illuminate\Support\Facades',
        'Illuminate\Foundation',
    ]);

arch('domain and application layers do not reach into HTTP')
    ->expect([...modulesLayer('Domain'), ...modulesLayer('Application')])
    ->not->toUse('Illuminate\Http\Request');

arch('controllers do not talk to the database directly')
    ->expect(modulesLayer('Http\Controllers'))
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\Query\Builder']);

arch('form requests extend the framework base class')
    ->expect(modulesLayer('Http\Requests'))
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

/**
 * @return list<string>
 */
function modulesLayer(string $layer): array
{
    $modules = glob(dirname(__DIR__, 2).'/app/Modules/*', GLOB_ONLYDIR) ?: [];

    return array_values(array_map(
        fn (string $path): string => 'App\\Modules\\'.basename($path).'\\'.$layer,
        $modules,
    ));
}
