<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\Consent;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\Profile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    config(['privacy.files_disk' => 'local']);
});

function userWithCv(): User
{
    $user = User::factory()->create();
    test()->actingAs($user)->post('/profile/cv', [
        'file' => UploadedFile::fake()->createWithContent('cv.pdf', makePdf('Platform engineer')),
        'consent' => '1',
    ]);

    return $user;
}

it('lists every consent purpose with its state', function (): void {
    $user = User::factory()->create();
    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);

    $this->actingAs($user)->get('/settings/privacy')->assertInertia(fn (Assert $page) => $page
        ->component('settings/Privacy')
        ->has('consents', count(ConsentPurpose::cases()))
        ->where('consents.5.purpose', 'email_alerts')
        ->where('consents.5.granted', true)
        ->where('consents.0.granted', false));
});

it('grants and revokes consent keeping the history', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/privacy/consent', ['purpose' => 'ai_processing', 'granted' => true])->assertRedirect();
    expect($user->hasConsented(ConsentPurpose::AiProcessing))->toBeTrue();

    $this->actingAs($user)->put('/settings/privacy/consent', ['purpose' => 'ai_processing', 'granted' => false]);

    expect($user->hasConsented(ConsentPurpose::AiProcessing))->toBeFalse()
        ->and(Consent::query()->where('user_id', $user->id)->whereNotNull('revoked_at')->count())->toBe(1);
});

it('re-asks consent when the policy version changes', function (): void {
    $user = User::factory()->create();
    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);

    config(['privacy.policy_version' => '2027-01']);
    app(GrantConsent::class)->handle($user, ConsentPurpose::EmailAlerts);

    expect(Consent::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(Consent::query()->whereNull('revoked_at')->value('policy_version'))->toBe('2027-01');
});

it('deletes CVs when CV processing consent is withdrawn', function (): void {
    $user = userWithCv();
    $path = CvDocument::query()->sole()->path;

    $this->actingAs($user)->put('/settings/privacy/consent', ['purpose' => 'cv_processing', 'granted' => false]);

    expect(CvDocument::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('exports everything stored about the user', function (): void {
    $user = userWithCv();
    $this->actingAs($user)->put('/profile', ['headline' => 'Platform engineer', 'languages' => [], 'work_modes' => [], 'employment_types' => []]);

    $response = $this->actingAs($user)->get('/settings/privacy/export')->assertOk();

    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $data = json_decode((string) $zip->getFromName('data.json'), true);
    $cvFile = $zip->getFromIndex(array_search(true, array_map(fn (int $i): bool => str_starts_with((string) $zip->getNameIndex($i), 'files/profile/'), range(0, $zip->numFiles - 1)), true));

    expect($data['sections']['account']['email'])->toBe($user->email)
        ->and($data['sections']['account']['consents'][0]['purpose'])->toBe('cv_processing')
        ->and($data['sections']['profile']['profile']['headline'])->toBe('Platform engineer')
        ->and($data['sections']['profile']['cv_documents'][0]['extracted_text'])->toContain('Platform engineer')
        ->and($cvFile)->toStartWith('%PDF-')
        ->and(json_encode($data))->not->toContain('password')->not->toContain('token');
});

it('rate limits exports', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 3) as $_) {
        $this->actingAs($user)->get('/settings/privacy/export')->assertOk();
    }

    $this->actingAs($user)->get('/settings/privacy/export')->assertTooManyRequests();
});

it('erases the account, its files and every related row', function (): void {
    $user = userWithCv();
    $this->actingAs($user)->put('/profile', ['headline' => 'x', 'languages' => [], 'work_modes' => [], 'employment_types' => []]);
    $path = CvDocument::query()->sole()->path;

    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertRedirect('/');

    $this->assertGuest();
    expect(User::query()->count())->toBe(0)
        ->and(Profile::query()->count())->toBe(0)
        ->and(CvDocument::query()->count())->toBe(0)
        ->and(Consent::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('lets passwordless accounts delete themselves by confirming their e-mail', function (): void {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)->delete('/settings/profile', ['email_confirmation' => 'wrong@example.com'])->assertSessionHasErrors('email_confirmation');
    $this->actingAs($user)->delete('/settings/profile', ['email_confirmation' => $user->email])->assertRedirect('/');

    expect(User::query()->count())->toBe(0);
});

it('applies retention rules', function (): void {
    $stale = User::factory()->unverified()->create(['created_at' => now()->subDays(31)]);
    $recent = User::factory()->unverified()->create();
    $inactive = userWithCv();
    $inactive->forceFill(['last_active_at' => now()->subMonths(25)])->save();

    $this->artisan('privacy:prune')->assertSuccessful();

    expect(User::query()->find($stale->id))->toBeNull()
        ->and(User::query()->find($recent->id))->not->toBeNull()
        ->and(CvDocument::query()->sole()->extracted_text)->toBeNull();
});

it('records activity at most once a day', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard');
    $first = $user->refresh()->last_active_at;
    $this->travel(2)->hours();
    $this->actingAs($user)->get('/dashboard');

    expect($first)->not->toBeNull()->and($user->refresh()->last_active_at?->equalTo($first))->toBeTrue();
});
