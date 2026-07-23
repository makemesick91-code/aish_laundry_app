<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Http\PaymentProjection;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Payments and the append-only ledger (FR-061 … FR-069).
 *
 * There is no update and no destroy endpoint: a payment is corrected by a
 * reversal, never edited or deleted (FR-066, FR-067). Paid state is never taken
 * from the request body — PaymentRegistry decides it (FR-064). Every lookup is
 * tenant-scoped first, so a foreign id 404s like an absent one (Rule 48).
 */
final class PaymentController
{
    public function __construct(private readonly PaymentRegistry $registry) {}

    /** Payments for one order. */
    public function index(string $order): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);
        $context = app(TenantContext::class);
        $orderModel = $this->findOrder($context, $order);
        Gate::authorize('view', $orderModel);

        $payments = Payment::query()->forTenant($context->tenantId())
            ->where('order_id', $orderModel->id)->orderBy('created_at')->get();

        return ApiResponse::success([
            'payments' => $payments->map(static fn (Payment $p) => PaymentProjection::summary($p))->all(),
        ]);
    }

    /** Record a payment against an order (FR-061, FR-062). */
    public function store(Request $request, string $order): JsonResponse
    {
        Gate::authorize('create', Payment::class);
        $context = app(TenantContext::class);
        $orderModel = $this->findOrder($context, $order);

        $validated = $request->validate([
            'method' => ['required', 'in:cash,bank_transfer,qris'],
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'client_reference' => ['required', 'string', 'max:190'],
            'gateway_reference' => ['sometimes', 'nullable', 'string', 'max:190'],
        ]);

        $payment = $this->registry->record($context, $orderModel, $validated);

        return ApiResponse::success(['payment' => PaymentProjection::summary($payment)], 201);
    }

    /** Confirm a pending gateway payment from a verified callback (FR-063). */
    public function confirm(Request $request, string $payment): JsonResponse
    {
        Gate::authorize('create', Payment::class);
        $context = app(TenantContext::class);
        $model = $this->findPayment($context, $payment);

        $validated = $request->validate([
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'gateway_reference' => ['sometimes', 'nullable', 'string', 'max:190'],
        ]);

        return ApiResponse::success(['payment' => PaymentProjection::summary($this->registry->confirmGateway($context, $model, $validated))]);
    }

    /** Reverse part or all of a payment (FR-065, FR-067). */
    public function reverse(Request $request, string $payment): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findPayment($context, $payment);
        Gate::authorize('refund', $model);

        $validated = $request->validate([
            'amount_rupiah' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reversal = $this->registry->reverse($context, $model, (int) $validated['amount_rupiah'], $validated['reason']);

        return ApiResponse::success(['payment' => PaymentProjection::summary($reversal)], 201);
    }

    private function findOrder(TenantContext $context, string $id): Order
    {
        $model = Order::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }

    private function findPayment(TenantContext $context, string $id): Payment
    {
        $model = Payment::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
