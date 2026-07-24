<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderRegistry;
use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT D. The IMMUTABLE first READY_FOR_PICKUP
 * anchor (FR-076, FR-077).
 *
 * When a production job is CLOSED (a passing/waived QC verdict, Unit C), the
 * order first becomes ready. This service writes the anchor row EXACTLY ONCE and
 * transitions the order, atomically:
 *
 *   - the anchor is `production_ready_events`: UNIQUE (tenant_id, order_id) makes
 *     it at most one row, and append-only triggers make it immutable at the
 *     database boundary — so the occurred_at Step 9 will read as the aging anchor
 *     can never be reset, replaced, or deleted;
 *   - `insertOrIgnore` under the job row lock means a retry/replay preserves the
 *     ORIGINAL occurred_at rather than overwriting it;
 *   - the order status transition is Ordering-owned and idempotent
 *     (OrderRegistry::markReadyForPickup); production never writes the orders
 *     table directly (Rule 06 hard rule 6).
 *
 * Step 9 (aging, the reminder ladder) is NOT implemented here — this records the
 * fact only.
 */
class ProductionReadyService
{
    public function __construct(private readonly OrderRegistry $orders)
    {
    }

    public function markReady(TenantContext $context, string $jobId, string $clientReference): Order
    {
        return DB::transaction(function () use ($context, $jobId, $clientReference) {
            $job = ProductionJob::query()
                ->forTenant($context->tenantId())
                ->where('id', $jobId)
                ->lockForUpdate()
                ->first();

            if ($job === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            $order = Order::query()->forTenant($context->tenantId())->find($job->order_id);
            if ($order === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            // Replay: a prior MarkedReadyForPickup with this reference returns the
            // order as-is (already ready) — exactly-once, no second effect.
            $prior = ProductionEvent::query()
                ->forTenant($context->tenantId())
                ->where('client_reference', $clientReference)
                ->first();
            if ($prior !== null) {
                if ($prior->type !== 'MarkedReadyForPickup') {
                    throw ApiException::of(
                        ErrorCode::CONFLICT,
                        'client_reference telah dipakai untuk perintah yang berbeda.',
                        ['client_reference' => ['reused_different_payload']],
                    );
                }

                return $order;
            }

            // The job must have completed production (QC passed/waived -> CLOSED).
            if ($job->state !== ProductionJob::STATE_CLOSED) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Pekerjaan produksi belum selesai; tidak dapat ditandai siap diambil.',
                    ['state' => ['not_closed']],
                );
            }

            // Write the anchor EXACTLY ONCE. insertOrIgnore preserves the original
            // occurred_at on any retry; the UNIQUE (tenant_id, order_id) index is
            // the backstop. The append-only trigger forbids any later mutation.
            DB::table('production_ready_events')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $context->tenantId(),
                'order_id' => $order->id,
                'job_id' => $job->id,
                'recorded_by_membership_id' => $context->membershipId(),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Order status transition through the Ordering interface (idempotent).
            $order = $this->orders->markReadyForPickup($context, $order);

            ProductionEvent::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $context->tenantId(),
                'job_id' => $job->id,
                'type' => 'MarkedReadyForPickup',
                'actor_membership_id' => $context->membershipId(),
                'payload' => ['order_id' => $order->id],
                'client_reference' => $clientReference,
                'occurred_at' => now(),
            ]);

            return $order;
        });
    }
}
