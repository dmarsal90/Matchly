<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Services\DeterministicTailor;

function sampleResume(): ResumeData
{
    return ResumeData::fromArray([
        'contact' => ['name' => 'Ana García', 'email' => 'ana@example.com', 'links' => [['label' => 'github', 'url' => 'https://github.com/ana'], ['label' => 'evil', 'url' => 'javascript:alert(1)']]],
        'headline' => 'Backend engineer',
        'experience' => [[
            'title' => 'Senior Backend Engineer',
            'company' => 'Acme',
            'started_on' => '2021-03',
            'finished_on' => null,
            'bullets' => ['Mentored two junior developers.', 'Built REST APIs with Laravel and PostgreSQL.', 'Ran the on-call rota.'],
        ]],
        'skills' => ['Figma', 'PHP', 'Laravel', 'PostgreSQL'],
    ]);
}

it('sanitises CV data: tags, unsafe links, bad dates and oversized lists', function (): void {
    $resume = ResumeData::fromArray([
        'contact' => ['name' => '<b>Ana</b>', 'links' => [['label' => 'x', 'url' => 'javascript:alert(1)'], ['label' => 'web', 'url' => 'https://ana.dev']]],
        'experience' => [['title' => ' Dev ', 'started_on' => 'March 2020', 'bullets' => ['a', 'a', '', '<script>x</script>b']], ['title' => '']],
        'skills' => array_fill(0, 50, 'PHP'),
    ]);

    expect($resume->contact['name'])->toBe('Ana')
        ->and($resume->contact['links'])->toBe([['label' => 'web', 'url' => 'https://ana.dev']])
        ->and($resume->experience)->toHaveCount(1)
        ->and($resume->experience[0])->toMatchArray(['title' => 'Dev', 'started_on' => null, 'bullets' => ['a', 'xb']])
        ->and($resume->skills)->toBe(['PHP']);
});

it('round-trips and exposes its facts as text', function (): void {
    $resume = sampleResume();

    expect(ResumeData::fromArray($resume->toArray()))->toEqual($resume)
        ->and($resume->plainText())->toContain('Acme')->toContain('Built REST APIs')->toContain('Figma');
});

it('moves what the job asks for to the front without adding anything', function (): void {
    $tailored = (new DeterministicTailor(catalog()))->tailor(sampleResume(), ['laravel', 'postgresql', 'kubernetes']);

    expect($tailored->skills)->toBe(['Laravel', 'PostgreSQL', 'Figma', 'PHP'])
        ->and($tailored->experience[0]['bullets'][0])->toBe('Built REST APIs with Laravel and PostgreSQL.')
        ->and($tailored->experience[0]['bullets'])->toHaveCount(3)
        ->and($tailored->keywords)->toBe(['Laravel', 'PostgreSQL'])
        // Never claims the missing skill.
        ->and($tailored->plainText())->not->toContain('Kubernetes')
        ->and($tailored->summary)->toBe('Backend engineer');
});
