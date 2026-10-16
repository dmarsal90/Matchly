<?php

declare(strict_types=1);

namespace App\Modules\Applications\Infrastructure\Models;

use App\Modules\Applications\Domain\Enums\NoteOrigin;
use App\Modules\Applications\Policies\CompanyNotePolicy;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $company_key
 * @property string $company
 * @property string $fact
 * @property string|null $source_url
 * @property NoteOrigin $origin
 * @property bool $approved
 * @property CarbonImmutable $created_at
 */
#[UsePolicy(CompanyNotePolicy::class)]
final class CompanyNote extends Model
{
    protected $fillable = ['company_key', 'company', 'fact', 'source_url', 'origin', 'approved'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'fact' => 'encrypted',
            'origin' => NoteOrigin::class,
            'approved' => 'boolean',
        ];
    }
}
