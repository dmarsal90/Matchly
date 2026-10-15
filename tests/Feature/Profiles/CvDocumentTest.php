<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\ConsentPurpose;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Infrastructure\Models\CvDocument;
use App\Modules\Profiles\Infrastructure\Queue\ExtractCvTextJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('local');
    config(['privacy.files_disk' => 'local']);
});

function pdfUpload(string $text = 'Senior Laravel developer with Vue and PostgreSQL'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('cv.pdf', makePdf($text));
}

it('uploads a CV encrypted at rest, records consent and extracts its text', function (): void {
    $user = User::factory()->create();
    $original = makePdf('Senior Laravel developer with Vue and PostgreSQL');

    $this->actingAs($user)
        ->post('/profile/cv', ['file' => UploadedFile::fake()->createWithContent('Jane Doe CV.pdf', $original), 'consent' => '1'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $document = CvDocument::query()->sole();
    $stored = Storage::disk('local')->get($document->path);

    expect($stored)->not->toBe($original)->not->toContain('Laravel')
        ->and($document->original_name)->toBe('Jane Doe CV.pdf')
        ->and($document->mime_type)->toBe(CvDocument::PDF)
        ->and($document->is_primary)->toBeTrue()
        ->and($document->status)->toBe(CvStatus::Extracted)
        ->and($document->extracted_text)->toContain('Senior Laravel developer')
        ->and($user->hasConsented(ConsentPurpose::CvProcessing))->toBeTrue();
});

it('extracts text from DOCX files', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/cv', [
        'file' => UploadedFile::fake()->createWithContent('cv.docx', makeDocx(['Data engineer', 'Python, Airflow, dbt'])),
        'consent' => '1',
    ])->assertSessionHasNoErrors();

    expect(CvDocument::query()->sole()->extracted_text)->toBe("Data engineer\nPython, Airflow, dbt");
});

it('refuses to inflate DOCX zip bombs', function (): void {
    config(['privacy.cv.max_uncompressed_bytes' => 1024 * 1024]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/cv', [
        'file' => UploadedFile::fake()->createWithContent('cv.docx', makeDocx(['Hello'], padding: 5 * 1024 * 1024)),
        'consent' => '1',
    ])->assertSessionHasNoErrors();

    $document = CvDocument::query()->sole();
    expect($document->status)->toBe(CvStatus::Failed)
        ->and($document->error)->toContain('expands beyond')
        ->and($document->extracted_text)->toBeNull();
});

it('queues text extraction', function (): void {
    Queue::fake();

    $this->actingAs(User::factory()->create())->post('/profile/cv', ['file' => pdfUpload(), 'consent' => '1']);

    Queue::assertPushed(ExtractCvTextJob::class, fn (ExtractCvTextJob $job): bool => $job->documentId === CvDocument::query()->sole()->id);
});

it('requires explicit consent', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/profile/cv', ['file' => pdfUpload()])
        ->assertSessionHasErrors('consent');

    expect(CvDocument::query()->count())->toBe(0);
});

it('rejects files that are not really PDF or DOCX', function (UploadedFile $file): void {
    $this->actingAs(User::factory()->create())
        ->post('/profile/cv', ['file' => $file, 'consent' => '1'])
        ->assertSessionHasErrors('file');
})->with([
    'text renamed to pdf' => fn () => UploadedFile::fake()->createWithContent('cv.pdf', 'just some text pretending to be a PDF'),
    'html renamed to docx' => fn () => UploadedFile::fake()->createWithContent('cv.docx', '<html><script>alert(1)</script></html>'),
    'image' => fn () => UploadedFile::fake()->image('cv.png'),
    'too large' => fn () => UploadedFile::fake()->create('cv.pdf', 11 * 1024, 'application/pdf'),
]);

it('limits how many CVs a user keeps', function (): void {
    config(['privacy.cv.max_documents' => 2]);
    $user = User::factory()->create();

    foreach (range(1, 2) as $i) {
        $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload("CV {$i}"), 'consent' => '1'])->assertSessionHasNoErrors();
    }

    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload(), 'consent' => '1'])->assertSessionHasErrors('file');
});

it('lets owners download their CV decrypted', function (): void {
    $user = User::factory()->create();
    $original = makePdf('Download me');
    $this->actingAs($user)->post('/profile/cv', ['file' => UploadedFile::fake()->createWithContent('mine.pdf', $original), 'consent' => '1']);
    $document = CvDocument::query()->sole();

    $response = $this->actingAs($user)->get("/profile/cv/{$document->id}")->assertOk();

    expect($response->streamedContent())->toBe($original)
        ->and($response->headers->get('Content-Disposition'))->toContain('mine.pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('never exposes a CV to another user', function (string $method, string $suffix): void {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post('/profile/cv', ['file' => pdfUpload(), 'consent' => '1']);
    $document = CvDocument::query()->sole();

    $this->actingAs(User::factory()->create())->{$method}("/profile/cv/{$document->id}{$suffix}")->assertForbidden();

    expect(CvDocument::query()->count())->toBe(1);
})->with([
    'download' => ['get', ''],
    'delete' => ['delete', ''],
    'make primary' => ['patch', '/primary'],
]);

it('deletes the file and promotes another CV to primary', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload('First'), 'consent' => '1']);
    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload('Second'), 'consent' => '1']);
    [$first, $second] = CvDocument::query()->orderBy('id')->get()->all();

    $this->actingAs($user)->delete("/profile/cv/{$first->id}")->assertRedirect();

    Storage::disk('local')->assertMissing($first->path);
    expect($second->refresh()->is_primary)->toBeTrue();
});

it('switches the primary CV', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload('First'), 'consent' => '1']);
    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload('Second'), 'consent' => '1']);
    [$first, $second] = CvDocument::query()->orderBy('id')->get()->all();

    $this->actingAs($user)->patch("/profile/cv/{$second->id}/primary")->assertRedirect();

    expect($first->refresh()->is_primary)->toBeFalse()->and($second->refresh()->is_primary)->toBeTrue();
});

it('lists the CVs on the profile page without exposing their contents', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/cv', ['file' => pdfUpload(), 'consent' => '1'])->assertSessionHasNoErrors();

    $this->actingAs($user)->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->has('cvDocuments', 1)
            ->where('cvDocuments.0.name', 'cv.pdf')
            ->where('cvDocuments.0.type', 'pdf')
            ->where('cvDocuments.0.status', CvStatus::Extracted->value)
            ->where('cvDocuments.0.is_primary', true)
            ->missing('cvDocuments.0.path')
            ->missing('cvDocuments.0.extracted_text'));
});
