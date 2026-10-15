<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Files;

use RuntimeException;

final class TemporaryFile
{
    /**
     * Creates an empty, owner-only file with an unguessable name in the
     * system temp directory. The caller deletes it.
     */
    public static function create(string $prefix, string $extension = 'tmp'): string
    {
        $path = sprintf('%s/%s-%s.%s', rtrim(sys_get_temp_dir(), '/'), $prefix, bin2hex(random_bytes(16)), $extension);

        // 'x' fails if the path exists: no clobbering a planted file or symlink.
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Cannot create a temporary file.');
        }

        fclose($handle);
        chmod($path, 0600);

        return $path;
    }
}
