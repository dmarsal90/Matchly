<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\ImportStatus;
use App\Modules\Profiles\Domain\Enums\SkillSource;
use App\Modules\Profiles\Infrastructure\Models\ProfileImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function zipUpload(string $path, string $name = 'Basic_LinkedInDataExport.zip'): UploadedFile
{
    $contents = (string) file_get_contents($path);
    @unlink($path);

    return UploadedFile::fake()->createWithContent($name, $contents);
}

it('imports the whitelisted files of an export for review', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/imports/linkedin', ['file' => zipUpload(linkedinExportZip()), 'consent' => '1']);

    $import = ProfileImport::query()->sole();
    $response->assertRedirect(route('skills.imports.show', $import));

    expect($import->source)->toBe(SkillSource::Linkedin)
        ->and($import->status)->toBe(ImportStatus::PendingReview)
        ->and($import->payload['files'])->toEqualCanonicalizing(['Profile.csv', 'Positions.csv', 'Education.csv', 'Skills.csv', 'Languages.csv', 'Certifications.csv', 'Projects.csv'])
        ->and($import->payload['skipped_files'])->toBe(2)
        ->and($import->payload['positions'])->toHaveCount(3)
        ->and($user->hasConsented(ConsentPurpose::LinkedinImport))->toBeTrue();

    // Messages and connections were never read, let alone stored.
    $raw = (string) DB::table('profile_imports')->value('payload');
    expect(json_encode($import->payload))->not->toContain('Private message')->not->toContain('Bob')
        // Encrypted at rest.
        ->and($raw)->not->toContain('Acme');
});

it('shows the import for review', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/imports/linkedin', ['file' => zipUpload(linkedinExportZip()), 'consent' => '1']);
    $import = ProfileImport::query()->sole();

    $this->get(route('skills.imports.show', $import))
        ->assertInertia(fn (Assert $page) => $page
            ->component('skills/ReviewImport')
            ->where('review.source', 'linkedin')
            ->where('review.data.profile.headline', 'Senior Backend Engineer · Laravel & Vue')
            ->has('review.data.skills')
            ->has('languageLevels', 7));
});

it('requires consent and a ZIP file', function (array $payload, string $error): void {
    $this->actingAs(User::factory()->create())->post('/profile/imports/linkedin', $payload)->assertSessionHasErrors($error);

    expect(ProfileImport::query()->count())->toBe(0);
})->with([
    'no consent' => [fn () => ['file' => zipUpload(linkedinExportZip())], 'consent'],
    'no file' => [['consent' => '1'], 'file'],
    'not a zip' => [fn () => ['file' => UploadedFile::fake()->createWithContent('export.zip', '%PDF-1.4 fake'), 'consent' => '1'], 'file'],
    'wrong extension' => [fn () => ['file' => zipUpload(linkedinExportZip(), 'export.pdf'), 'consent' => '1'], 'file'],
]);

it('explains why an archive was rejected', function (Closure $zip, string $reason): void {
    $this->actingAs(User::factory()->create())
        ->post('/profile/imports/linkedin', ['file' => zipUpload($zip()), 'consent' => '1'])
        ->assertSessionHasErrors(['file' => __("skills.errors.{$reason}")]);

    expect(ProfileImport::query()->count())->toBe(0);
})->with([
    'not an export' => [fn () => makeZip(['photo.jpg' => 'x', 'notes.txt' => 'y']), 'not_a_linkedin_export'],
    'zip slip' => [fn () => makeZip(['Skills.csv' => "Name\nPHP", '../../evil.php' => '<?php']), 'unsafe_archive'],
    'empty export' => [fn () => makeZip(['Skills.csv' => "Name\n"]), 'nothing_found'],
]);

it('enforces the upload size limit', function (): void {
    config(['linkedin.max_upload_kilobytes' => 1]);

    $this->actingAs(User::factory()->create())
        ->post('/profile/imports/linkedin', ['file' => UploadedFile::fake()->create('export.zip', 5, 'application/zip'), 'consent' => '1'])
        ->assertSessionHasErrors('file');
});
