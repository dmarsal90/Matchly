<?php

declare(strict_types=1);

use App\Modules\Profiles\Domain\Data\GitHubUsername;
use App\Modules\Profiles\Domain\Services\SkillCatalog;

function catalog(): SkillCatalog
{
    $config = require __DIR__.'/../../../config/skills.php';

    return new SkillCatalog($config['catalog'], $config['packages']);
}

describe('normalize', function (): void {
    it('collapses synonyms, spelling and versions into one skill', function (string $input, string $slug, string $label): void {
        $skill = catalog()->normalize($input);

        expect($skill?->slug)->toBe($slug)
            ->and($skill?->label)->toBe($label)
            ->and($skill?->known)->toBeTrue();
    })->with([
        ['Vue.js', 'vue', 'Vue'],
        ['VueJS', 'vue', 'Vue'],
        ['vue 3', 'vue', 'Vue'],
        ['  Vue   3 ', 'vue', 'Vue'],
        ['Node.js', 'nodejs', 'Node.js'],
        ['node', 'nodejs', 'Node.js'],
        ['Postgres', 'postgresql', 'PostgreSQL'],
        ['k8s', 'kubernetes', 'Kubernetes'],
        ['C#', 'csharp', 'C#'],
        ['c++', 'cpp', 'C++'],
        ['Python3', 'python', 'Python'],
        ['golang', 'go', 'Go'],
        ['Spring Boot', 'spring', 'Spring'],
        ['ASP.NET', 'dotnet', '.NET'],
        ['Jupyter Notebook', 'jupyter', 'Jupyter'],
        ['SCSS', 'sass', 'Sass'],
        ['Shell', 'bash', 'Bash'],
        ['Dockerfile', 'docker', 'Docker'],
    ]);

    it('keeps unknown skills with a slug and the original spelling', function (): void {
        $skill = catalog()->normalize('Unreal Engine');

        expect($skill?->slug)->toBe('unreal-engine')
            ->and($skill?->label)->toBe('Unreal Engine')
            ->and($skill?->known)->toBeFalse();
    });

    it('rejects empty and overly long names', function (string $input): void {
        expect(catalog()->normalize($input))->toBeNull();
    })->with(['', '   ', str_repeat('x', 61)]);
});

describe('detectIn', function (): void {
    it('finds known skills in prose and counts mentions', function (): void {
        $found = catalog()->detectIn('Built APIs with Laravel and PostgreSQL. Laravel queues, Redis, Vue.js and Node.js services.');

        expect($found)->toMatchArray(['laravel' => 2, 'postgresql' => 1, 'redis' => 1, 'vue' => 1, 'nodejs' => 1]);
    });

    it('respects word and symbol boundaries', function (): void {
        $found = catalog()->detectIn('Senior JavaScript engineer. Some C++ and C# work.');

        expect($found)->toHaveKeys(['javascript', 'cpp', 'csharp'])
            ->not->toHaveKey('java')
            ->not->toHaveKey('c');
    });

    it('ignores words that are ambiguous in prose', function (): void {
        $found = catalog()->detectIn('Ready to go the extra mile and express ideas; R&D lead.');

        expect($found)->not->toHaveKeys(['go', 'express', 'r']);
    });

    it('detects ambiguous skills through their unambiguous spellings', function (): void {
        expect(catalog()->detectIn('Microservices in Golang with Express.js gateways'))
            ->toHaveKeys(['go', 'express', 'microservices']);
    });
});

it('maps manifest packages to skills, including prefix wildcards', function (): void {
    $catalog = catalog();

    expect($catalog->forPackage('composer', 'laravel/framework'))->toBe('laravel')
        ->and($catalog->forPackage('composer', 'laravel/sanctum'))->toBe('laravel')
        ->and($catalog->forPackage('npm', '@inertiajs/vue3'))->toBe('inertia')
        ->and($catalog->forPackage('npm', 'Vue'))->toBe('vue')
        ->and($catalog->forPackage('pypi', 'fastapi'))->toBe('fastapi')
        ->and($catalog->forPackage('npm', 'left-pad'))->toBeNull()
        ->and($catalog->forPackage('unknown', 'vue'))->toBeNull();
});

describe('GitHubUsername', function (): void {
    it('accepts usernames, @handles and profile URLs', function (string $input): void {
        expect(GitHubUsername::parse($input)->value)->toBe('octo-cat');
    })->with(['octo-cat', '@octo-cat', 'https://github.com/octo-cat', 'github.com/octo-cat/', 'https://www.github.com/octo-cat/repo?tab=stars']);

    it('rejects anything else', function (string $input): void {
        expect(GitHubUsername::tryParse($input))->toBeNull();
    })->with(['', '-octocat', 'octo--cat', 'https://gitlab.com/octocat', 'octo cat', str_repeat('a', 40)]);
});
