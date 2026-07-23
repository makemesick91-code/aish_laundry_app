<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Support;

use App\Modules\SharedKernel\Money\RupiahRounding;
use InvalidArgumentException;

/**
 * DETERMINISTIC, SERVER-AUTHORITATIVE ORDER MONEY (FR-051, Rule 04).
 *
 * Every Rupiah an order charges is computed here from validated integer inputs
 * and a price snapshot — never accepted from the client. A client-submitted
 * total is display-only (FR-051); this class produces the number the order is
 * actually stored and charged with, and the database CHECK constraints
 * (`orders_total_is_subtotal_minus_discount`) reject any row whose parts do not
 * add up, so a bug here cannot silently persist a wrong total.
 *
 * ALL ARITHMETIC IS INTEGER, THROUGH THE ONE ROUNDING POINT. Weight-based
 * pricing is the only place a fraction can appear (a per-kilogram price times a
 * fractional weight), and it is resolved exactly once, by RupiahRounding, whose
 * every operation is integer and overflow-checked.
 *
 * THE ROUNDING MODE IS OWNER-RATIFIED (DEC-0036, OQ-017).
 * ------------------------------------------------------
 * FR-038 requires the rounding rule to be explicit and applied at a defined
 * point; it does NOT fix which mode, and neither does the Master Source. The
 * repository owner ratified HALF_UP — the conventional Indonesian retail
 * rounding — in DEC-0036, resolving open question OQ-017. It is a SINGLE, NAMED
 * constant; changing it in future is a one-line edit here plus a superseding
 * decision record, and no call site chooses a mode independently.
 */
final class OrderPricing
{
    /**
     * The order-line rounding mode, ratified as HALF_UP by DEC-0036 (OQ-017). Not
     * a default parameter: it is a visible, named decision, per RupiahRounding's
     * own contract. Covered by half-Rupiah boundary tests in OrderPricingTest.
     */
    public const ROUNDING_MODE = RupiahRounding::HALF_UP;

    /** Thousandths per whole unit — 1000 milli = 1 kg or 1 piece. */
    private const MILLI_PER_UNIT = 1000;

    /**
     * The subtotal of one line: (unit price × quantity) rounded to whole Rupiah,
     * minus the line discount. Quantity is in thousandths so a kiloan weight of
     * 2.5 kg is 2500 and no float ever enters the computation.
     *
     * @throws InvalidArgumentException on negative money/quantity or a discount
     *                                  that exceeds the rounded gross
     */
    public static function lineSubtotal(
        int $unitPriceRupiah,
        int $quantityMilli,
        int $lineDiscountRupiah,
    ): int {
        if ($unitPriceRupiah < 0) {
            throw new InvalidArgumentException('unit_price_rupiah may not be negative.');
        }
        if ($quantityMilli <= 0) {
            throw new InvalidArgumentException('quantity_milli must be positive.');
        }
        if ($lineDiscountRupiah < 0) {
            throw new InvalidArgumentException('discount_rupiah may not be negative.');
        }

        $gross = RupiahRounding::scale(
            $unitPriceRupiah,
            $quantityMilli,
            self::MILLI_PER_UNIT,
            self::ROUNDING_MODE,
        );

        if ($lineDiscountRupiah > $gross) {
            throw new InvalidArgumentException(
                'A line discount may not exceed the line gross. A discount is a '
                .'reduction, never a negative subtotal (Rule 04).'
            );
        }

        return $gross - $lineDiscountRupiah;
    }

    /**
     * The order subtotal (sum of line subtotals) and the order total (subtotal
     * minus the order-level discount). Mirrors the database CHECK constraints so
     * the two can never disagree.
     *
     * @param  list<int>  $lineSubtotals
     * @return array{subtotal: int, total: int}
     *
     * @throws InvalidArgumentException on a negative order discount or one that
     *                                  exceeds the subtotal
     */
    public static function orderTotals(array $lineSubtotals, int $orderDiscountRupiah): array
    {
        if ($orderDiscountRupiah < 0) {
            throw new InvalidArgumentException('order discount_rupiah may not be negative.');
        }

        $subtotal = 0;
        foreach ($lineSubtotals as $lineSubtotal) {
            // Overflow-checked integer addition via the multiply helper's guard:
            // a running total that overflowed would become a float (Rule 04).
            $subtotal = RupiahRounding::multiply($subtotal + $lineSubtotal, 1);
        }

        if ($orderDiscountRupiah > $subtotal) {
            throw new InvalidArgumentException(
                'An order discount may not exceed the order subtotal (Rule 04).'
            );
        }

        return ['subtotal' => $subtotal, 'total' => $subtotal - $orderDiscountRupiah];
    }
}
