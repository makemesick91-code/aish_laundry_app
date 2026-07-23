<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\OrderBalance;

/**
 * The nota (FR-052): a reprintable receipt that shows the prices the order
 * ACTUALLY captured, plus the payments recorded against it and the outstanding
 * balance.
 *
 * It reads the order-line SNAPSHOT (unit_price_rupiah), not the live price list,
 * so a reprint after a price change still shows what the customer was charged
 * (FR-036). Every amount is a whole integer number of Rupiah; formatting for
 * display is a client concern applied to these integers (Rule 04).
 *
 * This is an ALLOW-LIST projection: only the fields named here are assembled, so
 * a field is never leaked by omission. It carries no cost/margin, no internal
 * note, and no membership id — a nota is a customer-facing document.
 */
final class ReceiptProjection
{
    /**
     * @param  iterable<Payment>  $payments
     * @return array<string, mixed>
     */
    public static function of(Order $order, iterable $payments): array
    {
        $order->loadMissing('lines');

        $lines = $order->lines
            ->sortBy('line_number')
            ->map(fn ($line) => [
                'line_number' => (int) $line->line_number,
                'service_name' => $line->service_name,
                'unit' => $line->unit,
                'quantity_milli' => (int) $line->quantity_milli,
                'unit_price_rupiah' => (int) $line->unit_price_rupiah,
                'discount_rupiah' => (int) $line->discount_rupiah,
                'subtotal_rupiah' => (int) $line->subtotal_rupiah,
            ])
            ->values()
            ->all();

        $balance = OrderBalance::for($order);

        $paymentRows = [];
        foreach ($payments as $payment) {
            $paymentRows[] = [
                'payment_number' => $payment->payment_number,
                'kind' => $payment->kind,
                'method' => $payment->method,
                'status' => $payment->status,
                'amount_rupiah' => (int) $payment->amount_rupiah,
            ];
        }

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'currency' => 'IDR',
            'lines' => $lines,
            'subtotal_rupiah' => (int) $order->subtotal_rupiah,
            'discount_rupiah' => (int) $order->discount_rupiah,
            'total_rupiah' => (int) $order->total_rupiah,
            'payments' => $paymentRows,
            'paid_rupiah' => $balance['paid_rupiah'],
            'outstanding_rupiah' => $balance['outstanding_rupiah'],
            'payment_state' => $balance['state'],
        ];
    }
}
