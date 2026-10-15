<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Data\ResumeData;
use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Applications\Domain\Enums\LetterLength;
use App\Modules\Applications\Domain\Enums\LetterTone;
use App\Modules\Applications\Domain\Enums\PackageStatus;
use App\Modules\Applications\Policies\ApplicationPackagePolicy;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Jobs\Infrastructure\Models\JobListing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $job_listing_id
 * @property PackageStatus $status
 * @property string $language
 * @property LetterTone $tone
 * @property LetterLength $length
 * @property string|null $cover_letter
 * @property array<string, mixed>|null $resume
 * @property array<string, mixed>|null $master_resume
 * @property ContentSource|null $letter_source
 * @property ContentSource|null $resume_source
 * @property list<array{key: string, params: array<string, string>}>|null $warnings
 * @property string|null $error
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read JobListing|null $job
 * @property-read User|null $user
 */
#[UsePolicy(ApplicationPackagePolicy::class)]
final class ApplicationPackage extends Model
{
    protected $fillable = ['status', 'language', 'tone', 'length', 'cover_letter', 'resume', 'master_resume', 'letter_source', 'resume_source', 'warnings', 'error', 'approved_at'];

    protected $hidden = ['cover_letter', 'resume', 'master_resume'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobListing, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function resumeData(): ?ResumeData
    {
        return $this->resume !== null ? ResumeData::fromArray($this->resume) : null;
    }

    public function masterResumeData(): ?ResumeData
    {
        return $this->master_resume !== null ? ResumeData::fromArray($this->master_resume) : null;
    }

    protected function casts(): array
    {
        return [
            'status' => PackageStatus::class,
            'tone' => LetterTone::class,
            'length' => LetterLength::class,
            // The letter and CVs are personal data: encrypted at rest.
            'cover_letter' => 'encrypted',
            'resume' => 'encrypted:array',
            'master_resume' => 'encrypted:array',
            'letter_source' => ContentSource::class,
            'resume_source' => ContentSource::class,
            'warnings' => 'array',
            'approved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
