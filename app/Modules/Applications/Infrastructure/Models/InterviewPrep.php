<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Enums\ContentSource;
use App\Modules\Applications\Domain\Enums\InterviewPrepStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property InterviewPrepStatus $status
 * @property string $language
 * @property ContentSource|null $source
 * @property array{questions: list<array{category: string, question: string, skill: string|null, star: array{situation: string|null, task: string|null, action: string|null, result: string|null}|null}>, gaps: list<array{skill: string, required: bool, answer: string}>, ask: list<string>}|null $content
 * @property list<array{key: string, params: array<string, string>}>|null $warnings
 * @property CarbonImmutable $updated_at
 * @property-read Application $application
 */
final class InterviewPrep extends Model
{
    protected $fillable = ['status', 'language', 'source', 'content', 'warnings'];

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return [
            'status' => InterviewPrepStatus::class,
            'source' => ContentSource::class,
            'content' => 'encrypted:array',
            'warnings' => 'array',
        ];
    }
}
