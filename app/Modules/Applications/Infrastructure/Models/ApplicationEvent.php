<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Enums\ApplicationEventType;
use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $application_id
 * @property ApplicationEventType $type
 * @property ApplicationStatus|null $status
 * @property int|null $round
 * @property string|null $note
 * @property CarbonImmutable $occurred_at
 */
final class ApplicationEvent extends Model
{
    protected $fillable = ['type', 'status', 'round', 'note', 'occurred_at'];

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
            'type' => ApplicationEventType::class,
            'status' => ApplicationStatus::class,
            'note' => 'encrypted',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
