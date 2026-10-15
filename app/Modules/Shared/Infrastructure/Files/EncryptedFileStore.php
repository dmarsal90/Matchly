<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Files;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores user files encrypted at rest (AES-256 with the app key, MAC
 * verified on read), whatever the underlying disk is. Old keys listed in
 * APP_PREVIOUS_KEYS keep decrypting after a rotation.
 */
final readonly class EncryptedFileStore
{
    public function __construct(
        private Filesystems $filesystems,
        private Encrypter $encrypter,
        private string $disk,
    ) {}

    public function disk(): string
    {
        return $this->disk;
    }

    /**
     * @return string the stored path (random; reveals nothing about the content)
     */
    public function put(string $directory, string $contents): string
    {
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.bin';

        if (! $this->storage()->put($path, $this->encrypter->encrypt($contents, false))) {
            throw new RuntimeException("Could not write encrypted file [{$path}].");
        }

        return $path;
    }

    public function get(string $path): string
    {
        $payload = $this->storage()->get($path);

        if ($payload === null) {
            throw new RuntimeException("Encrypted file [{$path}] not found.");
        }

        $contents = $this->encrypter->decrypt($payload, false);

        return is_string($contents) ? $contents : throw new RuntimeException("Encrypted file [{$path}] is corrupt.");
    }

    public function delete(string $path): void
    {
        $this->storage()->delete($path);
    }

    public function deleteDirectory(string $directory): void
    {
        $this->storage()->deleteDirectory($directory);
    }

    private function storage(): Filesystem
    {
        return $this->filesystems->disk($this->disk);
    }
}
