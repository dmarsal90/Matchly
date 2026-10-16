<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Enums\ContentSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property string $question_key
 * @property string $answer
 * @property array{strengths: list<array{key?: string, text?: string}>, improvements: list<array{key?: string, text?: string}>}|null $feedback
 * @property ContentSource|null $feedback_source
 * @property CarbonImmutable $updated_at
 * @property-read Application $application
 */
final class InterviewAnswer extends Model
{
    protected $fillable = ['question_key', 'answer', 'feedback', 'feedback_source'];

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
            'answer' => 'encrypted',
            'feedback' => 'encrypted:array',
            'feedback_source' => ContentSource::class,
        ];
    }
}
