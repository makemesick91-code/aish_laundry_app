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
