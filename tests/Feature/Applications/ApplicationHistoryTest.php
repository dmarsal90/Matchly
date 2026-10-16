<?php

declare(strict_types=1);

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Applications\Infrastructure\Models\ApplicationEvent;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Illuminate\Support\Facades\DB;

function trackedApplication(User $user, ApplicationStatus $status = ApplicationStatus::Saved): Application
{
    $job = JobListing::factory()->create();
    test()->actingAs($user)->post("/jobs/{$job->id}/save");
    $application = Application::query()->where('user_id', $user->id)->sole();
    if ($status !== ApplicationStatus::Saved) {
        $application->forceFill(['status' => $status])->save();
    }

    return $application;
}

it('records an event when a job is saved and when its status changes', function (): void {
    $user = User::factory()->create();
    $application = trackedApplication($user);

    $this->actingAs($user)->patch("/applications/{$application->id}", ['status' => 'applied', 'notes' => null]);
    $this->actingAs($user)->patch("/applications/{$application->id}", ['status' => 'applied', 'notes' => 'same status, new note']);
    $this->actingAs($user)->patch("/applications/{$application->id}", ['status' => 'offer', 'notes' => null]);

    expect($application->events->map(fn (ApplicationEvent $e): string => "{$e->type->value}:{$e->status->value}")->all())
        ->toBe(['status_changed:saved', 'status_changed:applied', 'status_changed:offer']);
});

it('accepts the new closing statuses', function (string $status): void {
    $user = User::factory()->create();
    $application = trackedApplication($user);

    $this->actingAs($user)->patch("/applications/{$application->id}", ['status' => $status, 'notes' => null])->assertRedirect();

    expect($application->refresh()->status->value)->toBe($status);
})->with(['offer', 'accepted', 'declined', 'rejected', 'no_response']);

it('numbers interview rounds and moves the application to interview', function (): void {
    $user = User::factory()->create();
    $application = trackedApplication($user, ApplicationStatus::Applied);

    $this->actingAs($user)->post("/applications/{$application->id}/events", ['type' => 'interview', 'note' => 'HR screen', 'occurred_at' => '2026-10-10'])->assertRedirect();
    $this->actingAs($user)->post("/applications/{$application->id}/events", ['type' => 'interview', 'note' => 'Technical', 'occurred_at' => '2026-10-14']);

    $rounds = $application->events()->where('type', 'interview')->pluck('round')->all();
    expect($rounds)->toBe([1, 2])
        ->and($application->refresh()->status)->toBe(ApplicationStatus::Interview)
        ->and($application->events()->where('type', 'interview')->first()->occurred_at->toDateString())->toBe('2026-10-10');
});

it('does not move an application that is already past interview back to interview', function (): void {
    $user = User::factory()->create();
    $application = trackedApplication($user, ApplicationStatus::Offer);

    $this->actingAs($user)->post("/applications/{$application->id}/events", ['type' => 'interview']);

    expect($application->refresh()->status)->toBe(ApplicationStatus::Offer);
});

it('keeps event notes encrypted', function (): void {
    $user = User::factory()->create();
    $application = trackedApplication($user);

    $this->actingAs($user)->post("/applications/{$application->id}/events", ['type' => 'note', 'note' => 'Salary band 60-70k']);

    expect($application->events()->where('type', 'note')->sole()->note)->toBe('Salary band 60-70k')
        ->and(DB::table('application_events')->where('type', 'note')->value('note'))->not->toContain('Salary');
});

it('only lets the owner add events and only of the manual types', function (): void {
    $owner = User::factory()->create();
    $application = trackedApplication($owner);

    $this->actingAs(User::factory()->create())->post("/applications/{$application->id}/events", ['type' => 'note', 'note' => 'x'])->assertForbidden();
    $this->actingAs($owner)->post("/applications/{$application->id}/events", ['type' => 'status_changed'])->assertSessionHasErrors('type');
    $this->actingAs($owner)->post("/applications/{$application->id}/events", ['type' => 'note', 'occurred_at' => '1999-01-01'])->assertSessionHasErrors('occurred_at');

    expect($application->events()->where('type', ApplicationEventType::Note->value)->count())->toBe(0);
});

it('deletes the history with the application and includes it in the data export', function (): void {
    $user = User::factory()->create();
    $application = trackedApplication($user);
    $this->actingAs($user)->post("/applications/{$application->id}/events", ['type' => 'follow_up', 'note' => 'Sent an email']);

    $response = $this->actingAs($user)->get('/settings/privacy/export');
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $data = json_decode((string) $zip->getFromName('data.json'), true);
    $history = $data['sections']['applications']['applications'][0]['history'];

    expect(array_column($history, 'type'))->toBe(['status_changed', 'follow_up'])
        ->and($history[1]['note'])->toBe('Sent an email');

    $this->actingAs($user)->delete("/applications/{$application->id}");

    expect(ApplicationEvent::query()->count())->toBe(0);
});
