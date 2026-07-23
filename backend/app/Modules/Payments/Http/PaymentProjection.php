<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http;

use App\Modules\Payments\Models\Payment;

/**
 * The API projection of a payment. Allow-list; integer Rupiah. The gateway
 * reference is included for reconciliation but is never a secret (Rule 03); no
 * membership id or internal note is exposed.
 */
final class PaymentProjection
{
    /** @return array<string, mixed> */
    public static function summary(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'order_id' => $payment->order_id,
            'kind' => $payment->kind,
            'method' => $payment->method,
            'status' => $payment->status,
            'amount_rupiah' => (int) $payment->amount_rupiah,
            'reverses_payment_id' => $payment->reverses_payment_id,
            'gateway_reference' => $payment->gateway_reference,
            'received_at' => optional($payment->received_at)->toIso8601String(),
            'version' => (int) $payment->version,
            'created_at' => optional($payment->created_at)->toIso8601String(),
        ];
    }
}
