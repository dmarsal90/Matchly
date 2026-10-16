<?php

declare(strict_types=1);

use App\Modules\Applications\Application\Actions\SendFollowUpReminders;
use App\Modules\Applications\Application\Generators\FollowUpDraftWriter;
use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Notifications\Application\Notifications\FollowUpReminderNotification;
use Illuminate\Support\Facades\Notification;

function appliedApplication(User $user, int $daysAgo, ApplicationStatus $status = ApplicationStatus::Applied): Application
{
    $application = new Application;
    $application->forceFill([
        'user_id' => $user->id,
        'job_listing_id' => JobListing::factory()->create()->id,
        'status' => $status,
        'status_changed_at' => now()->subDays($daysAgo),
        'applied_at' => now()->subDays($daysAgo),
    ])->save();

    return $application;
}

it('reminds once about applications with no answer after the configured days', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $old = appliedApplication($user, 11);
    appliedApplication($user, 3);

    expect(app(SendFollowUpReminders::class)->handle())->toBe(1);
    Notification::assertSentTo($user, FollowUpReminderNotification::class, fn (FollowUpReminderNotification $n): bool => $n->applicationId === $old->id && $n->days === 10);
    expect($old->refresh()->follow_up_reminded_at)->not->toBeNull()
        ->and(app(SendFollowUpReminders::class)->handle())->toBe(0);
});

it('skips applications that already moved on, were followed up, or whose job closed', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    appliedApplication($user, 20, ApplicationStatus::Interview);
    appliedApplication($user, 20, ApplicationStatus::Rejected);
    $followed = appliedApplication($user, 20);
    $followed->events()->create(['type' => ApplicationEventType::FollowUp, 'occurred_at' => now()->subDays(2)]);
    $closed = appliedApplication($user, 20);
    $closed->job->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

    expect(app(SendFollowUpReminders::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('runs from the artisan command with a custom number of days', function (): void {
    Notification::fake();
    appliedApplication(User::factory()->create(), 5);

    $this->artisan('applications:follow-up', ['--days' => 4])->expectsOutputToContain('Sent 1 follow-up reminders')->assertSuccessful();
});

it('writes a draft in the language of the user and never sends it', function (): void {
    $user = User::factory()->create(['name' => 'Ana Ruiz', 'locale' => 'es']);
    $application = appliedApplication($user, 12);
    $application->load('job', 'user');

    $draft = app(FollowUpDraftWriter::class)->write($application);

    expect($draft)->toContain($application->job->company)->toContain($application->job->title)->toContain('Ana Ruiz')->toContain('Me presenté');
});

it('stores the reminder for the notification centre without the draft', function (): void {
    $user = User::factory()->create();
    $application = appliedApplication($user, 12);

    $user->notify(new FollowUpReminderNotification($application->id, 10));

    $data = $user->notifications()->sole()->data;
    expect($data['type'])->toBe('follow_up')->and($data['company'])->toBe($application->job->company)->and($data)->not->toHaveKey('draft');
});

it('shows the history and a follow-up draft on the board for applied applications only', function (): void {
    $user = User::factory()->create();
    $applied = appliedApplication($user, 12);
    $applied->events()->create(['type' => ApplicationEventType::Note, 'note' => 'Spoke to HR', 'occurred_at' => now()->subDays(5)]);
    appliedApplication($user, 12, ApplicationStatus::Interview);

    $this->actingAs($user)->get('/applications')->assertInertia(fn ($page) => $page
        ->has('applications', 2)
        ->where('applications.0.follow_up_draft', fn ($draft): bool => is_string($draft) || $draft === null)
        ->etc());

    $props = $this->actingAs($user)->get('/applications')->viewData('page')['props']['applications'];
    $byId = collect($props)->keyBy('id');
    expect($byId[$applied->id]['follow_up_draft'])->toBeString()
        ->and($byId[$applied->id]['events'][0]['note'])->toBe('Spoke to HR')
        ->and($byId->except($applied->id)->first()['follow_up_draft'])->toBeNull();
});

it('e-mails the reminder with the draft only to people who consented', function (): void {
    $user = User::factory()->create(['name' => 'Ana Ruiz', 'locale' => 'es']);
    $application = appliedApplication($user, 12);
    $notification = new FollowUpReminderNotification($application->id, 10);

    expect($notification->via($user))->not->toContain('mail');

    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);
    $mail = $notification->toMail($user);
    $html = (string) $mail->render();

    expect($notification->via($user))->toContain('mail')
        ->and($mail->subject)->toContain($application->job->company)
        ->and($html)->toContain('Me presenté')->toContain('Ana Ruiz')->toContain('nunca contactamos')
        ->and($notification->toBroadcast($user)->data['type'])->toBe('follow_up');
});
