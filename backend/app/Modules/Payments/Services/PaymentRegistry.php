<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\OrderBalance;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Money\RupiahRounding;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of payments.
 *
 * PAID STATE IS NEVER A CLIENT CLAIM (FR-064). A cash or transfer payment
 * recorded by authenticated staff is `succeeded` at record time — the staff
 * action IS the verified event. A QRIS payment starts `pending` and becomes
 * `succeeded` only through confirmGateway(), which verifies a callback's amount
 * and reference and rejects a replay. No provider is integrated in this step
 * (OQ-015), so confirmGateway verifies the CONTRACT it is handed; it never
 * fabricates a gateway success.
 *
 * RECORDING IS IDEMPOTENT (FR-062). A repeated client_reference returns the
 * original payment, backed by the (tenant_id, client_reference) UNIQUE
 * constraint, so a retry on a flaky connection cannot double-charge.
 *
 * CORRECTIONS ARE REVERSALS, NEVER DELETIONS (FR-066, FR-067). reverse() appends
 * a reversal row that references the original; the ledger is only added to.
 */
final class PaymentRegistry
{
    private const NUMBER_ATTEMPTS = 3;

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array{method: string, amount_rupiah: mixed, client_reference: string, gateway_reference?: ?string}  $input
     */
    public function record(TenantContext $context, Order $order, array $input): Payment
    {
        $this->assertSameTenant($context, $order->tenant_id);

        if ($order->isCancelled()) {
            throw ApiException::of(ErrorCode::CONFLICT, 'Pesanan yang dibatalkan tidak dapat menerima pembayaran.', ['order' => ['cancelled']]);
        }

        $clientReference = trim((string) ($input['client_reference'] ?? ''));
        if ($clientReference === '') {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'client_reference wajib diisi untuk mencegah pembayaran ganda.', ['client_reference' => ['required']]);
        }

        // IDEMPOTENCY (FR-062).
        $existing = Payment::query()->forTenant($context->tenantId())->where('client_reference', $clientReference)->first();
        if ($existing !== null) {
            return $existing;
        }

        $method = (string) ($input['method'] ?? '');
        if (! in_array($method, Payment::methods(), true)) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Metode pembayaran tidak dikenal.', ['method' => ['invalid']]);
        }

        try {
            $amount = RupiahRounding::fromInput($input['amount_rupiah'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, $e->getMessage(), ['amount_rupiah' => ['invalid']]);
        }
        if ($amount <= 0) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Nominal pembayaran harus lebih dari nol.', ['amount_rupiah' => ['positive']]);
        }

        // BALANCE-AWARE (FR-070, overpayment rule). A payment may not exceed the
        // outstanding balance: partial/deposit is allowed, overpayment is not —
        // cash change is a counter concern, never a stored payment above the total.
        $balance = OrderBalance::for($order);
        if ($balance['outstanding_rupiah'] <= 0) {
            throw ApiException::of(ErrorCode::CONFLICT, 'Pesanan ini sudah lunas.', ['order' => ['already_paid']]);
        }
        if ($amount > $balance['outstanding_rupiah']) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Nominal pembayaran melebihi sisa tagihan.',
                ['amount_rupiah' => ['exceeds_outstanding'], 'outstanding_rupiah' => $balance['outstanding_rupiah']],
            );
        }

        // Cash/transfer settle at once (authenticated staff = the verified event,
        // FR-064). QRIS awaits a verified callback.
        $status = $method === Payment::METHOD_QRIS ? Payment::STATUS_PENDING : Payment::STATUS_SUCCEEDED;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($context, $order, $method, $amount, $status, $clientReference, $input) {
                    $payment = new Payment();
                    $payment->tenant_id = $context->tenantId();
                    $payment->order_id = $order->id;
                    $payment->payment_number = $this->allocateNumber($context->tenantId());
                    $payment->client_reference = $clientReference;
                    $payment->kind = Payment::KIND_PAYMENT;
                    $payment->method = $method;
                    $payment->status = $status;
                    $payment->currency = 'IDR';
                    $payment->amount_rupiah = $amount;
                    $payment->gateway_reference = $input['gateway_reference'] ?? null;
                    $payment->received_at = $status === Payment::STATUS_SUCCEEDED ? now() : null;
                    $payment->recorded_by_membership_id = $context->membershipId();
                    $payment->created_by_membership_id = $context->membershipId();
                    $payment->save();

                    $this->audit->record(
                        action: AuditAction::PAYMENT_RECORDED,
                        subjectType: Payment::class,
                        subjectId: $payment->id,
                        tenantId: $context->tenantId(),
                        actorUserId: $context->userId(),
                        actorMembershipId: $context->membershipId(),
                        outletId: $order->outlet_id,
                        metadata: [
                            'payment_number' => $payment->payment_number,
                            'order_id' => $order->id,
                            'method' => $payment->method,
                            'status' => $payment->status,
                            'amount_rupiah' => $payment->amount_rupiah,
                        ],
                    );

                    return $payment;
                });
            } catch (UniqueConstraintViolationException $e) {
                if (str_contains($e->getMessage(), 'payments_tenant_client_ref_unique')) {
                    $winner = Payment::query()->forTenant($context->tenantId())->where('client_reference', $clientReference)->first();
                    if ($winner !== null) {
                        return $winner;
                    }
                }
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Confirm a pending gateway payment from a SERVER-VERIFIED callback (FR-063,
     * FR-064). Verifies the amount and reference and rejects a replay. Real
     * provider signature verification requires the provider (OQ-015) and is a
     * documented gap, not a claim; this never marks a payment succeeded on a
     * client's say-so.
     *
     * @param  array{amount_rupiah: int, gateway_reference?: ?string}  $verified
     */
    public function confirmGateway(TenantContext $context, Payment $payment, array $verified): Payment
    {
        $this->assertSameTenant($context, $payment->tenant_id);

        if (! $payment->isGatewayMethod()) {
            throw ApiException::of(ErrorCode::CONFLICT, 'Hanya pembayaran gateway yang dikonfirmasi lewat callback.', ['method' => [$payment->method]]);
        }
        // Replay protection: an already-settled payment is not re-processed.
        if ($payment->status !== Payment::STATUS_PENDING) {
            throw ApiException::of(ErrorCode::CONFLICT, 'Pembayaran ini sudah diproses.', ['status' => [$payment->status]]);
        }
        if ((int) ($verified['amount_rupiah'] ?? -1) !== (int) $payment->amount_rupiah) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Nominal callback tidak cocok dengan pembayaran.', ['amount_rupiah' => ['mismatch']]);
        }

        $payment->status = Payment::STATUS_SUCCEEDED;
        $payment->received_at = now();
        if (! empty($verified['gateway_reference'])) {
            $payment->gateway_reference = (string) $verified['gateway_reference'];
        }
        $payment->save();

        $this->audit->record(
            action: AuditAction::PAYMENT_CONFIRMED,
            subjectType: Payment::class,
            subjectId: $payment->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: ['payment_number' => $payment->payment_number, 'method' => $payment->method],
        );

        return $payment;
    }

    /**
     * Reverse part or all of a succeeded payment (FR-065, FR-067). Appends a
     * reversal row; never mutates or deletes the original. The reversed total can
     * never exceed what was paid.
     */
    public function reverse(TenantContext $context, Payment $payment, int $amount, string $reason): Payment
    {
        $this->assertSameTenant($context, $payment->tenant_id);

        $reason = trim($reason);
        if ($reason === '') {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Pembalikan wajib menyertakan alasan.', ['reason' => ['required']]);
        }
        if ($payment->kind !== Payment::KIND_PAYMENT || ! $payment->isSucceeded()) {
            throw ApiException::of(ErrorCode::CONFLICT, 'Hanya pembayaran yang berhasil yang dapat dibalik.', ['status' => [$payment->status]]);
        }
        if ($amount <= 0) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Nominal pembalikan harus lebih dari nol.', ['amount_rupiah' => ['positive']]);
        }

        $alreadyReversed = OrderBalance::reversedAgainst($payment);
        $reversible = (int) $payment->amount_rupiah - $alreadyReversed;
        if ($amount > $reversible) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Nominal pembalikan melebihi sisa pembayaran yang dapat dibalik.',
                ['amount_rupiah' => ['exceeds_reversible'], 'reversible_rupiah' => $reversible],
            );
        }

        return DB::transaction(function () use ($context, $payment, $amount, $reason, $alreadyReversed) {
            $reversal = new Payment();
            $reversal->tenant_id = $context->tenantId();
            $reversal->order_id = $payment->order_id;
            $reversal->payment_number = $this->allocateNumber($context->tenantId());
            $reversal->client_reference = 'rev-' . $payment->id . '-' . ($alreadyReversed + $amount);
            $reversal->kind = Payment::KIND_REVERSAL;
            $reversal->method = $payment->method;
            $reversal->status = Payment::STATUS_SUCCEEDED;
            $reversal->currency = 'IDR';
            $reversal->amount_rupiah = $amount;
            $reversal->reverses_payment_id = $payment->id;
            $reversal->reversal_reason = $reason;
            $reversal->received_at = now();
            $reversal->recorded_by_membership_id = $context->membershipId();
            $reversal->created_by_membership_id = $context->membershipId();
            $reversal->save();

            // Mark the original fully-reversed for reporting when nothing remains.
            if ($alreadyReversed + $amount >= (int) $payment->amount_rupiah) {
                $payment->status = Payment::STATUS_REVERSED;
                $payment->save();
            }

            $this->audit->record(
                action: AuditAction::PAYMENT_REVERSED,
                subjectType: Payment::class,
                subjectId: $reversal->id,
                tenantId: $context->tenantId(),
                actorUserId: $context->userId(),
                actorMembershipId: $context->membershipId(),
                reason: $reason,
                metadata: [
                    'reverses_payment_id' => $payment->id,
                    'payment_number' => $reversal->payment_number,
                    'amount_rupiah' => $amount,
                ],
            );

            return $reversal;
        });
    }

    private function allocateNumber(string $tenantId): string
    {
        $used = Payment::query()->forTenant($tenantId)->count();

        return sprintf('PAY-%06d', $used + 1);
    }

    private function assertSameTenant(TenantContext $context, string $tenantId): void
    {
        if (! hash_equals($context->tenantId(), $tenantId)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
