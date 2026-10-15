<?php

declare(strict_types=1);

use App\Modules\Alerts\Domain\Enums\AlertFrequency;
use App\Modules\Alerts\Infrastructure\Models\AlertDelivery;
use App\Modules\Alerts\Infrastructure\Models\SavedSearch;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Application\Events\JobsIngested;
use App\Modules\Jobs\Domain\Enums\JobRole;
use App\Modules\Jobs\Domain\Enums\WorkMode;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Notifications\Application\Notifications\JobAlertNotification;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function alertFor(User $user, array $attributes = []): SavedSearch
{
    return $user->savedSearches()->create([
        'name' => 'Laravel remote',
        'filters' => ['q' => 'laravel', 'work_mode' => 'remote'],
        'frequency' => AlertFrequency::Immediate,
        'email' => true,
        ...$attributes,
    ]);
}

function emailConsent(User $user): User
{
    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);

    return $user;
}

function newJob(array $attributes = []): JobListing
{
    return JobListing::factory()->create(['title' => 'Senior Laravel Developer', 'work_mode' => WorkMode::Remote, 'tags' => [], ...$attributes]);
}

describe('managing alerts', function (): void {
    it('creates an alert from the current search, asking for e-mail consent once', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/alerts', ['name' => 'Vue', 'filters' => ['q' => 'vue'], 'frequency' => 'daily', 'email' => true])
            ->assertSessionHasErrors('email_consent');

        $this->post('/alerts', [
            'name' => '  Vue remote  ',
            'filters' => ['q' => 'vue', 'work_mode' => 'remote', 'page' => '4', 'sort' => 'salary'],
            'frequency' => 'daily',
            'email' => true,
            'email_consent' => '1',
        ])->assertSessionHasNoErrors();

        $alert = SavedSearch::query()->sole();
        expect($alert->name)->toBe('Vue remote')
            ->and($alert->filters)->toBe(['q' => 'vue', 'work_mode' => 'remote'])
            ->and($alert->frequency)->toBe(AlertFrequency::Daily)
            ->and($user->acceptsEmailAlerts())->toBeTrue();

        // Consent is not asked again.
        $this->post('/alerts', ['name' => 'Second', 'filters' => [], 'frequency' => 'weekly', 'email' => true])->assertSessionHasNoErrors();
    });

    it('limits the number of alerts', function (): void {
        $user = User::factory()->create();
        foreach (range(1, 20) as $index) {
            alertFor($user, ['name' => "Alert {$index}", 'email' => false]);
        }

        $this->actingAs($user)->post('/alerts', ['name' => 'One more', 'filters' => [], 'frequency' => 'daily', 'email' => false])->assertSessionHasErrors('name');
    });

    it('lists, pauses, edits and deletes alerts', function (): void {
        $user = emailConsent(User::factory()->create());
        $alert = alertFor($user);

        $this->actingAs($user)->get('/alerts')->assertInertia(fn (Assert $page) => $page
            ->component('alerts/Index')
            ->where('alerts.0.name', 'Laravel remote')
            ->where('alerts.0.filters', ['q' => 'laravel', 'work_mode' => 'remote'])
            ->where('emailConsented', true));

        $this->put("/alerts/{$alert->id}", ['active' => false, 'frequency' => 'weekly', 'email' => false])->assertSessionHasNoErrors();
        $alert->refresh();
        expect($alert->active)->toBeFalse()->and($alert->frequency)->toBe(AlertFrequency::Weekly)->and($alert->email)->toBeFalse();

        $this->delete("/alerts/{$alert->id}");
        expect(SavedSearch::query()->count())->toBe(0);
    });

    it('keeps alerts private', function (): void {
        $alert = alertFor(User::factory()->create());

        $this->actingAs(User::factory()->create());
        $this->put("/alerts/{$alert->id}", ['active' => false])->assertForbidden();
        $this->delete("/alerts/{$alert->id}")->assertForbidden();
    });
});

describe('matching new jobs', function (): void {
    beforeEach(fn () => Notification::fake());

    it('notifies immediate alerts once per job and only for matching jobs', function (): void {
        $user = emailConsent(User::factory()->create());
        $alert = alertFor($user);
        $match = newJob();
        $other = newJob(['title' => 'Product Designer', 'description' => '<p>Figma</p>', 'role' => JobRole::Designer]);
        $onsite = newJob(['work_mode' => WorkMode::Onsite]);

        event(new JobsIngested('remotive', [$match->id, $other->id, $onsite->id]));

        Notification::assertSentTo($user, JobAlertNotification::class, fn (JobAlertNotification $notification): bool => $notification->jobIds === [$match->id]
            && $notification->frequency === AlertFrequency::Immediate
            && $notification->savedSearchId === $alert->id);
        expect(AlertDelivery::query()->sole())->toMatchArray(['job_listing_id' => $match->id])
            ->and(AlertDelivery::query()->sole()->notified_at)->not->toBeNull()
            ->and($alert->fresh()->last_notified_at)->not->toBeNull();

        // The same job arriving again is never notified twice.
        event(new JobsIngested('remotive', [$match->id]));
        Notification::assertSentToTimes($user, JobAlertNotification::class, 1);
    });

    it('queues digest alerts without notifying yet', function (): void {
        $user = User::factory()->create();
        alertFor($user, ['frequency' => AlertFrequency::Daily]);
        $job = newJob();

        event(new JobsIngested('remotive', [$job->id]));

        Notification::assertNothingSent();
        expect(AlertDelivery::query()->sole()->notified_at)->toBeNull();
    });

    it('ignores paused alerts, unverified accounts and updated jobs', function (): void {
        alertFor(User::factory()->create(), ['active' => false]);
        alertFor(User::factory()->unverified()->create());
        $job = newJob();

        event(new JobsIngested('remotive', [$job->id]));
        event(new JobsIngested('remotive', [], [$job->id]));

        Notification::assertNothingSent();
        expect(AlertDelivery::query()->count())->toBe(0);
    });

    it('applies the minimum score of an alert, scoring new jobs first', function (): void {
        $user = User::factory()->create();
        $user->profile()->create(['target_roles' => ['backend']])->forceFill(['onboarding_completed_at' => now()])->save();
        $user->skills()->create(['source' => SkillSource::Github, 'skill' => 'laravel', 'name' => 'Laravel', 'level' => 5]);
        alertFor($user, ['filters' => ['min_score' => '60']]);
        $fit = newJob(['description' => '<p>We need Laravel.</p>']);
        $unfit = newJob(['title' => 'Nurse', 'role' => null, 'category' => null, 'description' => '<p>Clinical care, Spanish native.</p>', 'seniority' => null]);

        event(new JobsIngested('remotive', [$fit->id, $unfit->id]));

        Notification::assertSentTo($user, JobAlertNotification::class, fn (JobAlertNotification $notification): bool => $notification->jobIds === [$fit->id]);
    });
});

describe('digests', function (): void {
    beforeEach(fn () => Notification::fake());

    it('sends pending jobs of each alert once, leaving closed jobs out', function (): void {
        $user = User::factory()->create();
        $daily = alertFor($user, ['frequency' => AlertFrequency::Daily]);
        $weekly = alertFor($user, ['name' => 'Weekly', 'frequency' => AlertFrequency::Weekly]);
        $open = newJob();
        $closed = newJob(['status' => 'closed']);
        foreach ([$daily, $weekly] as $alert) {
            foreach ([$open, $closed] as $job) {
                AlertDelivery::query()->create(['saved_search_id' => $alert->id, 'job_listing_id' => $job->id, 'matched_at' => now()]);
            }
        }

        $this->artisan('alerts:digest daily')->assertSuccessful();

        Notification::assertSentToTimes($user, JobAlertNotification::class, 1);
        Notification::assertSentTo($user, JobAlertNotification::class, fn (JobAlertNotification $notification): bool => $notification->savedSearchId === $daily->id
            && $notification->jobIds === [$open->id]
            && $notification->frequency === AlertFrequency::Daily);
        expect($daily->deliveries()->whereNull('notified_at')->count())->toBe(0)
            ->and($weekly->deliveries()->whereNull('notified_at')->count())->toBe(2);

        $this->artisan('alerts:digest daily')->assertSuccessful();
        Notification::assertSentToTimes($user, JobAlertNotification::class, 1);

        $this->artisan('alerts:digest immediate')->assertFailed();
    });
});

describe('the notification', function (): void {
    it('picks its channels from the alert, the consent and the preferences', function (): void {
        $user = User::factory()->create();
        $alert = alertFor($user);
        $notification = new JobAlertNotification($alert->id, [], AlertFrequency::Immediate);

        expect($notification->via($user))->toBe(['database', 'broadcast']);

        emailConsent($user);
        expect($notification->via($user->fresh()))->toBe(['database', 'broadcast', 'mail']);

        $alert->update(['email' => false]);
        $user->forceFill(['notification_preferences' => ['realtime' => false]])->save();
        expect($notification->via($user->fresh()))->toBe(['database']);

        $alert->delete();
        expect($notification->via($user->fresh()))->toBe([]);
    });

    it('e-mails a localised digest with direct links and one-click unsubscribe', function (): void {
        $user = User::factory()->create(['locale' => 'es']);
        $alert = alertFor($user, ['name' => 'Laravel en remoto']);
        $jobs = collect(range(1, 12))->map(fn (int $index) => newJob(['title' => "Laravel Developer {$index}", 'company' => "Company {$index}"]));

        app()->setLocale('es');
        $mail = (new JobAlertNotification($alert->id, $jobs->pluck('id')->all(), AlertFrequency::Daily))->toMail($user);
        $html = (string) $mail->render();

        expect($mail->subject)->toBe('Tu resumen diario: 12 ofertas para «Laravel en remoto»')
            ->and($html)->toContain('Laravel Developer 1')->toContain(route('jobs.show', ['job' => $jobs[0]->id, 'slug' => Str::slug('Laravel Developer 1 Company 1')]))
            ->and($html)->toContain('y 2 ofertas más')
            ->and($html)->toContain('/alerts/'.$alert->id.'/unsubscribe?')
            ->and($html)->toContain('nunca enviamos candidaturas por ti');

        $message = new Symfony\Component\Mime\Email;
        foreach ($mail->callbacks as $callback) {
            $callback($message);
        }
        expect($message->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
            ->and($message->getHeaders()->get('List-Unsubscribe')?->getBodyAsString())->toContain('signature=');
    });

    it('stores a compact summary for the notification centre', function (): void {
        $user = User::factory()->create();
        $alert = alertFor($user);
        $job = newJob();

        $user->notifyNow(new JobAlertNotification($alert->id, [$job->id], AlertFrequency::Immediate), ['database']);

        expect($user->notifications()->sole()->data)->toMatchArray([
            'type' => 'job_alert',
            'alert_id' => $alert->id,
            'alert_name' => 'Laravel remote',
            'filters' => ['q' => 'laravel', 'work_mode' => 'remote'],
            'count' => 1,
            'jobs' => [['id' => $job->id, 'title' => 'Senior Laravel Developer', 'company' => $job->company, 'score' => null]],
        ]);
    });
});

describe('unsubscribing', function (): void {
    it('turns e-mail off through a signed link, without signing in', function (): void {
        $alert = alertFor(User::factory()->create());

        $this->get(route('alerts.unsubscribe', $alert))->assertForbidden();

        $this->get(URL::signedRoute('alerts.unsubscribe', ['savedSearch' => $alert->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('alerts/Unsubscribed')->where('name', 'Laravel remote'));

        expect($alert->fresh()->email)->toBeFalse()->and($alert->fresh()->active)->toBeTrue();
    });

    it('accepts one-click POSTs from mail clients without a CSRF token', function (): void {
        $alert = alertFor(User::factory()->create());

        $this->post(URL::signedRoute('alerts.unsubscribe.one-click', ['savedSearch' => $alert->id]))->assertNoContent();

        expect($alert->fresh()->email)->toBeFalse();
    });
});

describe('notification centre', function (): void {
    function notifiedUser(): User
    {
        $user = User::factory()->create();
        $alert = alertFor($user);
        $job = newJob();
        foreach (range(1, 3) as $_) {
            $user->notifyNow(new JobAlertNotification($alert->id, [$job->id], AlertFrequency::Immediate), ['database']);
        }

        return $user;
    }

    it('shares the unread count and lists notifications', function (): void {
        $user = notifiedUser();

        $this->actingAs($user)->get('/alerts')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 3));
        $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->component('notifications/Index')->has('notifications.data', 3));
        $this->getJson('/notifications/latest')->assertOk()->assertJsonPath('unread', 3)->assertJsonCount(3, 'notifications');
    });

    it('marks one or all as read, only the user\'s own', function (): void {
        $user = notifiedUser();
        $other = notifiedUser();
        $mine = $user->notifications()->first();

        $this->actingAs($user)->post("/notifications/{$other->notifications()->first()->id}/read")->assertNotFound();
        $this->post("/notifications/{$mine->id}/read")->assertRedirect();
        expect($user->unreadNotifications()->count())->toBe(2);

        $this->post('/notifications/read-all');
        expect($user->unreadNotifications()->count())->toBe(0)
            ->and($other->unreadNotifications()->count())->toBe(3);
    });

    it('lets the user choose e-mail and real-time notices', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page
            ->component('settings/Notifications')->where('email', false)->where('realtime', true));

        $this->put('/settings/notifications', ['email' => true, 'realtime' => false])->assertSessionHasNoErrors();
        expect($user->fresh()->acceptsEmailAlerts())->toBeTrue()->and($user->fresh()->wantsRealtimeNotifications())->toBeFalse();

        $this->put('/settings/notifications', ['email' => false, 'realtime' => true]);
        expect($user->fresh()->acceptsEmailAlerts())->toBeFalse();
    });

    it('only lets users listen on their own channel', function (): void {
        $user = User::factory()->create();
        $channels = Broadcast::driver()->getChannels();
        $callback = $channels['users.{id}'];

        expect($callback($user, $user->id))->toBeTrue()
            ->and($callback($user, $user->id + 1))->toBeFalse();
    });
});

it('exports alerts and notifications and erases notifications with the account', function (): void {
    $user = notifiedUser();

    $zip = new ZipArchive;
    $zip->open(app(App\Modules\Identity\Application\Actions\ExportPersonalData::class)->handle($user));
    $data = json_decode((string) $zip->getFromName('data.json'), true);
    expect($data['sections']['alerts']['saved_searches'][0]['name'])->toBe('Laravel remote')
        ->and($data['sections']['alerts']['notifications'])->toHaveCount(3);

    app(App\Modules\Identity\Application\Actions\DeleteAccount::class)->handle($user);
    expect(DB::table('notifications')->count())->toBe(0)->and(SavedSearch::query()->count())->toBe(0);
});

it('prunes read notifications after the retention period', function (): void {
    $user = notifiedUser();
    $user->notifications()->limit(2)->get()->each(fn ($notification) => $notification->forceFill(['read_at' => now(), 'created_at' => now()->subDays(91)])->save());

    $this->artisan('privacy:prune')->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
});
