<?php

declare(strict_types=1);

namespace App\Modules\Applications\Application\Actions;

use App\Modules\Applications\Application\Queries\InsightsReport;
use App\Modules\Applications\Infrastructure\Models\SharedReport;
use App\Modules\Identity\Infrastructure\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Freezes the insights report and returns the one-time link to it. Only the
 * hash of the link's token is kept, so a link cannot be recovered later: it is
 * revoked and made again instead.
 */
final readonly class ShareInsightsReport
{
    public const int MAX_ACTIVE = 5;

    /** Days a link can live for. */
    public const array LIFETIMES = [1, 7, 30];

    public function __construct(private InsightsReport $report) {}

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return string the plain token to put in the link
     */
    public function handle(User $user, int $days, bool $withName): string
    {
        SharedReport::query()->where('user_id', $user->id)->where('expires_at', '<', CarbonImmutable::now())->delete();

        if (SharedReport::query()->where('user_id', $user->id)->count() >= self::MAX_ACTIVE) {
            throw ValidationException::withMessages(['days' => __('insights.share.too_many', ['max' => self::MAX_ACTIVE])]);
        }

        $token = Str::random(48);
        (new SharedReport)->forceFill([
            'user_id' => $user->id,
            'token_hash' => self::hash($token),
            'html' => $this->report->html($user, $withName),
            'expires_at' => CarbonImmutable::now()->addDays($days),
        ])->save();

        return $token;
    }
}
