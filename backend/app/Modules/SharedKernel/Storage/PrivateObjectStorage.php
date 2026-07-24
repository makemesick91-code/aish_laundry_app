<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The MINIMUM reusable private-object-storage abstraction (owner constraint: no
 * generic media platform beyond this). It puts bytes into a PRIVATE S3-compatible
 * disk under a random, non-guessable key and hands back a SHORT-LIVED signed URL —
 * never a permanent public URL, never a public bucket (Rule 03 hard rules 13-14,
 * Rule 06 hard rules 16-17).
 *
 * The disk is always the `s3` driver (MinIO in development). There is deliberately
 * NO local-disk production fallback: a foundation without deployment stores to the
 * real private object contract or not at all.
 */
final class PrivateObjectStorage
{
    public function __construct(private readonly string $disk)
    {
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * A random, non-guessable object key. Two UUID segments plus the extension;
     * it embeds no tenant, inspection, or order identifier, so knowing any id
     * discloses nothing about where an object lives (the bucket is private
     * regardless).
     */
    public function randomKey(string $extension): string
    {
        return sprintf('evidence/%s/%s.%s', (string) Str::uuid(), (string) Str::uuid(), $extension);
    }

    /** Store bytes privately. Throws (disk `throw => true`) rather than silently failing. */
    public function put(string $key, string $contents, string $contentType): void
    {
        $this->fs()->put($key, $contents, [
            'visibility' => 'private',
            'ContentType' => $contentType,
        ]);
    }

    public function exists(string $key): bool
    {
        return $this->fs()->exists($key);
    }

    /**
     * A short-lived signed URL. The response is forced to a safe content
     * disposition so a browser downloads it rather than executing anything, and
     * the filename never carries a path or a user-controlled value.
     */
    public function temporaryUrl(string $key, int $ttlSeconds, string $downloadFilename): string
    {
        return $this->fs()->temporaryUrl(
            $key,
            now()->addSeconds($ttlSeconds),
            [
                'ResponseContentDisposition' => 'attachment; filename="' . $downloadFilename . '"',
            ],
        );
    }
}
