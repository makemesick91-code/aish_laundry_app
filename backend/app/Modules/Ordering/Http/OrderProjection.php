<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Support\OrderBalance;

/**
 * The API projection of an order. An ALLOW-LIST: only the fields named here are
 * assembled, so nothing is leaked by omission. Money is emitted as integer
 * Rupiah; the client formats it (Rule 04). No customer personal data beyond the
 * id is included — the customer resource is fetched separately, under its own
 * permission (Rule 32).
 */
final class OrderProjection
{
    /** @return array<string, mixed> */
    public static function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer_id' => $order->customer_id,
            'outlet_id' => $order->outlet_id,
            'subtotal_rupiah' => (int) $order->subtotal_rupiah,
            'discount_rupiah' => (int) $order->discount_rupiah,
            'total_rupiah' => (int) $order->total_rupiah,
            'version' => (int) $order->version,
            'created_at' => optional($order->created_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(Order $order): array
    {
        $order->loadMissing('lines');

        $lines = $order->lines
            ->sortBy('line_number')
            ->map(fn ($line) => [
                'id' => $line->id,
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

        return array_merge(self::summary($order), [
            'special_instructions' => $order->special_instructions,
            'placed_at' => optional($order->placed_at)->toIso8601String(),
            'cancelled_at' => optional($order->cancelled_at)->toIso8601String(),
            'cancellation_reason' => $order->cancellation_reason,
            'lines' => $lines,
            'paid_rupiah' => $balance['paid_rupiah'],
            'outstanding_rupiah' => $balance['outstanding_rupiah'],
            'payment_state' => $balance['state'],
        ]);
    }
}
