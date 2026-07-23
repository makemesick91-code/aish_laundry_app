<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers;

use App\Modules\Ordering\Http\OrderProjection;
use App\Modules\Ordering\Http\ReceiptProjection;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderRegistry;
use App\Modules\Payments\Models\Payment;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Order intake and lifecycle (FR-048 … FR-060).
 *
 * EVERY LOOKUP IS TENANT-SCOPED FIRST: findOrFail() filters on the verified
 * tenant id, so a foreign or absent id produce the SAME 404 (Rule 48). Sort and
 * filter inputs are allow-listed enums, never raw columns. There is no destroy
 * endpoint — an order is cancelled, never deleted (FR-066).
 *
 * Totals are NEVER read from the request: the client sends what to order, and
 * OrderRegistry computes what it costs from the price snapshot (FR-051).
 */
final class OrderController
{
    private const SORTABLE = ['created_at', 'order_number', 'status'];

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly OrderRegistry $registry) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);
        $context = app(TenantContext::class);

        $sort = (string) $request->query('sort', 'created_at');
        if (! in_array($sort, self::SORTABLE, true)) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Kolom pengurutan tidak dikenal.', ['sort' => self::SORTABLE]);
        }
        $perPage = min(max((int) $request->query('per_page', 25), 1), self::MAX_PER_PAGE);

        $query = Order::query()->forTenant($context->tenantId());

        if (($number = trim((string) $request->query('order_number', ''))) !== '') {
            $query->where('order_number', $number);
        }
        if (($status = $request->query('status')) !== null) {
            if (! in_array($status, Order::CANONICAL_STATUSES, true)) {
                throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Status tidak dikenal.', ['status' => ['invalid']]);
            }
            $query->where('status', $status);
        }
        if (($outletId = $request->query('outlet_id')) !== null) {
            $query->where('outlet_id', (string) $outletId);
        }
        if (($customerId = $request->query('customer_id')) !== null) {
            $query->where('customer_id', (string) $customerId);
        }

        $page = $query->orderByDesc($sort)->paginate($perPage);

        return ApiResponse::success([
            'orders' => array_map(static fn (Order $o) => OrderProjection::summary($o), $page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $order);
        Gate::authorize('view', $model);

        return ApiResponse::success(['order' => OrderProjection::detail($model)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Order::class);
        $context = app(TenantContext::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'outlet_id' => ['required', 'uuid'],
            'client_reference' => ['required', 'string', 'max:190'],
            'discount_rupiah' => ['sometimes', 'integer', 'min:0'],
            'special_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.target_type' => ['required', 'in:service,package,addon'],
            'lines.*.target_id' => ['required', 'uuid'],
            'lines.*.quantity_milli' => ['required', 'integer', 'min:1'],
            'lines.*.discount_rupiah' => ['sometimes', 'integer', 'min:0'],
        ]);

        $order = $this->registry->create($context, $validated);

        return ApiResponse::success(['order' => OrderProjection::detail($order)], 201);
    }

    public function place(string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $order);
        Gate::authorize('update', $model);

        return ApiResponse::success(['order' => OrderProjection::detail($this->registry->place($context, $model))]);
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $order);
        Gate::authorize('cancel', $model);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return ApiResponse::success(['order' => OrderProjection::summary($this->registry->cancel($context, $model, $validated['reason']))]);
    }

    public function receipt(string $order): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $order);
        Gate::authorize('view', $model);

        $payments = Payment::query()->forTenant($context->tenantId())->where('order_id', $model->id)->orderBy('created_at')->get();

        return ApiResponse::success(['receipt' => ReceiptProjection::of($model, $payments)]);
    }

    private function findOrFail(TenantContext $context, string $id): Order
    {
        $model = Order::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
