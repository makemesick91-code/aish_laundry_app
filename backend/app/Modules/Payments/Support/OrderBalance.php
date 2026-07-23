<?php

declare(strict_types=1);

namespace App\Modules\Payments\Support;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;

/**
 * The paid amount, outstanding balance, and settlement state of an order —
 * DERIVED from the append-only ledger, never stored as a mutable field (FR-070).
 *
 * paid = Σ(succeeded payments) − Σ(succeeded reversals). All integer Rupiah, read
 * from the authoritative payment rows (Rule 04). A stored "paid" column would be a
 * second source of truth that could drift from the ledger; deriving it means the
 * two can never disagree.
 */
final class OrderBalance
{
    public const STATE_UNPAID = 'unpaid';

    public const STATE_PARTIAL = 'partial';

    public const STATE_PAID = 'paid';

    /**
     * @return array{paid_rupiah: int, outstanding_rupiah: int, state: string}
     */
    public static function for(Order $order): array
    {
        $tenantId = $order->tenant_id;

        $paid = (int) Payment::query()
            ->forTenant($tenantId)
            ->where('order_id', $order->id)
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sum('amount_rupiah');

        $reversed = (int) Payment::query()
            ->forTenant($tenantId)
            ->where('order_id', $order->id)
            ->where('kind', Payment::KIND_REVERSAL)
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sum('amount_rupiah');

        $net = $paid - $reversed;
        $total = (int) $order->total_rupiah;
        $outstanding = $total - $net;

        $state = match (true) {
            $net <= 0 => self::STATE_UNPAID,
            $net >= $total => self::STATE_PAID,
            default => self::STATE_PARTIAL,
        };

        return [
            'paid_rupiah' => $net,
            'outstanding_rupiah' => $outstanding,
            'state' => $state,
        ];
    }

    /**
     * The net amount already succeeded against a single payment (the payment
     * minus reversals that reference it). Used to bound a further reversal so the
     * total reversed can never exceed what was paid (FR-067).
     */
    public static function reversedAgainst(Payment $payment): int
    {
        return (int) Payment::query()
            ->forTenant($payment->tenant_id)
            ->where('reverses_payment_id', $payment->id)
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->sum('amount_rupiah');
    }
}
