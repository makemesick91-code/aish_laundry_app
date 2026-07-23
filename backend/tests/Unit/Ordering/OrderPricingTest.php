<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering;

use App\Modules\Ordering\Support\OrderPricing;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Pure, deterministic money math (FR-051, FR-038, Rule 04). No database.
 */
final class OrderPricingTest extends TestCase
{
    public function test_whole_kilo_line_is_exact(): void
    {
        // 7000/kg × 2.5 kg = 17500, exact.
        $this->assertSame(17500, OrderPricing::lineSubtotal(7000, 2500, 0));
    }

    public function test_fractional_result_rounds_half_up_at_the_single_point(): void
    {
        // 7333/kg × 2.5 kg = 18332.5 -> HALF_UP -> 18333.
        $this->assertSame(18333, OrderPricing::lineSubtotal(7333, 2500, 0));
    }

    /**
     * The half-Rupiah threshold, ratified as HALF_UP by DEC-0036 (OQ-017).
     * A value immediately BELOW the half rounds down; a value EXACTLY at the half
     * rounds up (away from zero); a value immediately ABOVE the half rounds up.
     * Rp1/kg × 1.499/1.500/1.501 kg = 1.499 / 1.500 / 1.501 Rupiah.
     */
    public function test_half_rupiah_threshold_below_at_and_above(): void
    {
        $this->assertSame(1, OrderPricing::lineSubtotal(1, 1499, 0), 'just below the half rounds down');
        $this->assertSame(2, OrderPricing::lineSubtotal(1, 1500, 0), 'exactly the half rounds up (HALF_UP)');
        $this->assertSame(2, OrderPricing::lineSubtotal(1, 1501, 0), 'just above the half rounds up');
    }

    public function test_half_up_rounds_a_larger_exact_half_upward(): void
    {
        // 3/kg × 2.5 kg = 7.5 -> HALF_UP -> 8 (a plain HALF_EVEN would give 8 too
        // here; the .5-at-an-odd-quotient case above is what distinguishes them).
        $this->assertSame(8, OrderPricing::lineSubtotal(3, 2500, 0));
        // 5/kg × 4.5 kg = 22.5 -> HALF_UP -> 23; HALF_EVEN would give 22. Locks the mode.
        $this->assertSame(23, OrderPricing::lineSubtotal(5, 4500, 0));
    }

    public function test_a_piece_line_is_a_whole_multiply(): void
    {
        // 5000 × 3 pcs (quantity_milli 3000) = 15000.
        $this->assertSame(15000, OrderPricing::lineSubtotal(5000, 3000, 0));
    }

    public function test_line_discount_reduces_the_subtotal(): void
    {
        $this->assertSame(16500, OrderPricing::lineSubtotal(7000, 2500, 1000));
    }

    public function test_a_line_discount_may_not_exceed_the_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrderPricing::lineSubtotal(7000, 2500, 17501);
    }

    public function test_negative_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrderPricing::lineSubtotal(-1, 1000, 0);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrderPricing::lineSubtotal(7000, 0, 0);
    }

    public function test_order_totals_sum_lines_and_apply_order_discount(): void
    {
        $totals = OrderPricing::orderTotals([17500, 15000, 8000], 2000);
        $this->assertSame(40500, $totals['subtotal']);
        $this->assertSame(38500, $totals['total']);
    }

    public function test_order_discount_may_not_exceed_subtotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OrderPricing::orderTotals([1000], 1001);
    }

    public function test_no_money_value_is_ever_a_float(): void
    {
        $subtotal = OrderPricing::lineSubtotal(7333, 2500, 0);
        $this->assertIsInt($subtotal);
        $totals = OrderPricing::orderTotals([$subtotal], 0);
        $this->assertIsInt($totals['subtotal']);
        $this->assertIsInt($totals['total']);
    }
}
