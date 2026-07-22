<?php

declare(strict_types=1);

namespace App\Modules\SharedKernel\Money;

use InvalidArgumentException;

/**
 * THE SINGLE DEFINED ROUNDING POINT FOR MONEY (FR-038, Rule 04 hard rule 2).
 *
 * FR-038: "Where a computation could produce a fractional Rupiah, the rounding
 * rule shall be explicit and applied at a defined point, not left to language
 * defaults." Its rationale is one sentence long and worth quoting: *implicit
 * rounding is how money quietly disappears.*
 *
 * This class is that defined point. Every fractional-Rupiah computation in the
 * product goes through it, or it does not happen.
 *
 * WHY THERE IS NO DEFAULT ROUNDING MODE
 * ------------------------------------
 * `$mode` is a required argument on every method. It has no default, and there
 * is deliberately no "sensible" fallback.
 *
 * A default mode would BE a language default wearing a domain name — the caller
 * would stop thinking about rounding, which is precisely the failure FR-038
 * names. It would also be an invented product decision: the Master Source fixes
 * that money is integer Rupiah and that the rounding rule must be explicit, but
 * it does not name a canonical mode. Choosing one here and hiding it in a
 * default parameter would silently settle an owner question (Rule 00 hard
 * rule 6). Making it a required argument leaves the choice visible at every
 * call site and recordable in a price snapshot (FIN-033).
 *
 * WHY EVERY OPERATION IS INTEGER ARITHMETIC
 * -----------------------------------------
 * Rule 04 hard rule 2 forbids `float` and `double` anywhere in a money path.
 * That includes intermediate values. `$amount * $numerator / $denominator`
 * written naively produces a float the instant the division is inexact, and the
 * defect is invisible until a total is out by one Rupiah.
 *
 * So the computation is: multiply as integers, divide with `intdiv()`, and
 * decide the last Rupiah from the exact integer remainder. No value in this
 * class is ever a float, at any point, including intermediates.
 *
 * PHP'S SILENT INTEGER OVERFLOW IS TREATED AS A MONEY DEFECT
 * ----------------------------------------------------------
 * PHP does not raise on integer overflow — it converts the result to a float.
 * A money computation that overflows would therefore turn itself into exactly
 * the floating-point value Rule 04 forbids, quietly, at the largest amounts
 * where precision matters most. `assertNoOverflow()` rejects that rather than
 * returning a rounded-off approximation.
 *
 * WHAT STEP 4 CLAIMS AND WHAT IT DOES NOT
 * ---------------------------------------
 * Step 4 delivers this rule, applied in one place, exhaustively unit-tested at
 * its boundaries. Step 4 stores no fractional amount: every stored price is
 * already an integer Rupiah, so nothing in Step 4's own persistence path
 * rounds at all.
 *
 * The computations that WILL consume this class — order line totals, weight-
 * based kiloan pricing, percentage discounts, tax — act on an order, and orders
 * are FR-048+ in Step 5. **Step 4 does not claim to have exercised this rule
 * against a real order, and must not** (DEC-0031 B, Rule 01).
 */
final class RupiahRounding
{
    /** Round half away from zero. */
    public const HALF_UP = 'half_up';

    /** Round half to the nearest even Rupiah — banker's rounding. */
    public const HALF_EVEN = 'half_even';

    /** Always away from zero when any fraction remains — ceiling of magnitude. */
    public const AWAY_FROM_ZERO = 'away_from_zero';

    /** Always toward zero — truncate any fraction. */
    public const TOWARD_ZERO = 'toward_zero';

    /**
     * The largest denominator accepted.
     *
     * A scaling denominator is a unit count — 1000 grams per kilogram, 100 for a
     * percentage. A bound this far above any real one costs nothing and keeps
     * the `2 * remainder` comparison below provably free of overflow.
     */
    private const MAX_DENOMINATOR = 1_000_000_000;

    /** @return list<string> */
    public static function modes(): array
    {
        return [self::HALF_UP, self::HALF_EVEN, self::AWAY_FROM_ZERO, self::TOWARD_ZERO];
    }

    public static function isMode(string $mode): bool
    {
        return in_array($mode, self::modes(), true);
    }

    /**
     * Scale an integer Rupiah amount by the rational factor numerator/denominator,
     * rounding the result to whole Rupiah by the named mode.
     *
     * This is the only place in the product where a fractional Rupiah is allowed
     * to become a whole one. Examples of the factor it exists for:
     *
     *   - 2.4 kg of a per-kilogram service -> numerator 2400, denominator 1000
     *   - a 15% adjustment                 -> numerator 15,   denominator 100
     *
     * Both are Step 5 computations. Step 4 provides the rule they will use.
     *
     * @param  int  $amountRupiah  a whole number of Rupiah
     * @param  int  $numerator  the factor's numerator; may be negative
     * @param  int  $denominator  the factor's denominator; must be >= 1
     * @param  string  $mode  one of self::modes() — required, never defaulted
     *
     * @throws InvalidArgumentException on an unknown mode, a non-positive or
     *                                  oversized denominator, or an overflow that
     *                                  would silently produce a float
     */
    public static function scale(
        int $amountRupiah,
        int $numerator,
        int $denominator,
        string $mode,
    ): int {
        self::assertMode($mode);
        self::assertDenominator($denominator);

        // Integer multiply FIRST, divide second. Dividing first would discard the
        // remainder before the rounding decision could see it.
        $product = $amountRupiah * $numerator;
        self::assertNoOverflow($product, $amountRupiah, $numerator);

        // `intdiv()` truncates toward zero, so the remainder carries the sign of
        // the product and the fractional part points away from zero in that
        // direction. Every branch below is expressed in those terms.
        $quotient = intdiv($product, $denominator);
        $remainder = $product - ($quotient * $denominator);

        if ($remainder === 0) {
            return $quotient;
        }

        $awayFromZero = $remainder < 0 ? -1 : 1;

        // Safe: |remainder| < denominator <= MAX_DENOMINATOR, so this cannot
        // overflow and cannot become a float.
        $twiceRemainder = 2 * abs($remainder);

        return match ($mode) {
            self::TOWARD_ZERO => $quotient,
            self::AWAY_FROM_ZERO => $quotient + $awayFromZero,
            self::HALF_UP => $twiceRemainder >= $denominator
                ? $quotient + $awayFromZero
                : $quotient,
            self::HALF_EVEN => self::halfEven($quotient, $twiceRemainder, $denominator, $awayFromZero),
            default => throw new InvalidArgumentException('Unreachable: mode already asserted.'),
        };
    }

    /**
     * Multiply an integer Rupiah amount by a whole quantity.
     *
     * NO ROUNDING HAPPENS HERE, AND THAT IS THE POINT. A whole number of items at
     * a whole number of Rupiah is exact, so this method has no `$mode` argument —
     * offering one would imply a rounding decision exists where none does, and
     * FR-038's negative path is explicit that rounding must not occur at any
     * point other than the single defined one.
     *
     * It exists so a caller reaches for an overflow-checked integer multiply
     * rather than writing `$price * $qty` inline and inheriting PHP's silent
     * float conversion at large values.
     *
     * @throws InvalidArgumentException on a negative quantity or an overflow
     */
    public static function multiply(int $amountRupiah, int $quantity): int
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException(
                'A quantity may not be negative. A reversal is a separate, audited '
                .'entry, never a negative multiplier (Rule 04 hard rule 8).'
            );
        }

        $product = $amountRupiah * $quantity;
        self::assertNoOverflow($product, $amountRupiah, $quantity);

        return $product;
    }

    /**
     * Accept a value as a whole number of Rupiah, or reject it.
     *
     * `float` is refused outright rather than cast. A caller holding a float
     * already holds a value Rule 04 forbids in a money path, and silently
     * accepting `1500.0` would teach the codebase that floats are fine as long
     * as they happen to be whole — which is how the first inexact one gets in.
     *
     * A numeric STRING is accepted only when it is an exact integer literal, so
     * a JSON body carrying `"1500"` works and `"1500.50"` does not.
     *
     * @throws InvalidArgumentException
     */
    public static function fromInput(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException(
                'Money may not be expressed as a floating-point value. The smallest '
                .'unit is one Rupiah and there is nothing below it (Rule 04, FR-037).'
            );
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            // Reject a literal too large for a PHP int rather than letting the
            // cast wrap it into a float.
            if (! is_int($parsed = filter_var($value, FILTER_VALIDATE_INT))) {
                throw new InvalidArgumentException(
                    'Money value is outside the representable integer range.'
                );
            }

            return $parsed;
        }

        throw new InvalidArgumentException(
            'Money must be a whole number of Rupiah, expressed as an integer.'
        );
    }

    private static function halfEven(
        int $quotient,
        int $twiceRemainder,
        int $denominator,
        int $awayFromZero,
    ): int {
        if ($twiceRemainder > $denominator) {
            return $quotient + $awayFromZero;
        }

        if ($twiceRemainder < $denominator) {
            return $quotient;
        }

        // Exactly one half. Move only if the truncated quotient is odd, so the
        // result is always even and a long run of halves does not drift upward.
        return $quotient % 2 === 0 ? $quotient : $quotient + $awayFromZero;
    }

    private static function assertMode(string $mode): void
    {
        if (! self::isMode($mode)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown rounding mode "%s". FR-038 requires the rounding rule to be '
                .'explicit; a mode outside %s is not a rule, it is a guess.',
                $mode,
                implode(', ', self::modes())
            ));
        }
    }

    private static function assertDenominator(int $denominator): void
    {
        if ($denominator < 1) {
            throw new InvalidArgumentException(
                'A scaling denominator must be a positive unit count. Zero or a '
                .'negative denominator has no defined meaning in a price rule.'
            );
        }

        if ($denominator > self::MAX_DENOMINATOR) {
            throw new InvalidArgumentException(sprintf(
                'A scaling denominator above %d is refused so the rounding comparison '
                .'stays provably free of integer overflow.',
                self::MAX_DENOMINATOR
            ));
        }
    }

    /**
     * PHP converts an overflowing integer product to a float instead of raising.
     *
     * That conversion would put a floating-point value into a money path (Rule 04
     * hard rule 2) at exactly the largest amounts, so it is refused rather than
     * rounded away.
     */
    private static function assertNoOverflow(mixed $product, int $a, int $b): void
    {
        if (! is_int($product)) {
            throw new InvalidArgumentException(sprintf(
                'Money computation overflowed the integer range (%d x %d). PHP would '
                .'silently continue in floating point, which Rule 04 forbids in any '
                .'money path.',
                $a,
                $b
            ));
        }
    }
}
