<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

use Illuminate\Support\Str;

/**
 * The correlation identifier for the current request.
 *
 * Bound as a request-scoped singleton so that the SAME value reaches the HTTP
 * response envelope, every log line, and every audit entry written during the
 * request. That is what makes "find everything that happened in this request"
 * answerable without correlating on timestamps.
 *
 * A client MAY supply `X-Request-Id` to stitch its own traces together, but the
 * value is treated as UNTRUSTED: it is length-capped and character-filtered
 * before use, exactly as a client-supplied tenant identifier is validated rather
 * than believed (Rule 02, hard rule 9).
 */
final class CorrelationId
{
    private const MAX_LENGTH = 64;

    private function __construct(public readonly string $value)
    {
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    /**
     * Accept a client-supplied value only when it is a safe, bounded token.
     * Anything else is discarded and replaced with a server-generated identifier.
     */
    public static function fromClient(?string $candidate): self
    {
        if ($candidate === null) {
            return self::generate();
        }

        $trimmed = trim($candidate);

        if ($trimmed === '' || strlen($trimmed) > self::MAX_LENGTH) {
            return self::generate();
        }

        if (preg_match('/^[A-Za-z0-9._:-]+$/', $trimmed) !== 1) {
            return self::generate();
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
