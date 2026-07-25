<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Support;

/**
 * MASKING AT BUILD TIME, NOT AT RENDER TIME (TRK-018, TRK-009, TRK-011).
 *
 * This is the structural point of the whole class. The projection never holds an
 * unmasked value, so a template bug, a debug dump, a JSON serialisation, or a
 * future developer adding a field to a view cannot leak a full name or a full
 * phone number — the full value was never in the object to leak.
 *
 * Masking here is DESTRUCTIVE and one-way. There is no `unmask()`, and there is no
 * path from a masked projection back to the record it came from.
 */
final class PublicMask
{
    /**
     * Given name plus the initial of the next name: "Budi Santoso" → "Budi S.".
     *
     * A single-word name is returned as its first character plus a dot, because
     * "Budi" alone in a forwarded link still identifies a person in a small
     * neighbourhood, and the portal assumes the link WILL be forwarded (TRK-014).
     */
    public static function name(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'Pelanggan';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = array_shift($parts) ?? '';

        if ($parts === []) {
            return mb_substr($first, 0, 1).'.';
        }

        $initial = mb_substr((string) $parts[count($parts) - 1], 0, 1);

        return $first.' '.mb_strtoupper($initial).'.';
    }

    /**
     * Country code plus the last four digits, and nothing in between.
     *
     * FR-090 forbids a full phone number on the portal. Four trailing digits are
     * enough for the customer to recognise their own number and far too few to dial.
     */
    public static function phone(?string $normalized): string
    {
        $digits = preg_replace('/\D+/', '', (string) $normalized) ?? '';

        if ($digits === '') {
            return '';
        }

        $last4 = mb_substr($digits, -4);

        // Indonesian numbers normalise to a 62 country code upstream; anything
        // else is rendered without a country prefix rather than guessing one.
        $prefix = str_starts_with($digits, '62') ? '+62' : '';

        return trim($prefix.' ···· '.$last4);
    }
}
