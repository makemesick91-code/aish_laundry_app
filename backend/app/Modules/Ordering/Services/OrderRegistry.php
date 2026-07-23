<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderLine;
use App\Modules\Ordering\Support\OrderNumberGenerator;
use App\Modules\Ordering\Support\OrderPricing;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of orders.
 *
 * Every method takes the resolved TenantContext explicitly (Rule 20 hard rule 6).
 * Totals are SERVER-AUTHORITATIVE (FR-051): the caller supplies what to order,
 * never what it costs. Unit prices are RESOLVED server-side from the tenant's
 * active price list and SNAPSHOT onto the line (FR-036), so a later price-list
 * change can never alter this order — the guarantee Step 4 prepared and Step 5
 * proves.
 *
 * CREATE IS IDEMPOTENT (FR-059, FR-062). A repeated `client_reference` returns
 * the original order, never a second one. The check-then-insert is backed by the
 * `(tenant_id, client_reference)` UNIQUE constraint: the loser of a concurrent
 * race gets a constraint violation and is handed the winner's order, so a retry
 * on a flaky connection cannot create a duplicate.
 *
 * STATUS MOVES ONLY ALONG ENUMERATED TRANSITIONS (Rule 19). There is no generic
 * "set status". Step 5 performs intake only — DRAFT → RECEIVED and
 * {DRAFT,RECEIVED} → CANCELLED. The production stages are Step 6 and are refused
 * here.
 */
final class OrderRegistry
{
    private const CODE_ALLOCATION_ATTEMPTS = 3;

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OrderNumberGenerator $numbers,
    ) {}

    /**
     * @param  array{
     *     customer_id: string,
     *     outlet_id: string,
     *     client_reference: string,
     *     special_instructions?: ?string,
     *     discount_rupiah?: int,
     *     lines: list<array{target_type: string, target_id: string, quantity_milli: int, discount_rupiah?: int}>
     * }  $input
     */
    public function create(TenantContext $context, array $input): Order
    {
        $tenantId = $context->tenantId();

        $clientReference = trim((string) ($input['client_reference'] ?? ''));
        if ($clientReference === '') {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'client_reference wajib diisi untuk mencegah pesanan ganda.',
                ['client_reference' => ['required']],
            );
        }

        // IDEMPOTENCY (FR-062): a replay returns the original order.
        $existing = Order::query()
            ->forTenant($tenantId)
            ->where('client_reference', $clientReference)
            ->first();
        if ($existing !== null) {
            return $existing->load('lines');
        }

        $outlet = $this->resolveOutlet($tenantId, (string) ($input['outlet_id'] ?? ''));
        $this->assertCustomerInTenant($tenantId, (string) ($input['customer_id'] ?? ''));

        $lines = $input['lines'] ?? [];
        if ($lines === []) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Pesanan harus memiliki minimal satu baris layanan.',
                ['lines' => ['required']],
            );
        }

        // Resolve every price and compute every subtotal BEFORE opening the
        // transaction, so a bad line fails fast without a half-written order.
        $resolved = [];
        $lineSubtotals = [];
        foreach (array_values($lines) as $index => $line) {
            $r = $this->resolveLine($tenantId, (string) $outlet->laundry_brand_id, $line, $index + 1);
            $resolved[] = $r;
            $lineSubtotals[] = $r['subtotal_rupiah'];
        }

        $orderDiscount = (int) ($input['discount_rupiah'] ?? 0);
        try {
            $totals = OrderPricing::orderTotals($lineSubtotals, $orderDiscount);
        } catch (\InvalidArgumentException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, $e->getMessage(), ['discount_rupiah' => ['invalid']]);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($context, $tenantId, $outlet, $input, $clientReference, $resolved, $orderDiscount, $totals) {
                    $order = new Order(['special_instructions' => $input['special_instructions'] ?? null]);
                    $order->tenant_id = $tenantId;
                    $order->outlet_id = $outlet->id;
                    $order->customer_id = (string) $input['customer_id'];
                    $order->order_number = $this->numbers->next($tenantId);
                    $order->client_reference = $clientReference;
                    $order->status = Order::STATUS_DRAFT;
                    $order->currency = 'IDR';
                    $order->subtotal_rupiah = $totals['subtotal'];
                    $order->discount_rupiah = $orderDiscount;
                    $order->total_rupiah = $totals['total'];
                    $order->created_by_membership_id = $context->membershipId();
                    $order->save();

                    foreach ($resolved as $i => $r) {
                        $orderLine = new OrderLine();
                        $orderLine->tenant_id = $tenantId;
                        $orderLine->order_id = $order->id;
                        $orderLine->line_number = $i + 1;
                        $orderLine->service_id = $r['service_id'];
                        $orderLine->service_package_id = $r['service_package_id'];
                        $orderLine->service_addon_id = $r['service_addon_id'];
                        $orderLine->service_name = $r['service_name'];
                        $orderLine->unit = $r['unit'];
                        $orderLine->quantity_milli = $r['quantity_milli'];
                        $orderLine->unit_price_rupiah = $r['unit_price_rupiah'];
                        $orderLine->discount_rupiah = $r['discount_rupiah'];
                        $orderLine->subtotal_rupiah = $r['subtotal_rupiah'];
                        $orderLine->price_list_id = $r['price_list_id'];
                        $orderLine->price_list_item_id = $r['price_list_item_id'];
                        $orderLine->save();
                    }

                    $this->audit->record(
                        action: AuditAction::ORDER_CREATED,
                        subjectType: Order::class,
                        subjectId: $order->id,
                        tenantId: $tenantId,
                        actorUserId: $context->userId(),
                        actorMembershipId: $context->membershipId(),
                        outletId: $outlet->id,
                        // The order NUMBER and the total, not the customer or the
                        // line detail: enough to find and reconcile the order,
                        // nothing that re-copies personal data into the trail.
                        metadata: ['order_number' => $order->order_number, 'total_rupiah' => $order->total_rupiah],
                    );

                    return $order->load('lines');
                });
            } catch (UniqueConstraintViolationException $e) {
                // A concurrent create won the client_reference race: hand back the
                // winner's order (idempotent), not a duplicate.
                if (str_contains($e->getMessage(), 'orders_tenant_client_ref_unique')) {
                    $winner = Order::query()->forTenant($tenantId)->where('client_reference', $clientReference)->first();
                    if ($winner !== null) {
                        return $winner->load('lines');
                    }
                }
                // Otherwise it is an order_number collision — bounded retry.
                if ($attempt >= self::CODE_ALLOCATION_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /** DRAFT → RECEIVED (FR-048). */
    public function place(TenantContext $context, Order $order): Order
    {
        $this->assertSameTenant($context, $order);
        $this->assertTransition($order, Order::STATUS_DRAFT, Order::STATUS_RECEIVED);

        $order->status = Order::STATUS_RECEIVED;
        $order->placed_at = now();
        $order->placed_by_membership_id = $context->membershipId();
        $order->updated_by_membership_id = $context->membershipId();
        $order->save();

        $this->audit->record(
            action: AuditAction::ORDER_PLACED,
            subjectType: Order::class,
            subjectId: $order->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            outletId: $order->outlet_id,
            metadata: ['order_number' => $order->order_number],
        );

        return $order;
    }

    /** {DRAFT, RECEIVED} → CANCELLED, with a recorded reason (FR-058). */
    public function cancel(TenantContext $context, Order $order, string $reason): Order
    {
        $this->assertSameTenant($context, $order);

        $reason = trim($reason);
        if ($reason === '') {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Pembatalan pesanan wajib menyertakan alasan.',
                ['reason' => ['required']],
            );
        }

        if (! in_array($order->status, [Order::STATUS_DRAFT, Order::STATUS_RECEIVED], true)) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Pesanan pada status ini tidak dapat dibatalkan.',
                ['status' => [$order->status]],
            );
        }

        $order->status = Order::STATUS_CANCELLED;
        $order->cancelled_at = now();
        $order->cancellation_reason = $reason;
        $order->cancelled_by_membership_id = $context->membershipId();
        $order->updated_by_membership_id = $context->membershipId();
        $order->save();

        $this->audit->record(
            action: AuditAction::ORDER_CANCELLED,
            subjectType: Order::class,
            subjectId: $order->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            outletId: $order->outlet_id,
            reason: $reason,
            metadata: ['order_number' => $order->order_number],
        );

        return $order;
    }

    // --- resolution & guards ------------------------------------------------

    private function resolveOutlet(string $tenantId, string $outletId): object
    {
        $outlet = DB::table('outlets')
            ->where('tenant_id', $tenantId)
            ->where('id', $outletId)
            ->whereNull('deleted_at')
            ->first(['id', 'laundry_brand_id']);

        if ($outlet === null) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Outlet tidak ditemukan pada tenant ini.', ['outlet_id' => ['not_found']]);
        }

        return $outlet;
    }

    private function assertCustomerInTenant(string $tenantId, string $customerId): void
    {
        $exists = DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->where('id', $customerId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Pelanggan tidak ditemukan pada tenant ini.', ['customer_id' => ['not_found']]);
        }
    }

    /**
     * Resolve one line: verify the target belongs to the tenant, look up its
     * price in the brand's ACTIVE DEFAULT price list, snapshot it, and compute
     * the subtotal. A client cannot supply the price — that is the FR-051
     * guarantee — nor reach another tenant's service (Rule 48).
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function resolveLine(string $tenantId, string $brandId, array $line, int $lineNumber): array
    {
        $targetType = (string) ($line['target_type'] ?? '');
        $targetId = (string) ($line['target_id'] ?? '');
        $quantityMilli = (int) ($line['quantity_milli'] ?? 0);
        $lineDiscount = (int) ($line['discount_rupiah'] ?? 0);

        if ($quantityMilli <= 0) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Jumlah pada baris {$lineNumber} harus lebih dari nol.", ['lines' => ['quantity']]);
        }

        [$serviceId, $packageId, $addonId] = [null, null, null];
        $itemColumn = null;

        switch ($targetType) {
            case 'service':
                $svc = DB::table('service_catalog')->where('tenant_id', $tenantId)->where('id', $targetId)->whereNull('deleted_at')->first(['name', 'unit_kind']);
                if ($svc === null) {
                    throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Layanan pada baris {$lineNumber} tidak ditemukan.", ['lines' => ['service_not_found']]);
                }
                $serviceId = $targetId;
                $itemColumn = 'service_id';
                $unit = $svc->unit_kind === 'kiloan' ? OrderLine::UNIT_KILOGRAM : OrderLine::UNIT_PIECE;
                $name = $svc->name;
                break;
            case 'package':
                $pkg = DB::table('service_packages')->where('tenant_id', $tenantId)->where('id', $targetId)->whereNull('deleted_at')->first(['name']);
                if ($pkg === null) {
                    throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Paket pada baris {$lineNumber} tidak ditemukan.", ['lines' => ['package_not_found']]);
                }
                $packageId = $targetId;
                $itemColumn = 'service_package_id';
                $unit = OrderLine::UNIT_PACKAGE;
                $name = $pkg->name;
                break;
            case 'addon':
                $add = DB::table('service_addons')->where('tenant_id', $tenantId)->where('id', $targetId)->whereNull('deleted_at')->first(['name']);
                if ($add === null) {
                    throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Tambahan pada baris {$lineNumber} tidak ditemukan.", ['lines' => ['addon_not_found']]);
                }
                $addonId = $targetId;
                $itemColumn = 'service_addon_id';
                $unit = OrderLine::UNIT_ADDON;
                $name = $add->name;
                break;
            default:
                throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Jenis baris '{$targetType}' tidak dikenal.", ['lines' => ['target_type']]);
        }

        // The brand's ACTIVE DEFAULT price list (at most one, by the partial
        // unique index Step 4 created), and the item priced for this target.
        $priceList = DB::table('price_lists')
            ->where('tenant_id', $tenantId)
            ->where('laundry_brand_id', $brandId)
            ->where('status', 'active')
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($priceList === null) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, 'Tidak ada daftar harga aktif untuk brand outlet ini.', ['price_list' => ['no_active_default']]);
        }

        $item = DB::table('price_list_items')
            ->where('tenant_id', $tenantId)
            ->where('price_list_id', $priceList->id)
            ->where($itemColumn, $targetId)
            ->first(['id', 'amount_rupiah']);

        if ($item === null) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Harga untuk baris {$lineNumber} tidak ada di daftar harga aktif.", ['lines' => ['no_price']]);
        }

        $unitPrice = (int) $item->amount_rupiah;
        try {
            $subtotal = OrderPricing::lineSubtotal($unitPrice, $quantityMilli, $lineDiscount);
        } catch (\InvalidArgumentException $e) {
            throw ApiException::of(ErrorCode::VALIDATION_FAILED, "Baris {$lineNumber}: {$e->getMessage()}", ['lines' => ['invalid_money']]);
        }

        return [
            'service_id' => $serviceId,
            'service_package_id' => $packageId,
            'service_addon_id' => $addonId,
            'service_name' => $name,
            'unit' => $unit,
            'quantity_milli' => $quantityMilli,
            'unit_price_rupiah' => $unitPrice,
            'discount_rupiah' => $lineDiscount,
            'subtotal_rupiah' => $subtotal,
            'price_list_id' => $priceList->id,
            'price_list_item_id' => $item->id,
        ];
    }

    private function assertTransition(Order $order, string $from, string $to): void
    {
        if ($order->status !== $from) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                "Transisi status tidak diizinkan dari {$order->status} ke {$to}.",
                ['status' => [$order->status]],
            );
        }
    }

    private function assertSameTenant(TenantContext $context, Order $order): void
    {
        if (! hash_equals($context->tenantId(), $order->tenant_id)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
