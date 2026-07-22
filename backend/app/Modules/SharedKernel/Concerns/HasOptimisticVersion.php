<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Concerns;

/**
 * AN EXPLICIT VERSION COUNTER FOR STALE-WRITE DETECTION (threat T-12).
 *
 * WHY NOT `updated_at`, WHICH WAS THE OBVIOUS CHOICE
 * --------------------------------------------------
 * It was tried and it does not work. Laravel's `timestamps()` produces a
 * SECOND-PRECISION column in PostgreSQL, so two edits inside the same second
 * carry an identical `updated_at` — and two edits inside the same second are
 * exactly the case a stale-write check exists to catch. A conflict detector that
 * is blind precisely when the conflict is most likely is not a detector.
 *
 * The failure was silent, too: a test would pass whenever the two writes happened
 * to straddle a second boundary and fail when they did not.
 *
 * An integer incremented on every save has none of that. It changes on every
 * write, it cannot collide, and it does not depend on clock precision, clock
 * skew, or how fast the machine ran that day.
 *
 * The counter is SERVER-OWNED. It is never mass-assignable and is never accepted
 * from a request body; a client that could choose the version could defeat the
 * check by sending whatever the row currently holds.
 */
trait HasOptimisticVersion
{
    public static function bootHasOptimisticVersion(): void
    {
        static::creating(function (self $model): void {
            $model->setAttribute('version', 1);
        });

        static::updating(function (self $model): void {
            // Increment on EVERY update, including one that changes nothing else.
            // A no-op save that left the version alone would let a second writer
            // reuse a version token the first writer had already consumed.
            $model->setAttribute('version', ((int) $model->getRawOriginal('version')) + 1);
        });
    }

    public function optimisticVersion(): string
    {
        return (string) ((int) $this->getAttribute('version'));
    }
}
