<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Domain\Enums\JobStatus;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('requires an account to save jobs', function (): void {
    $job = JobListing::factory()->create();

    $this->post("/jobs/{$job->id}/save")->assertRedirect(route('login'));
});

it('toggles a saved job', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create();

    $this->actingAs($user)->post("/jobs/{$job->id}/save")->assertRedirect();
    expect(Application::query()->sole()->status)->toBe(ApplicationStatus::Saved);

    $this->actingAs($user)->post("/jobs/{$job->id}/save");
    expect(Application::query()->count())->toBe(0);
});

it('refuses to save jobs held for moderation', function (): void {
    $job = JobListing::factory()->create(['status' => JobStatus::Flagged, 'title' => 'Suspicious offer']);

    $this->actingAs(User::factory()->create())->post("/jobs/{$job->id}/save")->assertNotFound();

    expect(Application::query()->count())->toBe(0);
});

it('never lists flagged jobs in the pipeline', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create();
    $this->actingAs($user)->post("/jobs/{$job->id}/save");
    $job->forceFill(['status' => JobStatus::Flagged])->save();

    $this->actingAs($user)->get('/applications')->assertInertia(fn (Assert $page) => $page->has('applications', 0));
});

it('lists the pipeline with job details', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create(['title' => 'Staff Engineer']);
    $this->actingAs($user)->post("/jobs/{$job->id}/save");

    $this->actingAs($user)->get('/applications')->assertInertia(fn (Assert $page) => $page
        ->component('applications/Index')
        ->has('applications', 1)
        ->where('applications.0.job.title', 'Staff Engineer')
        ->where('applications.0.status', 'saved')
        ->where('statuses', ['saved', 'prepared', 'applied', 'interview', 'rejected']));
});

it('moves an application through the pipeline and keeps private notes encrypted', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create();
    $this->actingAs($user)->post("/jobs/{$job->id}/save");
    $application = Application::query()->sole();

    $this->actingAs($user)->patch("/applications/{$application->id}", ['status' => 'applied', 'notes' => 'Recruiter: Ana'])->assertRedirect();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Applied)
        ->and($application->applied_at)->not->toBeNull()
        ->and($application->notes)->toBe('Recruiter: Ana')
        ->and(DB::table('applications')->value('notes'))->not->toContain('Ana');
});

it('protects other users applications', function (string $method): void {
    $owner = User::factory()->create();
    $job = JobListing::factory()->create();
    $this->actingAs($owner)->post("/jobs/{$job->id}/save");
    $application = Application::query()->sole();

    $this->actingAs(User::factory()->create())
        ->{$method}("/applications/{$application->id}", ['status' => 'rejected'])
        ->assertForbidden();

    expect($application->refresh()->status)->toBe(ApplicationStatus::Saved);
})->with(['patch', 'delete']);

it('includes applications in the data export', function (): void {
    $user = User::factory()->create();
    $job = JobListing::factory()->create(['title' => 'Exported role']);
    $this->actingAs($user)->post("/jobs/{$job->id}/save");

    $response = $this->actingAs($user)->get('/settings/privacy/export');
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $data = json_decode((string) $zip->getFromName('data.json'), true);

    expect($data['sections']['applications']['applications'][0]['job']['title'])->toBe('Exported role');
});
