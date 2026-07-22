<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * STALE-WRITE DETECTION FOR MASTER DATA (threat T-12).
 *
 * Two operators open the same outlet. One sets the quiet hours, the other sets
 * the capacity, and the second save silently discards the first — last write
 * wins, and nobody is told. For master data that governs money and messaging
 * windows, "nobody is told" is the defect.
 *
 * The caller may send the `updated_at` it read. If it disagrees with the stored
 * value, the write is REFUSED with a distinct error code so the interface can
 * surface the conflict rather than pick a winner. This is Rule 07 hard rule 5's
 * principle — a conflict is surfaced, never silently resolved — applied to
 * master data rather than to a payment.
 *
 * WHY THE PRECONDITION IS OPTIONAL
 * --------------------------------
 * Making it mandatory would break every caller that legitimately does not hold a
 * prior read: a console command, a first-time configuration, an integration.
 * Requiring it would also not make the system safer, because a client that
 * wanted to overwrite blindly could simply send the current value it just
 * fetched.
 *
 * What this DOES guarantee is that a client which sends the precondition is
 * never silently overridden — and every Step 4 management surface sends it.
 * A precondition that is present is always honoured; a mismatch is never
 * downgraded to a warning.
 */
final class OptimisticConcurrency
{
    /**
     * The header a client sends to say "I am editing the version I read".
     *
     * A header rather than a body field so it applies uniformly to every verb and
     * cannot be confused with a writable attribute.
     */
    public const HEADER = 'If-Unmodified-Since-Version';

    /**
     * @throws ApiException when the caller's version is not the stored one
     */
    public static function assertFresh(Request $request, Model $model): void
    {
        $expected = $request->header(self::HEADER);

        if ($expected === null || trim($expected) === '') {
            return;
        }

        $actual = self::versionOf($model);

        // hash_equals rather than ===: this compares an opaque server-issued
        // token, and constant-time comparison of such tokens is the house style
        // established in Step 3's policies.
        if ($actual === null || ! hash_equals($actual, trim($expected))) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Data ini sudah diubah oleh orang lain sejak Anda membukanya. '
                .'Muat ulang untuk melihat perubahan terbaru, lalu ulangi '
                .'penyuntingan Anda.',
                ['version' => ['stale']]
            );
        }
    }

    /**
     * The opaque version token for a model.
     *
     * An explicit server-owned counter, NOT `updated_at`.
     *
     * `updated_at` was the obvious choice and it is wrong: Laravel's
     * `timestamps()` yields a SECOND-PRECISION column in PostgreSQL, so two
     * edits inside the same second share a timestamp — and that is precisely
     * when a conflict is most likely. The counter changes on every write and
     * cannot collide. See `HasOptimisticVersion`.
     *
     * Returns null for a model that carries no counter, in which case
     * `assertFresh()` has nothing to compare and a supplied precondition can
     * never match — the safe direction.
     */
    public static function versionOf(Model $model): ?string
    {
        $version = $model->getAttribute('version');

        return $version === null ? null : (string) ((int) $version);
    }
}
