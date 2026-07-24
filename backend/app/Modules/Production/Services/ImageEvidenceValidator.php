<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;

/**
 * Validates defect-photo bytes by their CONTENT, never their filename or the
 * client-declared type (FR-083; Rule 03 hard rule 12 — client-declared content
 * types are untrusted).
 *
 * A file passes only when BOTH the libmagic MIME sniff and the image-header
 * decoder agree it is one of a small set of raster formats, it is within the size
 * and dimension bounds, and it decodes at all. A truncated, mislabelled, oversized
 * or non-image file fails closed with a specific 422 marker; SVG and any
 * executable content are rejected because they are not in the raster allow-list.
 */
final class ImageEvidenceValidator
{
    public const MAX_BYTES = 5 * 1024 * 1024; // 5 MiB
    public const MIN_DIMENSION = 32;
    public const MAX_DIMENSION = 8000;

    /** Detected MIME => [extension, IMAGETYPE_*]. The ONLY accepted formats. */
    private const ALLOWED = [
        'image/jpeg' => ['jpg', IMAGETYPE_JPEG],
        'image/png' => ['png', IMAGETYPE_PNG],
        'image/webp' => ['webp', IMAGETYPE_WEBP],
    ];

    /**
     * @return array{content_type: string, extension: string, width: int, height: int}
     */
    public function validate(string $bytes): array
    {
        $size = strlen($bytes);
        if ($size === 0) {
            $this->reject('empty', 'Berkas bukti kosong.');
        }
        if ($size > self::MAX_BYTES) {
            $this->reject('too_large', 'Ukuran berkas melebihi batas maksimum.');
        }

        // (1) libmagic content sniff — NOT the client's declared type.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! is_string($mime) || ! isset(self::ALLOWED[$mime])) {
            $this->reject('unsupported_type', 'Tipe berkas tidak didukung. Gunakan JPEG, PNG, atau WebP.');
        }
        [$extension, $expectedImageType] = self::ALLOWED[$mime];

        // (2) image-header decode — a malformed/truncated image fails here.
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            $this->reject('malformed', 'Berkas bukan gambar yang valid atau rusak.');
        }

        // (3) the sniffed MIME and the decoded image type MUST agree — a PNG body
        // relabelled as image/jpeg, or vice versa, is refused.
        if (($info[2] ?? null) !== $expectedImageType) {
            $this->reject('type_mismatch', 'Isi berkas tidak cocok dengan tipe gambar.');
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            $this->reject('too_small_dimensions', 'Dimensi gambar terlalu kecil.');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            $this->reject('too_large_dimensions', 'Dimensi gambar terlalu besar.');
        }

        return [
            'content_type' => $mime,
            'extension' => $extension,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function reject(string $marker, string $message): never
    {
        throw ApiException::of(
            ErrorCode::VALIDATION_FAILED,
            $message,
            ['photo' => [$marker]],
        );
    }
}
