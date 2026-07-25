<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Support;

use App\Modules\Tracking\Models\TrackingToken;

/**
 * THE ONE-TIME CARRIER for a freshly minted tracking token.
 *
 * This object exists so that the plaintext has a single, named, obviously
 * short-lived home. It is returned by `TrackingTokenService::issue()` and
 * `::rotate()`, handed straight to the HTTP response, and then discarded. Nothing
 * stores it, nothing caches it, and no other class accepts one.
 *
 * `__toString`, `jsonSerialize`, and `__debugInfo` are deliberately NOT
 * implemented, and `__sleep`/`__serialize` throw. A `SECRET` value that stringifies
 * or serialises conveniently is a `SECRET` value that ends up in a log line, an
 * exception context, a cache entry, or a queued job payload by accident (Rule 03
 * hard rule 20, Rule 21 hard rule 18, Rule 46 hard rule 2).
 */
final class IssuedTrackingLink
{
    public function __construct(
        public readonly TrackingToken $token,
        private readonly string $plaintext,
    ) {
    }

    /**
     * Read the plaintext. Call this exactly once, at the response boundary.
     *
     * A method rather than a public property, so every read is greppable: finding
     * every place the plaintext is touched is `grep -rn 'plaintext()'`.
     */
    public function plaintext(): string
    {
        return $this->plaintext;
    }

    /**
     * The customer-facing URL.
     *
     * Built here rather than in a controller so the path shape lives in one place
     * and cannot drift between the API response, a notification body, and the
     * operator UI.
     */
    public function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/lacak/'.$this->plaintext;
    }

    /** @return never */
    public function __sleep(): array
    {
        throw new \LogicException(
            'A plaintext tracking token is SECRET and is never serialised (TRK-019). '
            .'Serialising this object would place the token in a cache entry, a session, '
            .'or a queued job payload.'
        );
    }

    /** @return never */
    public function __serialize(): array
    {
        throw new \LogicException(
            'A plaintext tracking token is SECRET and is never serialised (TRK-019).'
        );
    }
}
