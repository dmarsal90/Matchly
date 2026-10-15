<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Alerts\Application\Actions\MatchNewJobsToAlerts;
use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\EmploymentType;
use App\Modules\Jobs\Domain\Enums\JobCategory;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\RemoteScope;
use App\Modules\Jobs\Domain\Enums\Seniority;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Matching\Application\Actions\ScoreJobsForUser;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Providers\Infrastructure\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Fixed data for the Playwright suite (docker/scripts/e2e.sh). The specs rely on
 * these names, so change both together. Every account uses the password "password".
 */
final class E2eSeeder extends Seeder
{
    public const string CANDIDATE = 'candidate@e2e.test';

    public const string NEWCOMER = 'newcomer@e2e.test';

    public const string DAILY_ALERT = 'Backend en remoto';

    public function run(GrantConsent $grantConsent, ScoreJobsForUser $scoreJobs, MatchNewJobsToAlerts $matchAlerts): void
    {
        $this->seedJobs();

        $candidate = $this->candidate();
        foreach ([ConsentPurpose::LinkedinImport, ConsentPurpose::EmailAlerts] as $purpose) {
            $grantConsent->handle($candidate, $purpose);
        }
        $scoreJobs->handle($candidate);

        // A daily alert with matches waiting: the alerts spec sends the digest.
        $candidate->savedSearches()->create([
            'name' => self::DAILY_ALERT,
            'filters' => ['role' => JobRole::Backend->value],
            'frequency' => AlertFrequency::Daily,
            'email' => true,
        ]);
        $matchAlerts->handle(array_values(array_map(intval(...), JobListing::query()->visible()->pluck('id')->all())));

        User::factory()->create(['name' => 'Nora Newcomer', 'email' => self::NEWCOMER, 'locale' => 'es']);
    }

    private function seedJobs(): void
    {
        $source = Source::query()->create([
            'key' => 'e2e_board',
            'name' => 'E2E Board',
            'homepage_url' => 'https://example.com',
        ]);

        $jobs = [
            ['Desarrollador Backend Senior', 'Globex', JobRole::Backend, JobCategory::SoftwareDevelopment, Seniority::Senior, EmploymentType::FullTime, ['php', 'laravel', 'postgresql'], 2,
                '<p>Buscamos una persona con experiencia en Laravel y PostgreSQL para el equipo de la empresa. Valoramos Kubernetes.</p>'],
            ['Senior Laravel Engineer', 'Initech', JobRole::Backend, JobCategory::SoftwareDevelopment, Seniority::Senior, EmploymentType::Contract, ['php', 'laravel', 'redis'], 1,
                '<p>Build and maintain Laravel APIs backed by PostgreSQL and Redis.</p>'],
            ['Frontend Developer (Vue)', 'Umbrella', JobRole::Frontend, JobCategory::SoftwareDevelopment, Seniority::Mid, EmploymentType::FullTime, ['vue', 'typescript'], 3,
                '<p>Ship accessible Vue 3 interfaces with TypeScript and Tailwind CSS.</p>'],
            ['Junior React Developer', 'Hooli', JobRole::Frontend, JobCategory::SoftwareDevelopment, Seniority::Junior, EmploymentType::FullTime, ['react', 'javascript'], 5,
                '<p>Join a mentoring-first team building React components.</p>'],
            ['Product Designer', 'Stark Industries', JobRole::Designer, JobCategory::Design, Seniority::Senior, EmploymentType::FullTime, ['figma', 'ux'], 4,
                '<p>Own the design system and run user research with Figma.</p>'],
            ['DevOps Engineer', 'Wayne Enterprises', JobRole::DevOps, JobCategory::DevOps, Seniority::Mid, EmploymentType::FullTime, ['kubernetes', 'terraform', 'aws'], 6,
                '<p>Operate Kubernetes clusters on AWS with Terraform.</p>'],
            ['Data Scientist', 'Soylent', JobRole::DataScientist, JobCategory::Data, Seniority::Senior, EmploymentType::PartTime, ['python', 'machine-learning'], 8,
                '<p>Model churn with Python and scikit-learn.</p>'],
            ['Customer Support Specialist', 'Vandelay', JobRole::CustomerSupport, JobCategory::CustomerSupport, Seniority::Junior, EmploymentType::FullTime, ['zendesk'], 10,
                '<p>Help customers by chat and e-mail in English and Spanish.</p>'],
        ];

        foreach ($jobs as [$title, $company, $role, $category, $seniority, $type, $tags, $daysAgo, $description]) {
            JobListing::factory()->postedDaysAgo($daysAgo)->create([
                'source_id' => $source->id,
                'title' => $title,
                'company' => $company,
                'description' => $description,
                'role' => $role,
                'category' => $category,
                'seniority' => $seniority,
                'employment_type' => $type,
                'remote_scope' => RemoteScope::Worldwide,
                'tags' => $tags,
            ]);
        }

        // Neither may show up: one is past the freshness window, the other closed.
        JobListing::factory()->postedDaysAgo(45)->create([
            'source_id' => $source->id, 'title' => 'Stale Backend Role', 'company' => 'Oldco',
        ]);
        JobListing::factory()->closed()->create([
            'source_id' => $source->id, 'title' => 'Closed Backend Role', 'company' => 'Goneco',
        ]);
    }

    private function candidate(): User
    {
        $user = User::factory()->create(['name' => 'Ana García', 'email' => self::CANDIDATE, 'locale' => 'es']);

        $user->profile()->create([
            'headline' => 'Backend engineer',
            'phone' => '+34 600 000 000',
            'city' => 'Valencia',
            'country' => 'ES',
            'target_roles' => [JobRole::Backend->value],
            'seniority' => Seniority::Senior,
            'years_experience' => 6,
            'languages' => [['code' => 'es', 'level' => 'native'], ['code' => 'en', 'level' => 'c1']],
            'salary_min' => 60000,
            'salary_currency' => 'EUR',
            'salary_period' => 'year',
            'onboarding_completed_at' => CarbonImmutable::now(),
        ]);

        $user->profileImports()->create([
            'source' => SkillSource::Linkedin,
            'status' => ImportStatus::Confirmed,
            'confirmed_at' => CarbonImmutable::now(),
            'payload' => [
                'profile' => ['summary' => 'Backend developer focused on APIs.'],
                'positions' => [[
                    'title' => 'Senior Backend Engineer', 'company' => 'Acme', 'location' => null,
                    'description' => "Mentored two junior developers.\nBuilt REST APIs with Laravel and PostgreSQL.",
                    'started_on' => '2021-03', 'finished_on' => null,
                ]],
                'education' => [['school' => 'Universitat de València', 'degree' => 'Computer Engineering', 'started_on' => '2012', 'finished_on' => '2016']],
                'certifications' => [],
                'projects' => [],
            ],
        ]);

        foreach (['PHP' => 'php', 'Laravel' => 'laravel', 'PostgreSQL' => 'postgresql', 'Redis' => 'redis'] as $name => $slug) {
            $user->skills()->create(['source' => SkillSource::Declared, 'skill' => $slug, 'name' => $name, 'level' => 4, 'years' => 5]);
            $user->skills()->create(['source' => SkillSource::Linkedin, 'skill' => $slug, 'name' => $name]);
        }

        return $user;
    }
}
