<?php

declare(strict_types=1);

use App\Modules\Alerts\Application\Actions\SendRadarNotifications;
use App\Modules\Alerts\Infrastructure\Models\FollowedCompany;
use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use App\Modules\Notifications\Application\Notifications\RadarNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function followed(User $user, string $company, string $checkedDaysAgo = '1 day'): FollowedCompany
{
    $follow = new FollowedCompany(['company_key' => App\Modules\Applications\Domain\Services\CompanyKey::from($company), 'company' => $company, 'last_checked_at' => now()->sub($checkedDaysAgo)]);
    $follow->user()->associate($user);
    $follow->save();

    return $follow;
}

function seenAgo(string $company, string $ago = '2 hours', array $state = []): JobListing
{
    return JobListing::factory()->create(['company' => $company, 'first_seen_at' => now()->sub($ago), ...$state]);
}

it('follows and unfollows a company, one entry however it is written', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('radar.store'), ['company' => 'ACME, Inc.'])->assertRedirect();
    $this->post(route('radar.store'), ['company' => 'Acme'])->assertRedirect();
    $this->post(route('radar.store'), ['company' => ''])->assertSessionHasErrors('company');

    $follow = FollowedCompany::query()->sole();
    expect($follow->company)->toBe('ACME, Inc.');
    $this->get(route('radar.index'))->assertInertia(fn (Assert $page) => $page->component('alerts/Radar')->has('companies', 1));

    $job = seenAgo('ACME Inc');
    $this->get(route('jobs.show', $job))->assertInertia(fn (Assert $page) => $page->where('followedCompanyId', $follow->id));

    $this->delete(route('radar.destroy', $follow))->assertRedirect();
    expect(FollowedCompany::query()->count())->toBe(0);
});

it('does not let anyone remove somebody else\'s follow', function (): void {
    $follow = followed(User::factory()->create(), 'Acme');

    $this->actingAs(User::factory()->create())->delete(route('radar.destroy', $follow))->assertForbidden();
});

it('limits how many companies a person follows', function (): void {
    $user = User::factory()->create();
    foreach (range(1, 50) as $i) {
        followed($user, "Company {$i}");
    }

    $this->actingAs($user)->post(route('radar.store'), ['company' => 'One more'])->assertSessionHasErrors('company');
    $this->post(route('radar.store'), ['company' => 'Company 7'])->assertSessionHasNoErrors();
});

it('notifies once about new open jobs of a followed company and not about old, closed or other ones', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $follow = followed($user, 'Acme', '1 day');
    $new = seenAgo('ACME Inc.', '2 hours', ['title' => 'Backend Engineer']);
    seenAgo('Acme', '3 days', ['title' => 'Before the follow']);
    seenAgo('Acme', '1 hour', ['status' => 'closed', 'closed_at' => now()]);
    seenAgo('Globex', '1 hour');

    expect(app(SendRadarNotifications::class)->handle())->toBe(1);
    Notification::assertSentTo($user, RadarNotification::class, fn (RadarNotification $n): bool => $n->company === 'Acme' && $n->count === 1 && $n->jobs === [['id' => $new->id, 'title' => 'Backend Engineer']]);
    expect($follow->refresh()->last_notified_at)->not->toBeNull()
        // Checked now: the same listings are not news a second time.
        ->and(app(SendRadarNotifications::class)->handle())->toBe(0);
});

it('lists at most five jobs but counts them all, and keeps each follow separate', function (): void {
    Notification::fake();
    $ana = User::factory()->create();
    $luis = User::factory()->create();
    followed($ana, 'Acme', '1 day');
    followed($luis, 'Acme', '1 hour');
    foreach (range(1, 7) as $_) {
        seenAgo('Acme', '3 hours');
    }

    expect(app(SendRadarNotifications::class)->handle())->toBe(1);
    Notification::assertSentTo($ana, RadarNotification::class, fn (RadarNotification $n): bool => $n->count === 7 && count($n->jobs) === 5);
    Notification::assertNotSentTo($luis, RadarNotification::class);
});

it('stores the notice, e-mails only with consent and runs from artisan', function (): void {
    $user = User::factory()->create(['locale' => 'es']);
    $notification = new RadarNotification('Acme', 7, [['id' => seenAgo('Acme')->id, 'title' => 'Backend Engineer']]);

    $user->notify($notification);
    expect($user->notifications()->sole()->data)->toMatchArray(['type' => 'radar', 'company' => 'Acme', 'count' => 7])
        ->and($notification->via($user))->not->toContain('mail');

    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);
    $html = (string) $notification->toMail($user)->render();
    expect($notification->via($user))->toContain('mail')->and($html)->toContain('Backend Engineer')->toContain('Y 6 más')
        ->and($notification->toBroadcast($user)->data['type'])->toBe('radar');

    $this->artisan('alerts:radar')->expectsOutputToContain('Sent 0 radar notifications')->assertSuccessful();
});
