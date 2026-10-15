<?php

declare(strict_types=1);

use App\Modules\Profiles\Infrastructure\LinkedIn\CsvTable;
use App\Modules\Profiles\Infrastructure\LinkedIn\LinkedInDate;
use App\Modules\Profiles\Infrastructure\LinkedIn\LinkedInExportMapper;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-10-14 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

function mapExport(array $names = ['Profile', 'Positions', 'Education', 'Skills', 'Languages', 'Certifications', 'Projects']): array
{
    $files = [];
    foreach ($names as $name) {
        $files[strtolower($name)] = linkedinFixture("{$name}.csv");
    }

    return (new LinkedInExportMapper(catalog(), 500))->map($files);
}

it('maps the profile without age, address or other sensitive fields', function (): void {
    $profile = mapExport()['profile'];

    expect($profile)->toBe([
        'headline' => 'Senior Backend Engineer · Laravel & Vue',
        'summary' => 'Backend developer focused on APIs.',
        'industry' => 'Software Development',
        'location' => 'Madrid, Community of Madrid, Spain',
        'websites' => ['https://ana.dev', 'https://acme.test'],
    ])->and(json_encode(mapExport()))->not->toContain('1990')->not->toContain('Calle Falsa')->not->toContain('28001');
});

it('maps positions with normalised dates', function (): void {
    $positions = mapExport()['positions'];

    expect($positions)->toHaveCount(3)
        ->and($positions[0])->toMatchArray(['title' => 'Senior Backend Engineer', 'company' => 'Acme', 'started_on' => '2021-03', 'finished_on' => null])
        ->and($positions[1])->toMatchArray(['company' => 'Globex', 'started_on' => '2017-01', 'finished_on' => '2021-02'])
        ->and($positions[2]['description'])->toBeNull();
});

it('combines listed skills with skills named in positions', function (): void {
    $skills = collect(mapExport()['skills'])->keyBy('skill');

    // Listed in Skills.csv and used at Acme until now.
    expect($skills['laravel'])->toMatchArray(['name' => 'Laravel', 'last_used_year' => 2026, 'years' => 5.5, 'level' => 4])
        ->and($skills['laravel']['evidence'])->toBe(['listed' => true, 'positions' => ['Senior Backend Engineer · Acme']])
        // Listed and used at Globex (2017-2021): four years, but not used since, so one level less.
        ->and($skills['vue'])->toMatchArray(['years' => 4.0, 'last_used_year' => 2021, 'level' => 3])
        // Only named in a position.
        ->and($skills['docker']['evidence']['listed'])->toBeFalse()
        // Listed only: no level is invented.
        ->and($skills['team-leadership'])->toMatchArray(['name' => 'Team Leadership', 'level' => null, 'years' => null])
        ->and($skills['php']['level'])->toBeNull();
});

it('maps languages written in any locale to ISO codes and CEFR levels', function (): void {
    expect(mapExport()['languages'])->toBe([
        ['name' => 'Spanish', 'code' => 'es', 'level' => 'native'],
        ['name' => 'English', 'code' => 'en', 'level' => 'C2'],
        ['name' => 'Klingon', 'code' => null, 'level' => 'A2'],
    ]);

    $spanish = (new LinkedInExportMapper(catalog(), 500))->map([
        'languages' => "Nombre,Nivel de competencia\nInglés,Competencia profesional completa\nAlemán,Competencia básica profesional",
    ]);
    // Localised headers are not recognised, so the file reads as empty rather than wrong.
    expect($spanish['languages'])->toBe([]);

    $localisedNames = (new LinkedInExportMapper(catalog(), 500))->map([
        'languages' => "Name,Proficiency\nInglés,Competencia profesional completa\nAlemán,Competencia básica profesional\nPortuguês,Competência nativa ou bilíngue",
    ]);
    expect($localisedNames['languages'])->toBe([
        ['name' => 'Inglés', 'code' => 'en', 'level' => 'C2'],
        ['name' => 'Alemán', 'code' => 'de', 'level' => 'B1'],
        ['name' => 'Português', 'code' => 'pt', 'level' => 'native'],
    ]);
});

it('maps education, certifications and projects', function (): void {
    $data = mapExport();

    expect($data['education'])->toBe([['school' => 'Universidad Politécnica de Madrid', 'degree' => 'Computer Engineering', 'notes' => null, 'started_on' => '2012', 'finished_on' => '2016']])
        ->and($data['certifications'][0])->toMatchArray(['name' => 'AWS Certified Developer', 'authority' => 'Amazon Web Services', 'url' => 'https://aws.amazon.com/verify/123', 'started_on' => '2022-04'])
        ->and($data['certifications'][0])->not->toHaveKey('license number')
        ->and($data['projects'][0])->toMatchArray(['title' => 'Open source job board', 'url' => 'https://github.com/ana/jobs', 'finished_on' => null]);
});

it('tolerates missing files and columns', function (): void {
    $data = (new LinkedInExportMapper(catalog(), 500))->map(['positions' => "Title,Company Name\nDeveloper,Acme"]);

    expect($data['profile'])->toBeNull()
        ->and($data['skills'])->toBe([])
        ->and($data['positions'][0])->toMatchArray(['title' => 'Developer', 'company' => 'Acme', 'description' => null, 'started_on' => null]);
});

describe('CsvTable', function (): void {
    it('decodes UTF-8 with BOM, UTF-16 and Windows-1252', function (string $contents): void {
        expect(CsvTable::rows($contents, ['Name'], 10))->toBe([['name' => 'Ingeniería']]);
    })->with([
        'utf-8 bom' => "\xEF\xBB\xBFName\nIngeniería",
        'utf-16le bom' => "\xFF\xFE".mb_convert_encoding("Name\nIngeniería", 'UTF-16LE', 'UTF-8'),
        'windows-1252' => mb_convert_encoding("Name\nIngeniería", 'Windows-1252', 'UTF-8'),
    ]);

    it('skips preamble lines before the header and blank rows', function (): void {
        $rows = CsvTable::rows(linkedinFixture('Connections.csv'), ['First Name', 'Company'], 10);

        expect($rows)->toBe([['first name' => 'Bob', 'company' => 'Acme']]);
    });

    it('stops at the row limit', function (): void {
        expect(CsvTable::rows("Name\na\nb\nc", ['Name'], 2))->toHaveCount(2);
    });
});

describe('LinkedInDate', function (): void {
    it('normalises the date formats LinkedIn exports', function (string $input, ?string $expected): void {
        expect(LinkedInDate::parse($input))->toBe($expected);
    })->with([
        ['Mar 2021', '2021-03'],
        ['mar. 2021', '2021-03'],
        ['dic 2019', '2019-12'],
        ['03/2021', '2021-03'],
        ['2016', '2016'],
        ['', null],
        ['Present', null],
    ]);

    it('measures periods, open-ended ones until today', function (): void {
        expect(LinkedInDate::span('2021-03', null))->toBe([5.6, 2026])
            ->and(LinkedInDate::span('2012', '2016'))->toBe([5.0, 2016])
            ->and(LinkedInDate::span(null, '2020-01'))->toBe([0.0, 2020]);
    });
});
