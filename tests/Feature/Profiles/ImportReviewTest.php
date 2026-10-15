<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Actions\GrantConsent;
use App\Modules\Identity\Application\Actions\RevokeConsent;
use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Application\Events\ProfileChanged;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use App\Modules\Profiles\Infrastructure\Models\UserSkill;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function pendingLinkedInImport(User $user): ProfileImport
{
    $path = linkedinExportZip();
    test()->actingAs($user)->post('/profile/imports/linkedin', [
        'file' => UploadedFile::fake()->createWithContent('export.zip', (string) file_get_contents($path)),
        'consent' => '1',
    ]);
    @unlink($path);

    return ProfileImport::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

it('saves only what the user kept', function (): void {
    Event::fake([ProfileChanged::class]);
    $user = User::factory()->create();
    $import = pendingLinkedInImport($user);

    $this->post(route('skills.imports.confirm', $import), [
        'skills' => ['laravel', 'postgresql'],
        'items' => ['positions' => [0], 'education' => [], 'certifications' => [0], 'projects' => []],
        'headline' => 'Backend engineer (Laravel)',
        'languages' => [['code' => 'en', 'level' => 'C1']],
    ])->assertRedirect(route('skills.index'))->assertSessionHasNoErrors();

    $import->refresh();
    $skills = UserSkill::query()->where('source', SkillSource::Linkedin)->get()->keyBy('skill');

    expect($import->status)->toBe(ImportStatus::Confirmed)
        ->and($import->confirmed_at)->not->toBeNull()
        ->and($skills->keys()->sort()->values()->all())->toBe(['laravel', 'postgresql'])
        ->and($skills['laravel']->years)->toBe(5.5)
        ->and($skills['laravel']->evidence['positions'])->toBe(['Senior Backend Engineer · Acme'])
        ->and(array_column($import->payload['skills'], 'skill'))->toBe(['laravel', 'postgresql'])
        ->and(array_column($import->payload['positions'], 'company'))->toBe(['Acme'])
        ->and($import->payload['education'])->toBe([])
        ->and($import->payload['certifications'])->toHaveCount(1)
        ->and($user->profile()->sole()->headline)->toBe('Backend engineer (Laravel)')
        ->and($user->profile()->sole()->languages)->toBe([['code' => 'en', 'level' => 'C1']]);
    Event::assertDispatched(ProfileChanged::class);
});

it('adds imported languages without overriding the user\'s own levels', function (): void {
    $user = User::factory()->create();
    $user->profile()->create(['languages' => [['code' => 'en', 'level' => 'B2']], 'headline' => 'Mine']);
    $import = pendingLinkedInImport($user);

    $this->post(route('skills.imports.confirm', $import), [
        'skills' => [],
        'languages' => [['code' => 'en', 'level' => 'C2'], ['code' => 'es', 'level' => 'native']],
    ])->assertSessionHasNoErrors();

    $profile = $user->profile()->sole();
    expect($profile->languages)->toBe([['code' => 'en', 'level' => 'B2'], ['code' => 'es', 'level' => 'native']])
        ->and($profile->headline)->toBe('Mine');
});

it('replaces the previous import of the same source on confirmation', function (): void {
    $user = User::factory()->create();
    $first = pendingLinkedInImport($user);
    $this->post(route('skills.imports.confirm', $first), ['skills' => ['php', 'laravel']]);

    $second = pendingLinkedInImport($user);
    // Until the new one is confirmed, the old data stays.
    expect(ProfileImport::query()->find($first->id))->not->toBeNull();

    $this->post(route('skills.imports.confirm', $second), ['skills' => ['docker']]);

    expect(ProfileImport::query()->find($first->id))->toBeNull()
        ->and(UserSkill::query()->where('source', SkillSource::Linkedin)->pluck('skill')->all())->toBe(['docker']);
});

it('cannot confirm an import twice or someone else\'s', function (): void {
    $owner = User::factory()->create();
    $import = pendingLinkedInImport($owner);

    $this->actingAs(User::factory()->create());
    $this->get(route('skills.imports.show', $import))->assertForbidden();
    $this->post(route('skills.imports.confirm', $import), ['skills' => []])->assertForbidden();
    $this->delete(route('skills.imports.destroy', $import))->assertForbidden();

    $this->actingAs($owner)->post(route('skills.imports.confirm', $import), ['skills' => []])->assertSessionHasNoErrors();
    $this->post(route('skills.imports.confirm', $import), ['skills' => []])->assertSessionHasErrors('import');
    $this->get(route('skills.imports.show', $import))->assertRedirect(route('skills.index'));
});

it('discards imports in flight but not confirmed data', function (): void {
    $user = User::factory()->create();
    $import = pendingLinkedInImport($user);

    $this->delete(route('skills.imports.destroy', $import))->assertRedirect(route('skills.index'));
    expect(ProfileImport::query()->count())->toBe(0);

    $confirmed = pendingLinkedInImport($user);
    $this->post(route('skills.imports.confirm', $confirmed), ['skills' => []]);
    $this->delete(route('skills.imports.destroy', $confirmed))->assertStatus(409);
});

it('deletes everything from one source and nothing else', function (): void {
    $user = User::factory()->create();
    $this->post(route('skills.imports.confirm', pendingLinkedInImport($user)), ['skills' => ['laravel']]);
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'php', 'name' => 'PHP', 'level' => 4]);

    $this->delete(route('skills.sources.destroy', 'linkedin'))->assertRedirect(route('skills.index'));

    expect(ProfileImport::query()->count())->toBe(0)
        ->and(UserSkill::query()->pluck('skill')->all())->toBe(['php']);
    $this->delete('/profile/sources/facebook')->assertNotFound();
});

it('deletes imported data when consent is withdrawn', function (ConsentPurpose $purpose, SkillSource $source): void {
    $user = User::factory()->create();
    app(GrantConsent::class)->handle($user, $purpose);
    $user->profileImports()->create(['source' => $source, 'status' => ImportStatus::Confirmed, 'payload' => ['skills' => []]]);
    $user->skills()->create(['source' => $source, 'skill' => 'php', 'name' => 'PHP']);
    $user->skills()->create(['source' => SkillSource::Declared, 'skill' => 'go', 'name' => 'Go', 'level' => 3]);

    app(RevokeConsent::class)->handle($user, $purpose);

    expect(ProfileImport::query()->count())->toBe(0)
        ->and(UserSkill::query()->pluck('skill')->all())->toBe(['go']);
})->with([
    [ConsentPurpose::GithubImport, SkillSource::Github],
    [ConsentPurpose::LinkedinImport, SkillSource::Linkedin],
    [ConsentPurpose::CvProcessing, SkillSource::Cv],
]);

it('finds the skills mentioned in the primary CV', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/cv', [
        'file' => UploadedFile::fake()->createWithContent('cv.pdf', makePdf('Senior Laravel developer. Laravel, Vue.js and PostgreSQL; Docker.')),
        'consent' => '1',
    ]);

    $this->post(route('skills.imports.cv'))->assertRedirect();

    $import = ProfileImport::query()->sole();
    $skills = collect($import->payload['skills'])->keyBy('skill');
    expect($import->source)->toBe(SkillSource::Cv)
        ->and($import->status)->toBe(ImportStatus::PendingReview)
        ->and($skills->keys()->all())->toBe(['laravel', 'vue', 'postgresql', 'docker'])
        ->and($skills['laravel']['evidence'])->toBe(['mentions' => 2])
        ->and($skills['laravel']['level'])->toBeNull();
});

it('explains when there is no CV text to analyse', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('skills.imports.cv'))->assertSessionHasErrors(['cv' => __('skills.errors.no_cv_text')]);

    $user->cvDocuments()->create([
        'disk' => 'local', 'path' => 'x', 'original_name' => 'cv.pdf', 'mime_type' => CvDocument::PDF,
        'size' => 1, 'checksum' => 'x', 'is_primary' => true, 'status' => CvStatus::Uploaded,
    ]);
    $this->post(route('skills.imports.cv'))->assertSessionHasErrors('cv');
});
