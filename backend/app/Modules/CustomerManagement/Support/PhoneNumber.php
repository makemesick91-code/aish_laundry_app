<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Support;

use InvalidArgumentException;

/**
 * Indonesian telephone-number normalisation for MATCHING ONLY.
 *
 * WHAT THIS IS FOR
 * ----------------
 * `0812-0000-0001`, `0812 0000 0001`, `+62 812 0000 0001` and `62812 0000 0001`
 * are one customer typing their number four ways. Without a canonical form the
 * `(tenant_id, phone_normalized)` uniqueness constraint that implements FR-022
 * would not fire, and the counter would accumulate duplicate profiles for the
 * same person.
 *
 * Every example number in this file is recognisably fabricated — an all-zero
 * subscriber body with a trailing counter — because a plausible-looking fake
 * reads as a genuine disclosure to an outside reader on a PUBLIC repository
 * (Rule 45). An earlier draft of this docblock used a plausible-looking
 * sequential number and was correctly rejected by
 * `validate-public-repository-safety.sh`; the rejected literal is deliberately
 * not reproduced here, because quoting it would reintroduce it.
 *
 * WHAT THIS IS NOT
 * ----------------
 * It is NOT an authorization key, NOT an identity claim, and NOT a credential.
 * It identifies a customer within an already-authorised tenant scope and nothing
 * more (Rule 02 hard rule 9). Nothing in this class may ever be used to decide
 * access.
 *
 * It is also NOT a validity check. A number that normalises cleanly may still be
 * unreachable; proving reachability requires sending something, which Step 4
 * does not do (WhatsApp is Step 7). This class makes no reachability claim.
 *
 * DETERMINISTIC AND SERVER-SIDE
 * -----------------------------
 * A client never supplies the normalized form. If it did, two clients could
 * disagree about whether two numbers are the same person, and the uniqueness
 * constraint would depend on which client wrote last.
 */
final class PhoneNumber
{
    /** Indonesia. The product's single market (Master Source §1.6). */
    private const COUNTRY_CODE = '62';

    /**
     * Canonical match form: country code followed by the subscriber number,
     * digits only, no punctuation and no leading `+`.
     *
     * Rejects rather than guesses. A number that cannot be normalised is a data
     * error the operator must see, not something to store in a form that will
     * silently fail to match later.
     *
     * @throws InvalidArgumentException when no plausible number can be derived
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Nomor telepon tidak berisi angka.');
        }

        // 0812... -> 62812...
        if (str_starts_with($digits, '0')) {
            $digits = self::COUNTRY_CODE.substr($digits, 1);
        } elseif (! str_starts_with($digits, self::COUNTRY_CODE)) {
            // A bare subscriber number such as `812...`, typed without either
            // the trunk `0` or the country code.
            $digits = self::COUNTRY_CODE.$digits;
        }

        // 62 + 8 digits is the shortest plausible Indonesian mobile number;
        // 62 + 13 the longest. Outside that range the input is not a number
        // this product can use, and storing it would create a row that can
        // never match anything.
        $subscriberLength = strlen($digits) - strlen(self::COUNTRY_CODE);

        if ($subscriberLength < 8 || $subscriberLength > 13) {
            throw new InvalidArgumentException('Panjang nomor telepon tidak wajar.');
        }

        return $digits;
    }

    /**
     * Masked for display: country code plus the last four digits (Rule 32,
     * hard rule 4).
     *
     * Applied at the serializer boundary. A masked value is what a surface
     * renders by DEFAULT; unmasking is a deliberate, per-record, permissioned,
     * recorded action and is never a hover or a bulk operation (Rule 32, hard
     * rule 5).
     */
    public static function mask(string $normalized): string
    {
        $last4 = substr($normalized, -4);

        return '+'.self::COUNTRY_CODE.'••••'.$last4;
    }
}
