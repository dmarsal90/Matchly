<?php

declare(strict_types=1);

namespace App\Modules\Profiles\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Profiles\Domain\Enums\CvStatus;
use App\Modules\Profiles\Policies\CvDocumentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $checksum
 * @property bool $is_primary
 * @property CvStatus $status
 * @property string|null $extracted_text
 * @property array<string, mixed>|null $parsed_data
 * @property CarbonImmutable|null $extracted_at
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
#[UsePolicy(CvDocumentPolicy::class)]
final class CvDocument extends Model
{
    public const string PDF = 'application/pdf';

    public const string DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'checksum', 'is_primary', 'status'];

    protected $hidden = ['path', 'extracted_text'];

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
            'original_name' => 'encrypted',
            'extracted_text' => 'encrypted',
            'parsed_data' => 'encrypted:array',
            'is_primary' => 'boolean',
            'size' => 'integer',
            'status' => CvStatus::class,
            'extracted_at' => 'immutable_datetime',
        ];
    }
}
