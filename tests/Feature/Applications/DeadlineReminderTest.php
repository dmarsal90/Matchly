<?php

declare(strict_types=1);

use App\Modules\Applications\Application\Actions\SendDeadlineReminders;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use App\Modules\Applications\Infrastructure\Models\Application;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Notifications\Application\Notifications\DeadlineReminderNotification;
use Illuminate\Support\Facades\Notification;

function withDeadline(User $user, int $inDays, ApplicationStatus $status = ApplicationStatus::Prepared): Application
{
    $application = new Application;
    $application->forceFill([
        'user_id' => $user->id,
        'job_listing_id' => JobListing::factory()->create()->id,
        'status' => $status,
        'status_changed_at' => now(),
        'deadline_on' => now()->addDays($inDays)->toDateString(),
    ])->save();

    return $application;
}

it('warns two days before a deadline, once, and not for other dates or closed applications', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $soon = withDeadline($user, 2);
    withDeadline($user, 5);
    withDeadline($user, -1);
    withDeadline($user, 1, ApplicationStatus::Rejected);

    expect(app(SendDeadlineReminders::class)->handle())->toBe(1);
    Notification::assertSentTo($user, DeadlineReminderNotification::class, fn (DeadlineReminderNotification $n): bool => $n->applicationId === $soon->id && $n->daysLeft === 2);
    expect(app(SendDeadlineReminders::class)->handle())->toBe(0);
});

it('lets the person set, change and clear the date, which earns a fresh warning', function (): void {
    $user = User::factory()->create();
    $application = withDeadline($user, 1);
    $application->forceFill(['deadline_reminded_at' => now()])->save();

    $this->actingAs($user)->patch("/applications/{$application->id}/deadline", ['deadline' => now()->addDays(9)->toDateString()])->assertRedirect();
    expect($application->refresh()->deadline_on->toDateString())->toBe(now()->addDays(9)->toDateString())
        ->and($application->deadline_reminded_at)->toBeNull();

    $this->actingAs($user)->patch("/applications/{$application->id}/deadline", ['deadline' => ''])->assertRedirect();
    expect($application->refresh()->deadline_on)->toBeNull();

    $this->actingAs($user)->patch("/applications/{$application->id}/deadline", ['deadline' => 'tomorrow'])->assertSessionHasErrors('deadline');
});

it('keeps other people from touching someone else\'s deadline', function (): void {
    $application = withDeadline(User::factory()->create(), 3);

    $this->actingAs(User::factory()->create())->patch("/applications/{$application->id}/deadline", ['deadline' => now()->toDateString()])->assertForbidden();
});

it('sends the board the deadline, stores the notification and e-mails only with consent', function (): void {
    $user = User::factory()->create(['locale' => 'es']);
    $application = withDeadline($user, 2);

    $this->actingAs($user)->get('/applications')->assertInertia(fn ($page) => $page->where('applications.0.deadline_on', $application->deadline_on->toDateString())->etc());

    $notification = new DeadlineReminderNotification($application->id, $application->deadline_on->toDateString(), 2);
    $user->notify($notification);
    $data = $user->notifications()->sole()->data;
    expect($data)->toMatchArray(['type' => 'deadline', 'days_left' => 2, 'company' => $application->job->company])
        ->and($notification->via($user))->not->toContain('mail');

    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);
    $html = (string) $notification->toMail($user)->render();
    expect($notification->via($user))->toContain('mail')->and($html)->toContain($application->job->title)->toContain('no avisamos')
        ->and($notification->toBroadcast($user)->data['type'])->toBe('deadline');
});

it('runs from the artisan command', function (): void {
    Notification::fake();
    withDeadline(User::factory()->create(), 1);

    $this->artisan('applications:deadlines')->expectsOutputToContain('Sent 1 deadline reminders')->assertSuccessful();
});
