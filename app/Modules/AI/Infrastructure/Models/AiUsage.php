<?php

declare(strict_types=1);

namespace App\Modules\AI\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $task
 * @property string $provider
 * @property string $model
 * @property string $key_owner
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property float $cost_usd
 * @property bool $success
 * @property string|null $error
 * @property int $duration_ms
 * @property CarbonImmutable $created_at
 */
final class AiUsage extends Model
{
    public const string SERVER_KEY = 'server';

    public const string USER_KEY = 'user';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

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
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'cost_usd' => 'float',
            'success' => 'boolean',
            'duration_ms' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
