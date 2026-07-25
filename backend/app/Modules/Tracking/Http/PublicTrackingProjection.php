<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Payments\Support\OrderBalance;
use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Tracking\Models\TrackingToken;
use App\Modules\Tracking\Support\CustomerVisibleStatus;
use App\Modules\Tracking\Support\PublicMask;
use Illuminate\Support\Facades\DB;

/**
 * STEP 7 · UNIT B — THE PUBLIC TRACKING PROJECTION (FR-089, FR-090).
 *
 * A SEPARATE READ MODEL, NOT A FILTERED ORDER (TRK-008)
 * ----------------------------------------------------
 * This class BUILDS an array from an allow-list. It does not take an order, hide
 * some fields, and hand the rest over. The difference is the whole security
 * property: a deny-list fails open the moment somebody adds a column, whereas a
 * field that is never assembled cannot leak through a template bug, a debug dump,
 * a JSON cast, or a future contributor's `@dd`.
 *
 * `FORBIDDEN` below is therefore documentation and a TEST FIXTURE, not a filter.
 * `PublicProjectionTest` asserts the built array's key set equals `ALLOWED`
 * exactly, and separately asserts that no forbidden value appears anywhere in the
 * serialised output — including nested, including as a substring.
 *
 * WHAT IS NEVER HERE, WITH OR WITHOUT OTP
 * ---------------------------------------
 * A street address in any form (TRK-010 — not to the customer, not to a forwarded
 * recipient, not behind OTP). A full phone (FR-090). Any other order of the same
 * customer (TRK-015). Internal notes / `special_instructions` (TRK-016). Laundry or
 * QC photographs and their object-storage keys (TRK-017). Staff identity. Cost,
 * margin, or discount internals. Internal UUIDs. The token or its hash. Provider
 * metadata. Any debug field.
 *
 * MASKING HAPPENS HERE, AT BUILD TIME (TRK-018). The returned array never holds an
 * unmasked name or phone.
 */
final class PublicTrackingProjection
{
    /**
     * The EXACT key set FR-089 authorises. Asserted by test, not merely intended.
     *
     * @var list<string>
     */
    public const ALLOWED = [
        'order_number',
        'brand',
        'outlet',
        'service_types',
        'status',
        'status_history',
        'estimated_completion',
        'amount_due_rupiah',
        'payment_state',
        'customer',
        'available_actions',
        'is_ready_for_pickup',
        'first_ready_at',
        'generated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function build(TrackingToken $token, Order $order): array
    {
        $tenantId = $order->tenant_id;

        $outlet = Outlet::query()->forTenant($tenantId)->find($order->outlet_id);
        $brand = $outlet === null
            ? null
            : LaundryBrand::query()->forTenant($tenantId)->find($outlet->laundry_brand_id);
        $customer = Customer::query()->forTenant($tenantId)->find($order->customer_id);

        $balance = OrderBalance::for($order);

        // The immutable Step 6 anchor, READ ONLY. Step 7 never writes, restarts, or
        // re-derives it (Rule 10, FR-076/FR-077). Reading it here is what makes the
        // "siap diambil" claim survive a later rework — see $readyAt below.
        $firstReadyAt = DB::table('production_ready_events')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->value('occurred_at');

        return [
            'order_number' => $order->order_number,

            // Identity only. No address, no phone, no operating hours, no zone.
            'brand' => ['name' => $brand?->name ?? ''],
            'outlet' => ['name' => $outlet?->name ?? ''],

            // WHAT was ordered, never how much of it or what it cost per unit.
            'service_types' => self::serviceTypes($tenantId, $order->id),

            'status' => CustomerVisibleStatus::for($order->status),
            'status_history' => self::history($order, $firstReadyAt),

            // Nullable and explicitly an ESTIMATE. Step 7 computes no ETA and the
            // product never presents an estimate as a guarantee (Rule 09 hard
            // rule 1, TRACKING_DOMAIN §8).
            'estimated_completion' => null,

            // Integer Rupiah, READ from the Step 5 ledger. Step 7 never computes,
            // rounds, or mutates money (Rule 04).
            'amount_due_rupiah' => (int) $balance['outstanding_rupiah'],
            'payment_state' => self::paymentState((string) $balance['state']),

            // Masked at BUILD time (TRK-009, TRK-011, TRK-018).
            'customer' => [
                'masked_name' => PublicMask::name($customer?->name),
                'masked_phone' => PublicMask::phone($customer?->phone_normalized),
            ],

            'available_actions' => self::availableActions($order),

            // Sticky by construction: once the anchor exists it stays true, even if
            // the order later re-enters REWORK and returns. A customer told "siap
            // diambil" is never told "not ready yet" afterwards.
            'is_ready_for_pickup' => $firstReadyAt !== null,
            'first_ready_at' => $firstReadyAt === null ? null : self::iso($firstReadyAt),

            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * The service types on the order, deduplicated.
     *
     * `service_name` is the snapshot Step 5 captured on the line, so this survives
     * a later catalogue rename exactly as the price snapshot does (FR-036).
     *
     * @return list<string>
     */
    private static function serviceTypes(string $tenantId, string $orderId): array
    {
        $names = DB::table('order_lines')
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->orderBy('line_number')
            ->pluck('service_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_map(static fn ($n): string => (string) $n, $names);
    }

    /**
     * The customer-visible timeline, built from RECORDED FACTS ONLY.
     *
     * Nothing here is inferred or back-dated. Each entry has a real timestamp from
     * a real row: `orders.placed_at`, the first production event, the first QC
     * inspection, the immutable ready anchor, `orders.cancelled_at`. Internal stage
     * granularity (SORTING vs WASHING vs DRYING) is deliberately collapsed — the
     * customer sees "sedang dikerjakan", not the tenant's floor layout.
     *
     * @return list<array{code: string, label: string, occurred_at: string}>
     */
    private static function history(Order $order, mixed $firstReadyAt): array
    {
        $tenantId = $order->tenant_id;
        $entries = [];

        if ($order->placed_at !== null) {
            $entries[] = self::entry(CustomerVisibleStatus::RECEIVED, $order->placed_at->toIso8601String());
        }

        $jobIds = ProductionJob::query()
            ->forTenant($tenantId)
            ->where('order_id', $order->id)
            ->pluck('id')
            ->all();

        if ($jobIds !== []) {
            $startedAt = ProductionEvent::query()
                ->forTenant($tenantId)
                ->whereIn('job_id', $jobIds)
                ->whereIn('type', ['StartStage', 'CompleteStage'])
                ->orderBy('occurred_at')
                ->value('occurred_at');

            if ($startedAt !== null) {
                $entries[] = self::entry(CustomerVisibleStatus::IN_PROGRESS, self::iso($startedAt));
            }

            $inspectedAt = DB::table('quality_control_inspections')
                ->where('tenant_id', $tenantId)
                ->whereIn('job_id', $jobIds)
                ->orderBy('inspected_at')
                ->value('inspected_at');

            if ($inspectedAt !== null) {
                $entries[] = self::entry(CustomerVisibleStatus::CHECKING, self::iso($inspectedAt));
            }
        }

        if ($firstReadyAt !== null) {
            $entries[] = self::entry(CustomerVisibleStatus::READY, self::iso($firstReadyAt));
        }

        if ($order->cancelled_at !== null) {
            // Deliberately WITHOUT `cancellation_reason`: the reason is an internal
            // operational note (FR-058) and may name a staff member or a dispute.
            $entries[] = self::entry(CustomerVisibleStatus::CANCELLED, $order->cancelled_at->toIso8601String());
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($a['occurred_at'], $b['occurred_at']));

        return $entries;
    }

    /**
     * @return array{code: string, label: string, occurred_at: string}
     */
    private static function entry(string $code, string $occurredAt): array
    {
        return ['code' => $code, 'label' => CustomerVisibleStatus::label($code), 'occurred_at' => $occurredAt];
    }

    /**
     * @return array{code: string, label: string}
     */
    private static function paymentState(string $state): array
    {
        return match ($state) {
            OrderBalance::STATE_PAID => ['code' => 'LUNAS', 'label' => 'Lunas'],
            OrderBalance::STATE_PARTIAL => ['code' => 'SEBAGIAN', 'label' => 'Dibayar sebagian'],
            default => ['code' => 'BELUM_DIBAYAR', 'label' => 'Belum dibayar'],
        };
    }

    /**
     * The actions FR-089 means by "available actions", and no others.
     *
     * Exactly the two FR-091 sensitive actions, offered only while the order can
     * still be affected by them. Requesting a PICKUP or a DELIVERY is Step 8 and is
     * deliberately absent — offering a control that does nothing would be the dead
     * control Rule 34 rejects, and building the effect would be the scope leak
     * DEC-0039 §5 forbids.
     *
     * @return list<array{code: string, label: string, requires_otp: bool}>
     */
    private static function availableActions(Order $order): array
    {
        $terminal = in_array($order->status, [Order::STATUS_CANCELLED, 'COMPLETED'], true);

        if ($terminal) {
            return [];
        }

        return [
            [
                'code' => 'change_delivery_address',
                'label' => 'Ubah alamat pengantaran',
                'requires_otp' => true,
            ],
            [
                'code' => 'request_schedule_change',
                'label' => 'Minta perubahan jadwal',
                'requires_otp' => true,
            ],
        ];
    }

    private static function iso(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance(
                \DateTimeImmutable::createFromInterface($value)
            )->toIso8601String();
        }

        return \Illuminate\Support\Carbon::parse((string) $value)->toIso8601String();
    }
}
