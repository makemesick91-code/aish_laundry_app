<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\SharedKernel\Money\RupiahRounding;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-038 — THE SINGLE DEFINED ROUNDING POINT, EXERCISED AT ITS BOUNDARIES.
 *
 * FR-038 requires the rounding rule to be explicit and applied at a defined
 * point. A rule that has never been run against its own boundary cases is a
 * rule on paper: the half-way value, the sign, the overflow edge, and the
 * refusal of a float are exactly where an implicit rounding defect hides.
 *
 * WHAT THIS TEST PROVES, AND WHAT IT DOES NOT
 * -------------------------------------------
 * It proves the rule itself: correct results at every boundary, in every mode,
 * with no float at any point, and a hard refusal of the inputs that would
 * reintroduce one.
 *
 * It does NOT prove that a real order's total honours the rule. That requires
 * an order, orders are FR-048+ in Step 5, and claiming it here would be a false
 * claim under Rule 01 (DEC-0031 B).
 *
 * Every amount below is fictional (Rule 23, Rule 45).
 */
final class RupiahRoundingTest extends TestCase
{
    // ------------------------------------------------------------------
    // The half-way boundary — where the modes actually differ
    // ------------------------------------------------------------------

    /**
     * 2500/1000 of Rp1 is exactly 2.5 Rupiah: the one input where every mode
     * gives a different, individually defensible answer.
     *
     * @return list<array{string, int, int}>
     */
    public static function halfWayCases(): array
    {
        return [
            // mode, numerator (thousandths), expected
            [RupiahRounding::HALF_UP, 2500, 3],       // 2.5 -> away from zero
            [RupiahRounding::HALF_EVEN, 2500, 2],     // 2.5 -> nearest even
            [RupiahRounding::HALF_EVEN, 3500, 4],     // 3.5 -> nearest even
            [RupiahRounding::AWAY_FROM_ZERO, 2500, 3],
            [RupiahRounding::TOWARD_ZERO, 2500, 2],
        ];
    }

    #[DataProvider('halfWayCases')]
    public function test_each_mode_resolves_the_exact_half_as_specified(
        string $mode,
        int $numerator,
        int $expected,
    ): void {
        $this->assertSame(
            $expected,
            RupiahRounding::scale(1, $numerator, 1000, $mode)
        );
    }

    public function test_half_even_does_not_drift_over_a_run_of_halves(): void
    {
        // The reason banker's rounding exists: HALF_UP biases a long run upward.
        // 0.5, 1.5, 2.5, 3.5 -> HALF_EVEN gives 0, 2, 2, 4 (sum 8, exact sum 8).
        $exactSum = 0;
        $roundedSum = 0;

        foreach ([500, 1500, 2500, 3500] as $thousandths) {
            $exactSum += $thousandths;
            $roundedSum += RupiahRounding::scale(1, $thousandths, 1000, RupiahRounding::HALF_EVEN);
        }

        $this->assertSame(8000, $exactSum);
        $this->assertSame(8, $roundedSum, 'HALF_EVEN must not accumulate a bias.');
    }

    // ------------------------------------------------------------------
    // Just below and just above the half — the off-by-one boundary
    // ------------------------------------------------------------------

    public function test_just_below_the_half_rounds_down_in_every_half_mode(): void
    {
        foreach ([RupiahRounding::HALF_UP, RupiahRounding::HALF_EVEN] as $mode) {
            $this->assertSame(
                2,
                RupiahRounding::scale(1, 2499, 1000, $mode),
                $mode.' must not round 2.499 up.'
            );
        }
    }

    public function test_just_above_the_half_rounds_up_in_every_half_mode(): void
    {
        foreach ([RupiahRounding::HALF_UP, RupiahRounding::HALF_EVEN] as $mode) {
            $this->assertSame(
                3,
                RupiahRounding::scale(1, 2501, 1000, $mode),
                $mode.' must round 2.501 up.'
            );
        }
    }

    public function test_the_smallest_possible_fraction_still_moves_away_from_zero_mode(): void
    {
        // One thousandth of a Rupiah is the smallest fraction this denominator
        // can express. AWAY_FROM_ZERO must still act on it.
        $this->assertSame(3, RupiahRounding::scale(1, 2001, 1000, RupiahRounding::AWAY_FROM_ZERO));
        $this->assertSame(2, RupiahRounding::scale(1, 2001, 1000, RupiahRounding::TOWARD_ZERO));
    }

    // ------------------------------------------------------------------
    // Exactness — no rounding may occur where no fraction exists
    // ------------------------------------------------------------------

    public function test_an_exact_result_is_identical_in_every_mode(): void
    {
        // FR-038 negative path: rounding must NOT occur at any point other than
        // the defined one — and never at all when the result is already whole.
        foreach (RupiahRounding::modes() as $mode) {
            $this->assertSame(
                4500,
                RupiahRounding::scale(1500, 3000, 1000, $mode),
                $mode.' altered an exact result.'
            );
        }
    }

    public function test_a_zero_amount_stays_zero_in_every_mode(): void
    {
        foreach (RupiahRounding::modes() as $mode) {
            $this->assertSame(0, RupiahRounding::scale(0, 2500, 1000, $mode));
        }
    }

    public function test_scaling_by_one_is_the_identity(): void
    {
        foreach (RupiahRounding::modes() as $mode) {
            $this->assertSame(17_500, RupiahRounding::scale(17_500, 1, 1, $mode));
        }
    }

    // ------------------------------------------------------------------
    // Sign — rounding is symmetric about zero, not about negative infinity
    // ------------------------------------------------------------------

    public function test_rounding_is_symmetric_about_zero(): void
    {
        // A mode named "away from zero" must mean it on both sides. A naive
        // implementation built on floor() rounds -2.5 to -3 under HALF_UP but
        // -2.001 to -2 under a "ceiling" it inherited, and the asymmetry only
        // shows up on a reversal.
        $this->assertSame(-3, RupiahRounding::scale(-1, 2500, 1000, RupiahRounding::HALF_UP));
        $this->assertSame(-2, RupiahRounding::scale(-1, 2500, 1000, RupiahRounding::TOWARD_ZERO));
        $this->assertSame(-3, RupiahRounding::scale(-1, 2001, 1000, RupiahRounding::AWAY_FROM_ZERO));
        $this->assertSame(-2, RupiahRounding::scale(-1, 2500, 1000, RupiahRounding::HALF_EVEN));
    }

    // ------------------------------------------------------------------
    // Realistic Step 5 shapes — proven here as arithmetic only
    // ------------------------------------------------------------------

    public function test_a_weight_based_factor_rounds_at_the_defined_point(): void
    {
        // 2.4 kg at Rp7.000/kg = Rp16.800 exactly. Fictional price.
        $this->assertSame(
            16_800,
            RupiahRounding::scale(7_000, 2_400, 1_000, RupiahRounding::HALF_UP)
        );

        // 2.45 kg at Rp7.000/kg = Rp17.150 exactly — still no fraction.
        $this->assertSame(
            17_150,
            RupiahRounding::scale(7_000, 2_450, 1_000, RupiahRounding::HALF_UP)
        );

        // 1.333 kg at Rp7.000/kg = Rp9.331 exactly.
        $this->assertSame(
            9_331,
            RupiahRounding::scale(7_000, 1_333, 1_000, RupiahRounding::HALF_UP)
        );
    }

    public function test_a_percentage_factor_rounds_at_the_defined_point(): void
    {
        // 15% of Rp17.500 = Rp2.625 exactly.
        $this->assertSame(
            2_625,
            RupiahRounding::scale(17_500, 15, 100, RupiahRounding::HALF_UP)
        );

        // 15% of Rp17.503 = Rp2.62545 -> Rp2.625 under every half mode.
        $this->assertSame(
            2_625,
            RupiahRounding::scale(17_503, 15, 100, RupiahRounding::HALF_UP)
        );
    }

    // ------------------------------------------------------------------
    // Large values — where a float would first lose a Rupiah
    // ------------------------------------------------------------------

    public function test_a_large_whole_rupiah_value_survives_scaling_exactly(): void
    {
        // Rp9.007.199.254.740.993 is 2^53 + 1: the first integer a 64-bit float
        // CANNOT represent. If any step of this computation touched a float, the
        // result would come back as 2^53 and the assertion would fail.
        $beyondFloatPrecision = 9_007_199_254_740_993;

        $this->assertSame(
            $beyondFloatPrecision,
            RupiahRounding::scale($beyondFloatPrecision, 1, 1, RupiahRounding::HALF_UP)
        );

        $this->assertNotSame(
            (int) (float) $beyondFloatPrecision,
            RupiahRounding::scale($beyondFloatPrecision, 1, 1, RupiahRounding::HALF_UP),
            'A float round-trip loses this value; the integer path must not.'
        );
    }

    public function test_a_large_amount_scales_without_precision_loss(): void
    {
        // Rp1.000.000.000.001 scaled by 1/3 — the remainder decides the last
        // Rupiah, and only exact integer arithmetic can see it.
        $this->assertSame(
            333_333_333_334,
            RupiahRounding::scale(1_000_000_000_001, 1, 3, RupiahRounding::HALF_UP)
        );

        $this->assertSame(
            333_333_333_333,
            RupiahRounding::scale(1_000_000_000_001, 1, 3, RupiahRounding::TOWARD_ZERO)
        );
    }

    public function test_an_overflowing_computation_is_refused_not_silently_floated(): void
    {
        // PHP converts an overflowing integer product to a float instead of
        // raising. Continuing would put a float in a money path (Rule 04 hard
        // rule 2) at the largest amounts, so it must be refused.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('overflowed the integer range');

        RupiahRounding::scale(PHP_INT_MAX, 2, 1, RupiahRounding::HALF_UP);
    }

    public function test_the_result_is_always_a_php_integer(): void
    {
        foreach (RupiahRounding::modes() as $mode) {
            foreach ([[1, 2500, 1000], [7_000, 2_400, 1_000], [PHP_INT_MAX, 1, 7]] as [$a, $n, $d]) {
                $this->assertIsInt(
                    RupiahRounding::scale($a, $n, $d, $mode),
                    'Every rounding result must be an integer, never a float.'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Invalid precision and invalid mode — rejected, never guessed
    // ------------------------------------------------------------------

    public function test_an_unknown_rounding_mode_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown rounding mode');

        RupiahRounding::scale(1_000, 1, 3, 'bankers');
    }

    public function test_a_zero_denominator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive unit count');

        RupiahRounding::scale(1_000, 1, 0, RupiahRounding::HALF_UP);
    }

    public function test_a_negative_denominator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('positive unit count');

        RupiahRounding::scale(1_000, 1, -100, RupiahRounding::HALF_UP);
    }

    public function test_an_oversized_denominator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provably free of integer overflow');

        RupiahRounding::scale(1_000, 1, 1_000_000_001, RupiahRounding::HALF_UP);
    }

    public function test_there_is_no_default_rounding_mode(): void
    {
        // FR-038's whole point: the rule may not be left to a default. If a
        // default is ever added, `scale()` becomes callable with three arguments
        // and this assertion fails.
        $parameters = (new \ReflectionMethod(RupiahRounding::class, 'scale'))->getParameters();

        $mode = $parameters[3];

        $this->assertSame('mode', $mode->getName());
        $this->assertFalse(
            $mode->isDefaultValueAvailable(),
            'The rounding mode must never have a default — a default IS the '
            .'language default FR-038 forbids, wearing a domain name.'
        );
    }

    // ------------------------------------------------------------------
    // multiply() — exact by construction, with no rounding decision at all
    // ------------------------------------------------------------------

    public function test_multiply_is_exact_and_offers_no_rounding_mode(): void
    {
        $this->assertSame(52_500, RupiahRounding::multiply(17_500, 3));
        $this->assertSame(0, RupiahRounding::multiply(17_500, 0));

        // FR-038 negative path: no rounding point may exist here, so the method
        // must not accept a mode at all.
        $this->assertSame(
            2,
            (new \ReflectionMethod(RupiahRounding::class, 'multiply'))->getNumberOfParameters(),
            'multiply() must take no rounding mode: a whole quantity at a whole '
            .'price is exact, and offering a mode would imply otherwise.'
        );
    }

    public function test_multiply_refuses_a_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may not be negative');

        RupiahRounding::multiply(17_500, -1);
    }

    public function test_multiply_refuses_an_overflowing_product(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('overflowed the integer range');

        RupiahRounding::multiply(PHP_INT_MAX, 3);
    }

    // ------------------------------------------------------------------
    // fromInput() — the boundary a float must never cross
    // ------------------------------------------------------------------

    public function test_an_integer_input_is_accepted(): void
    {
        $this->assertSame(17_500, RupiahRounding::fromInput(17_500));
        $this->assertSame(0, RupiahRounding::fromInput(0));
    }

    public function test_an_exact_integer_string_is_accepted(): void
    {
        // A JSON body carrying "17500" is a legitimate transport shape.
        $this->assertSame(17_500, RupiahRounding::fromInput('17500'));
        $this->assertSame(-250, RupiahRounding::fromInput('-250'));
    }

    public function test_a_float_input_is_refused_even_when_it_is_whole(): void
    {
        // 1500.0 is exactly representable and would cast cleanly. It is still
        // refused: accepting it teaches the codebase that floats are acceptable
        // in a money path as long as they happen to be whole, which is how the
        // first inexact one arrives.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('floating-point');

        RupiahRounding::fromInput(1500.0);
    }

    public function test_a_fractional_float_input_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('floating-point');

        RupiahRounding::fromInput(1500.75);
    }

    public function test_a_decimal_string_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whole number of Rupiah');

        RupiahRounding::fromInput('1500.50');
    }

    public function test_a_formatted_money_string_is_refused(): void
    {
        // "Rp17.500" is a VIEW concern applied to an integer. Money is never
        // inferred from a display string (Rule 04, supporting expectations).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('whole number of Rupiah');

        RupiahRounding::fromInput('Rp17.500');
    }

    public function test_a_null_or_boolean_input_is_refused(): void
    {
        foreach ([null, true, false, []] as $value) {
            try {
                RupiahRounding::fromInput($value);
                $this->fail('Expected a non-numeric money input to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_out_of_range_integer_string_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('representable integer range');

        RupiahRounding::fromInput('99999999999999999999999');
    }
}
