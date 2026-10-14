<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\JobListings\Pages\ManageJobListings;
use App\Filament\Admin\Resources\Sources\Pages\ManageSources;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Actions\ModerateJob;
use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Enums\CloseReason;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Support\Facades\Event;

use function Pest\Livewire\livewire;

it('sends guests to the app login', function (): void {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('forbids non-admins', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('forbids admins until they verify their e-mail', function (): void {
    $this->actingAs(User::factory()->unverified()->create(['is_admin' => true]))->get('/admin')->assertForbidden();
});

it('lets admins into every section', function (string $path): void {
    JobListing::factory()->count(2)->create();

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get($path)->assertOk();
})->with(['/admin', '/admin/job-listings', '/admin/sources', '/admin/ats-companies', '/admin/users', '/admin/ingestion-runs']);

it('serves the admin panel with its own relaxed CSP only', function (): void {
    config(['security.csp.enabled' => true]);
    $admin = User::factory()->create(['is_admin' => true]);

    $adminPolicy = (string) $this->actingAs($admin)->get('/admin')->headers->get('Content-Security-Policy');
    $appPolicy = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($adminPolicy)->toContain("'unsafe-eval'")
        ->and($appPolicy)->not->toContain("'unsafe-eval'")->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('lists flagged jobs for review', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $flagged = JobListing::factory()->create(['status' => JobStatus::Flagged, 'flag_reasons' => ['upfront_payment']]);
    $open = JobListing::factory()->create();

    livewire(ManageJobListings::class)
        ->set('activeTab', 'flagged')
        ->assertCanSeeTableRecords([$flagged])
        ->assertCanNotSeeTableRecords([$open]);
});

it('renders the sources table', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    JobListing::factory()->create();

    livewire(ManageSources::class)->assertSuccessful();
});

it('publishes an approved job and announces it', function (): void {
    Event::fake([JobsIngested::class]);
    $job = JobListing::factory()->create(['status' => JobStatus::Flagged, 'flag_reasons' => ['off_platform_contact']]);

    app(ModerateJob::class)->approve($job);

    expect($job->refresh()->status)->toBe(JobStatus::Open)->and($job->flag_reasons)->toBeNull();
    Event::assertDispatched(JobsIngested::class, fn (JobsIngested $event): bool => $event->jobIds === [$job->id]);
});

it('closes a rejected job as moderated', function (): void {
    $job = JobListing::factory()->create(['status' => JobStatus::Flagged]);

    app(ModerateJob::class)->reject($job);

    expect($job->refresh()->status)->toBe(JobStatus::Closed)->and($job->close_reason)->toBe(CloseReason::Moderated);
});

it('grants admin rights from the console', function (): void {
    $user = User::factory()->create();

    $this->artisan('users:admin', ['email' => $user->email])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});
