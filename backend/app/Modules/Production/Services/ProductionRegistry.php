<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionItem;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT B.
 *
 * The single server-side authority for production job state (Rule 19). A client
 * sends a COMMAND, never a next-state; this service derives the target from the
 * command and the current state via an explicit registry, enforces the guards,
 * checks the optimistic version, and applies the transition atomically. It fails
 * CLOSED: an unenumerated transition, a stale version, or a cross-tenant target
 * changes nothing.
 *
 * Idempotency is a server contract (Rule 07/20): every command carries a
 * client_reference. A replay with the same reference and payload returns the
 * original result and produces no second effect; a replay with the same
 * reference and a DIFFERENT payload fails closed. Every command appends one row
 * to the append-only production_events log (FR-084).
 *
 * Production NEVER writes an order's money; the historical price snapshot is
 * untouched (Rule 04, FR-036).
 */
class ProductionRegistry
{
    // Commands the production floor may issue. QC-verdict-driven transitions
    // (AWAITING_QC -> CLOSED / -> REWORK_IN_PROGRESS) and rework are Unit C;
    // the READY_FOR_PICKUP order transition is Unit D.
    public const CMD_START_STAGE = 'StartStage';
    public const CMD_COMPLETE_STAGE = 'CompleteStage';
    public const CMD_BLOCK = 'BlockProductionJob';
    public const CMD_RESUME = 'ResumeProductionJob';
    public const CMD_SEND_TO_QC = 'SendToQualityControl';
    public const CMD_ABANDON = 'AbandonProductionJob';

    /** @var array<string, array<string, string>> state => command => target state. */
    private const TRANSITIONS = [
        ProductionJob::STATE_CREATED => [
            self::CMD_START_STAGE => ProductionJob::STATE_IN_PROGRESS,
            self::CMD_ABANDON => ProductionJob::STATE_ABANDONED,
        ],
        ProductionJob::STATE_IN_PROGRESS => [
            self::CMD_START_STAGE => ProductionJob::STATE_IN_PROGRESS,
            self::CMD_COMPLETE_STAGE => ProductionJob::STATE_IN_PROGRESS,
            self::CMD_BLOCK => ProductionJob::STATE_BLOCKED,
            self::CMD_SEND_TO_QC => ProductionJob::STATE_AWAITING_QC,
            self::CMD_ABANDON => ProductionJob::STATE_ABANDONED,
        ],
        ProductionJob::STATE_BLOCKED => [
            self::CMD_RESUME => ProductionJob::STATE_IN_PROGRESS,
            self::CMD_ABANDON => ProductionJob::STATE_ABANDONED,
        ],
        ProductionJob::STATE_REWORK_IN_PROGRESS => [
            self::CMD_ABANDON => ProductionJob::STATE_ABANDONED,
        ],
    ];

    /** Create the production job for an eligible order. Idempotent per order and per client_reference. */
    public function createJobForOrder(TenantContext $context, Order $order, string $clientReference): ProductionJob
    {
        $this->assertOrderInTenant($context, $order);

        if ($order->status !== Order::STATUS_RECEIVED) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Pesanan pada status ini tidak dapat masuk produksi.',
                ['order' => ['not_eligible_for_production']],
            );
        }

        return DB::transaction(function () use ($context, $order, $clientReference) {
            // Idempotent: an existing OPEN job for this order is returned as-is.
            $existing = ProductionJob::query()
                ->forTenant($context->tenantId())
                ->where('order_id', $order->id)
                ->whereNotIn('state', ProductionJob::TERMINAL_STATES)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $job = new ProductionJob([
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $order->outlet_id,
                'order_id' => $order->id,
                'state' => ProductionJob::STATE_CREATED,
                'created_by_membership_id' => $context->membershipId(),
                'updated_by_membership_id' => $context->membershipId(),
            ]);
            $job->save();

            foreach ($order->lines()->get() as $line) {
                ProductionItem::create([
                    'tenant_id' => $context->tenantId(),
                    'job_id' => $job->id,
                    'order_line_id' => $line->id,
                    'service_type' => $this->serviceTypeFor($line->unit),
                    'stage' => 'SORTING',
                    'quantity_done' => 0,
                    'units_total' => 1,
                    'units_done' => 0,
                    'flags' => [],
                ]);
            }

            $this->recordEvent($context, $job, 'ProductionJobCreated', ['order_id' => $order->id], $clientReference);

            return $job;
        });
    }

    /** Apply a production command to a job. The generic, server-authoritative transition path. */
    public function applyCommand(
        TenantContext $context,
        string $jobId,
        string $command,
        ?int $expectedVersion,
        string $clientReference,
        array $payload = [],
    ): ProductionJob {
        return DB::transaction(function () use ($context, $jobId, $command, $expectedVersion, $clientReference, $payload) {
            $job = ProductionJob::query()
                ->forTenant($context->tenantId())
                ->where('id', $jobId)
                ->lockForUpdate()
                ->first();

            // Cross-tenant / absent are indistinguishable (Rule 48).
            if ($job === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            // Idempotency (Rule 07/20): a prior event with this reference means
            // the command already applied. Same payload -> return the original
            // result; different payload -> fail closed.
            $prior = ProductionEvent::query()
                ->forTenant($context->tenantId())
                ->where('client_reference', $clientReference)
                ->first();

            if ($prior !== null) {
                if ($prior->type === $command && $this->payloadMatches($prior->payload ?? [], $payload)) {
                    return $job; // exactly-once: no second effect
                }

                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'client_reference telah dipakai untuk perintah yang berbeda.',
                    ['client_reference' => ['reused_different_payload']],
                );
            }

            // Optimistic concurrency: a stale expected version changes nothing.
            if ($expectedVersion !== null && (int) $job->version !== $expectedVersion) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Versi produksi sudah berubah; muat ulang keadaan terbaru.',
                    ['version' => ['version_conflict']],
                );
            }

            $target = self::TRANSITIONS[$job->state][$command] ?? null;
            if ($target === null) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    "Transisi produksi tidak diizinkan dari {$job->state} melalui {$command}.",
                    ['state' => ['invalid_transition']],
                );
            }

            $this->assertGuards($context, $job, $command, $payload);

            if ($command === self::CMD_BLOCK) {
                $job->block_reason_code = $payload['reason_code'];
                $job->block_reason = $payload['reason'] ?? null;
            } elseif ($command === self::CMD_RESUME) {
                $job->block_reason_code = null;
                $job->block_reason = null;
            }

            $job->state = $target;
            $job->updated_by_membership_id = $context->membershipId();
            $job->save(); // HasOptimisticVersion bumps version atomically

            $this->recordEvent($context, $job, $command, $payload, $clientReference);

            return $job;
        });
    }

    public function startStage(TenantContext $c, string $jobId, ?int $v, string $ref, string $stage): ProductionJob
    {
        return $this->applyCommand($c, $jobId, self::CMD_START_STAGE, $v, $ref, ['stage' => $stage]);
    }

    public function block(TenantContext $c, string $jobId, ?int $v, string $ref, string $reasonCode, ?string $reason = null): ProductionJob
    {
        return $this->applyCommand($c, $jobId, self::CMD_BLOCK, $v, $ref, ['reason_code' => $reasonCode, 'reason' => $reason]);
    }

    public function resume(TenantContext $c, string $jobId, ?int $v, string $ref): ProductionJob
    {
        return $this->applyCommand($c, $jobId, self::CMD_RESUME, $v, $ref);
    }

    public function sendToQualityControl(TenantContext $c, string $jobId, ?int $v, string $ref): ProductionJob
    {
        return $this->applyCommand($c, $jobId, self::CMD_SEND_TO_QC, $v, $ref);
    }

    public function abandon(TenantContext $c, string $jobId, ?int $v, string $ref, string $reasonCode): ProductionJob
    {
        return $this->applyCommand($c, $jobId, self::CMD_ABANDON, $v, $ref, ['reason_code' => $reasonCode]);
    }

    private function assertGuards(TenantContext $context, ProductionJob $job, string $command, array $payload): void
    {
        if ($command === self::CMD_BLOCK && empty($payload['reason_code'])) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Alasan (reason_code) wajib saat menahan pekerjaan produksi.',
                ['reason_code' => ['required']],
            );
        }

        if ($command === self::CMD_ABANDON) {
            if (empty($payload['reason_code'])) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Alasan (reason_code) wajib saat membatalkan pekerjaan produksi.',
                    ['reason_code' => ['required']],
                );
            }

            // P-11: a job is abandoned only because its order was cancelled.
            $order = Order::query()->forTenant($context->tenantId())->whereKey($job->order_id)->first();
            if ($order === null || $order->status !== Order::STATUS_CANCELLED) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Pekerjaan produksi hanya dibatalkan ketika pesanannya dibatalkan.',
                    ['order' => ['not_cancelled']],
                );
            }
        }
    }

    private function recordEvent(TenantContext $context, ProductionJob $job, string $type, array $payload, string $clientReference): void
    {
        ProductionEvent::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId(),
            'job_id' => $job->id,
            'type' => $type,
            'actor_membership_id' => $context->membershipId(),
            'payload' => $payload,
            'client_reference' => $clientReference,
            'occurred_at' => now(),
        ]);
    }

    private function payloadMatches(array $stored, array $incoming): bool
    {
        return json_encode($this->normalise($stored)) === json_encode($this->normalise($incoming));
    }

    /** @param array<mixed> $a @return array<mixed> */
    private function normalise(array $a): array
    {
        ksort($a);

        return $a;
    }

    private function serviceTypeFor(string $unit): string
    {
        return $unit === 'kilogram' ? ProductionItem::SERVICE_KILOAN : ProductionItem::SERVICE_SATUAN;
    }

    private function assertOrderInTenant(TenantContext $context, Order $order): void
    {
        if ($order->tenant_id !== $context->tenantId()) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
