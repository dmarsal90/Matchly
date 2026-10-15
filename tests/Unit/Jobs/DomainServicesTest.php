<?php

declare(strict_types=1);

use App\Modules\Jobs\Domain\Data\Salary;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\SalaryPeriod;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Domain\Services\CanonicalUrl;
use App\Modules\Jobs\Domain\Services\JobFingerprint;
use App\Modules\Jobs\Domain\Services\SalaryParser;

describe('CanonicalUrl', function (): void {
    it('normalizes host, scheme, trailing slash, fragment and tracking parameters', function (string $url, string $expected): void {
        expect(CanonicalUrl::from($url))->toBe($expected);
    })->with([
        ['http://WWW.Example.com/jobs/123/?utm_source=x&ref=abc#apply', 'https://example.com/jobs/123'],
        ['https://boards.greenhouse.io/acme/jobs/1?gh_src=feed&gh_jid=1', 'https://boards.greenhouse.io/acme/jobs/1'],
        ['https://jobs.example.com/view?id=9&b=2&utm_campaign=z', 'https://jobs.example.com/view?b=2&id=9'],
    ]);

    it('treats URLs differing only in tracking noise as equal', function (): void {
        expect(CanonicalUrl::from('https://acme.com/careers/42?utm_medium=email'))
            ->toBe(CanonicalUrl::from('http://www.acme.com/careers/42/'));
    });
});

describe('JobFingerprint', function (): void {
    it('ignores casing, accents, remote markers and legal suffixes', function (): void {
        expect(JobFingerprint::make('Senior Backend Engineer (Remote)', 'Acme Inc.'))
            ->toBe(JobFingerprint::make('senior backend engineer - remote', 'ACME'))
            ->and(JobFingerprint::make('Développeur PHP (m/w/d)', 'Société Générale SA'))
            ->toBe(JobFingerprint::make('Developpeur PHP', 'Societe Generale'));
    });

    it('distinguishes different roles at the same company', function (): void {
        expect(JobFingerprint::make('Backend Engineer', 'Acme'))->not->toBe(JobFingerprint::make('Frontend Engineer', 'Acme'));
    });
});

describe('SalaryParser', function (): void {
    it('parses common free-text salaries', function (string $text, ?float $min, ?float $max, ?string $currency, SalaryPeriod $period): void {
        $salary = SalaryParser::parse($text);

        expect($salary)->not->toBeNull()
            ->and($salary?->min)->toBe($min)
            ->and($salary?->max)->toBe($max)
            ->and($salary?->currency)->toBe($currency)
            ->and($salary?->period)->toBe($period);
    })->with([
        'remotive k with decimals' => ['$35,3k- $52k', 35300.0, 52000.0, 'USD', SalaryPeriod::Year],
        'euro thousands separators' => ['€60.000 - €75.000 / year', 60000.0, 75000.0, 'EUR', SalaryPeriod::Year],
        'hourly with code' => ['USD 40-50 per hour', 40.0, 50.0, 'USD', SalaryPeriod::Hour],
        'single value' => ['£120k', 120000.0, null, 'GBP', SalaryPeriod::Year],
        'monthly' => ['3.500 € al mes / month', 3500.0, null, 'EUR', SalaryPeriod::Month],
    ]);

    it('returns null when there is nothing to parse', function (?string $text): void {
        expect(SalaryParser::parse($text))->toBeNull();
    })->with([null, '', 'Competitive', 'DOE']);

    it('annualizes periodic salaries', function (): void {
        $salary = new Salary(20.0, 30.0, 'USD', SalaryPeriod::Hour);

        expect($salary->annualMin())->toBe(41600.0)->and($salary->annualMax())->toBe(62400.0);
    });

    it('re-reads periods that are impossible for the amount', function (float $amount, SalaryPeriod $stated, SalaryPeriod $expected): void {
        expect(Salary::tryFrom($amount, null, 'USD', $stated)?->period)->toBe($expected);
    })->with([
        'yearly labelled hourly' => [41600, SalaryPeriod::Hour, SalaryPeriod::Year],
        'monthly labelled hourly' => [3500, SalaryPeriod::Hour, SalaryPeriod::Month],
        'real hourly' => [85, SalaryPeriod::Hour, SalaryPeriod::Hour],
        'yearly labelled monthly' => [180000, SalaryPeriod::Month, SalaryPeriod::Year],
        'real monthly' => [6000, SalaryPeriod::Month, SalaryPeriod::Month],
    ]);

    it('repairs reversed or zero ranges from sources', function (): void {
        expect(Salary::tryFrom(0, 0, 'USD'))->toBeNull()
            ->and(Salary::tryFrom(90000, 70000, 'usd')?->min)->toBe(70000.0)
            ->and(Salary::tryFrom('50000', null, 'not-a-code')?->currency)->toBeNull();
    });
});

describe('label mapping', function (): void {
    it('maps employment type labels from every source', function (?string $label, ?EmploymentType $expected): void {
        expect(EmploymentType::fromLabel($label))->toBe($expected);
    })->with([
        ['Full-Time', EmploymentType::FullTime],
        ['full_time', EmploymentType::FullTime],
        ['FullTime', EmploymentType::FullTime],
        ['permanent', EmploymentType::FullTime],
        ['Unbefristeter arbeitsvertrag', EmploymentType::FullTime],
        ['Part Time', EmploymentType::PartTime],
        ['Contract', EmploymentType::Contract],
        ['Freelance', EmploymentType::Freelance],
        ['Intern', EmploymentType::Internship],
        ['Befristeter Arbeitsvertrag', EmploymentType::Temporary],
        ['Something else', null],
        [null, null],
    ]);

    it('maps seniority from titles without confusing sales executives with C-level', function (string $title, ?Seniority $expected): void {
        expect(Seniority::fromLabel($title))->toBe($expected);
    })->with([
        ['Senior Backend Engineer', Seniority::Senior],
        ['Sr. Designer', Seniority::Senior],
        ['Junior Developer', Seniority::Junior],
        ['Staff Engineer', Seniority::Lead],
        ['Engineering Manager', Seniority::Manager],
        ['Director of Sales', Seniority::Director],
        ['VP of Engineering', Seniority::Executive],
        ['Account Executive - France', null],
        ['Software Engineering Intern', Seniority::Intern],
        ['Backend Engineer', null],
    ]);
});
