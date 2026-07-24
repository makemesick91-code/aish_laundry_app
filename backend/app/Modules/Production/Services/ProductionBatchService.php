<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionBatchEvent;
use App\Modules\Production\Models\ProductionBatchItem;
use App\Modules\Production\Models\ProductionItem;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 6 — PRODUCTION OPERATIONS · FR-074 batch operations (residual closure).
 *
 * The single server-side authority for production batches (Rule 40). A client
 * sends a COMMAND (create, update, add item, remove item, close); the server
 * enforces every batch invariant and applies the change atomically. It fails
 * CLOSED: a cross-tenant target, a stale version, a closed batch, an ineligible
 * item, or a duplicate membership changes nothing.
 *
 * INVARIANTS (Rule 18):
 *   - tenant-safe & outlet-safe membership: an item joins a batch only when both
 *     belong to the SAME tenant (composite FK) AND the same outlet (guard below);
 *   - stage compatibility: an item's current stage must match the batch stage;
 *   - eligibility: only an item of a non-terminal job may be added;
 *   - no duplicate active membership: the unique (batch_id, item_id) index refuses it;
 *   - a CLOSED batch is immutable (guarded here AND by DB triggers, Rule 18);
 *   - idempotency (Rule 07/20): every command carries a client_reference; a replay
 *     with the same reference and payload returns the original effect and writes
 *     nothing new, a replay with the same reference and a DIFFERENT payload fails
 *     closed;
 *   - append-only history: removing an item deletes its CURRENT-membership row but
 *     the fact is preserved forever in production_batch_events (FR-074).
 *
 * A batch records no money and never touches an order's price snapshot (Rule 04,
 * FR-036).
 */
class ProductionBatchService
{
    /** Create an OPEN batch for the active outlet. Idempotent on client_reference. */
    public function createBatch(TenantContext $context, string $code, string $stage, string $clientReference): ProductionBatch
    {
        $outletId = $context->outletId();
        if ($outletId === null) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Pilih outlet aktif sebelum membuat batch produksi.',
                ['outlet' => ['required']],
            );
        }

        return DB::transaction(function () use ($context, $outletId, $code, $stage, $clientReference) {
            $payload = ['code' => $code, 'stage' => $stage];
            $prior = $this->replayGuard($context->tenantId(), $clientReference, ProductionBatchEvent::TYPE_CREATED, $payload);
            if ($prior !== null) {
                // Exactly-once replay: the batch already exists.
                return ProductionBatch::query()->forTenant($context->tenantId())->whereKey($prior->batch_id)->firstOrFail();
            }

            // Code is unique per tenant (DB constraint); pre-check for a friendly conflict.
            $duplicate = ProductionBatch::query()->forTenant($context->tenantId())->where('code', $code)->exists();
            if ($duplicate) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Kode batch sudah dipakai pada tenant ini.',
                    ['code' => ['already_exists']],
                );
            }

            $batch = new ProductionBatch([
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $outletId,
                'code' => $code,
                'stage' => $stage,
                'status' => ProductionBatch::STATUS_OPEN,
                'created_by_membership_id' => $context->membershipId(),
            ]);
            $batch->save();

            $this->recordEvent($context, $batch, ProductionBatchEvent::TYPE_CREATED, $payload, $clientReference);

            return $batch;
        });
    }

    /**
     * Update an OPEN batch's metadata (PATCH). `code` may be renamed while open;
     * `stage` may change only while the batch has NO members (else it would break
     * stage compatibility). Optimistic-concurrency checked; bumps the version.
     */
    public function updateBatch(
        TenantContext $context,
        string $batchId,
        ?string $code,
        ?string $stage,
        ?int $expectedVersion,
        string $clientReference,
    ): ProductionBatch {
        return DB::transaction(function () use ($context, $batchId, $code, $stage, $expectedVersion, $clientReference) {
            $batch = $this->lockBatch($context, $batchId);

            $payload = array_filter(
                ['code' => $code, 'stage' => $stage],
                static fn ($v): bool => $v !== null,
            );
            $prior = $this->replayGuard($context->tenantId(), $clientReference, ProductionBatchEvent::TYPE_UPDATED, $payload);
            if ($prior !== null) {
                return $batch;
            }

            $this->assertMutable($batch, $expectedVersion);

            if ($code !== null && $code !== $batch->code) {
                $duplicate = ProductionBatch::query()->forTenant($context->tenantId())
                    ->where('code', $code)->where('id', '!=', $batch->id)->exists();
                if ($duplicate) {
                    throw ApiException::of(
                        ErrorCode::CONFLICT,
                        'Kode batch sudah dipakai pada tenant ini.',
                        ['code' => ['already_exists']],
                    );
                }
                $batch->code = $code;
            }

            if ($stage !== null && $stage !== $batch->stage) {
                $hasMembers = ProductionBatchItem::query()->forTenant($context->tenantId())
                    ->where('batch_id', $batch->id)->exists();
                if ($hasMembers) {
                    throw ApiException::of(
                        ErrorCode::CONFLICT,
                        'Tahap batch tidak dapat diubah selama masih ada item di dalamnya.',
                        ['stage' => ['batch_not_empty']],
                    );
                }
                $batch->stage = $stage;
            }

            $batch->save(); // bumps version
            $this->recordEvent($context, $batch, ProductionBatchEvent::TYPE_UPDATED, $payload, $clientReference);

            return $batch->refresh();
        });
    }

    /**
     * Add an eligible production item to an OPEN batch. Enforces same-outlet,
     * stage compatibility, non-terminal job, and no duplicate membership.
     * expected_version is checked (batch view must be current) but NOT bumped —
     * adding a member does not change the batch's own metadata, so two operators
     * adding DIFFERENT items concurrently both succeed; a duplicate is refused.
     */
    public function addItem(
        TenantContext $context,
        string $batchId,
        string $productionItemId,
        ?int $expectedVersion,
        string $clientReference,
    ): ProductionBatch {
        return DB::transaction(function () use ($context, $batchId, $productionItemId, $expectedVersion, $clientReference) {
            $batch = $this->lockBatch($context, $batchId);

            $payload = ['production_item_id' => $productionItemId];
            $prior = $this->replayGuard($context->tenantId(), $clientReference, ProductionBatchEvent::TYPE_ITEM_ADDED, $payload);
            if ($prior !== null) {
                return $batch;
            }

            $this->assertMutable($batch, $expectedVersion);

            $item = ProductionItem::query()->forTenant($context->tenantId())->whereKey($productionItemId)->first();
            // Absent or cross-tenant item are indistinguishable (Rule 48).
            if ($item === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            $job = ProductionJob::query()->forTenant($context->tenantId())->whereKey($item->job_id)->first();
            if ($job === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            // Same-outlet: a batch groups items of ITS outlet only (T6-02).
            if ($job->outlet_id !== $batch->outlet_id) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Item berasal dari outlet berbeda dengan batch.',
                    ['production_item_id' => ['different_outlet']],
                );
            }

            // Stage compatibility: the item must be at the batch's stage.
            if ($item->stage !== $batch->stage) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Tahap item tidak sesuai dengan tahap batch.',
                    ['production_item_id' => ['stage_mismatch']],
                );
            }

            // Eligibility: only an item of a non-terminal job may be batched.
            if (in_array($job->state, ProductionJob::TERMINAL_STATES, true)) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Item bukan bagian dari pekerjaan produksi yang aktif.',
                    ['production_item_id' => ['not_eligible']],
                );
            }

            // No duplicate membership. The unique (batch_id, item_id) index is the
            // authority; this pre-check turns the DB error into a clean 409.
            $already = ProductionBatchItem::query()->forTenant($context->tenantId())
                ->where('batch_id', $batch->id)->where('production_item_id', $item->id)->exists();
            if ($already) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Item sudah menjadi anggota batch ini.',
                    ['production_item_id' => ['already_member']],
                );
            }

            ProductionBatchItem::create([
                'tenant_id' => $context->tenantId(),
                'batch_id' => $batch->id,
                'production_item_id' => $item->id,
            ]);

            $this->recordEvent($context, $batch, ProductionBatchEvent::TYPE_ITEM_ADDED, $payload, $clientReference);

            return $batch;
        });
    }

    /**
     * Remove a member from an OPEN batch. Deletes the CURRENT-membership row; the
     * fact is preserved in the append-only timeline (FR-074). A CLOSED batch is
     * immutable, so removal is refused there.
     */
    public function removeItem(
        TenantContext $context,
        string $batchId,
        string $productionItemId,
        ?int $expectedVersion,
        string $clientReference,
    ): ProductionBatch {
        return DB::transaction(function () use ($context, $batchId, $productionItemId, $expectedVersion, $clientReference) {
            $batch = $this->lockBatch($context, $batchId);

            $payload = ['production_item_id' => $productionItemId];
            $prior = $this->replayGuard($context->tenantId(), $clientReference, ProductionBatchEvent::TYPE_ITEM_REMOVED, $payload);
            if ($prior !== null) {
                return $batch;
            }

            $this->assertMutable($batch, $expectedVersion);

            $membership = ProductionBatchItem::query()->forTenant($context->tenantId())
                ->where('batch_id', $batch->id)->where('production_item_id', $productionItemId)->first();
            if ($membership === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            $membership->delete();

            $this->recordEvent($context, $batch, ProductionBatchEvent::TYPE_ITEM_REMOVED, $payload, $clientReference);

            return $batch;
        });
    }

    /** Close an OPEN batch. Optimistic-concurrency checked; a CLOSED batch is immutable thereafter. */
    public function closeBatch(TenantContext $context, string $batchId, ?int $expectedVersion, string $clientReference): ProductionBatch
    {
        return DB::transaction(function () use ($context, $batchId, $expectedVersion, $clientReference) {
            $batch = $this->lockBatch($context, $batchId);

            $prior = $this->replayGuard($context->tenantId(), $clientReference, ProductionBatchEvent::TYPE_CLOSED, []);
            if ($prior !== null) {
                return $batch;
            }

            $this->assertMutable($batch, $expectedVersion);

            $batch->status = ProductionBatch::STATUS_CLOSED;
            $batch->closed_at = now();
            $batch->save(); // bumps version; OLD.status='open' so the DB trigger allows this UPDATE

            $this->recordEvent($context, $batch, ProductionBatchEvent::TYPE_CLOSED, [], $clientReference);

            return $batch->refresh();
        });
    }

    /**
     * Lock the batch row and prove it belongs to the active tenant (404 == denied,
     * Rule 48). Every mutating command starts here. The idempotency replay check
     * runs NEXT — BEFORE the open/version check — so a replayed command returns the
     * original result even after the batch has since been closed (Rule 07/20, the
     * ordering ProductionRegistry uses).
     */
    private function lockBatch(TenantContext $context, string $batchId): ProductionBatch
    {
        $batch = ProductionBatch::query()
            ->forTenant($context->tenantId())
            ->where('id', $batchId)
            ->lockForUpdate()
            ->first();

        if ($batch === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $batch;
    }

    /** Require the batch to be OPEN and the optimistic version to be current. Fails closed. */
    private function assertMutable(ProductionBatch $batch, ?int $expectedVersion): void
    {
        if (! $batch->isOpen()) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Batch sudah ditutup dan tidak dapat diubah.',
                ['status' => ['batch_closed']],
            );
        }

        if ($expectedVersion !== null && (int) $batch->version !== $expectedVersion) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Versi batch sudah berubah; muat ulang keadaan terbaru.',
                ['version' => ['version_conflict']],
            );
        }
    }

    /**
     * Idempotency (Rule 07/20). A prior event with this reference means the
     * command already applied: same type + payload returns the original effect,
     * a different payload fails closed.
     */
    private function replayGuard(string $tenantId, string $clientReference, string $type, array $payload): ?ProductionBatchEvent
    {
        $prior = ProductionBatchEvent::query()->forTenant($tenantId)
            ->where('client_reference', $clientReference)->first();

        if ($prior === null) {
            return null;
        }

        if ($prior->type === $type && $this->payloadMatches($prior->payload ?? [], $payload)) {
            return $prior; // exactly-once: no second effect
        }

        throw ApiException::of(
            ErrorCode::CONFLICT,
            'client_reference telah dipakai untuk perintah yang berbeda.',
            ['client_reference' => ['reused_different_payload']],
        );
    }

    private function recordEvent(TenantContext $context, ProductionBatch $batch, string $type, array $payload, string $clientReference): void
    {
        ProductionBatchEvent::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $context->tenantId(),
            'batch_id' => $batch->id,
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
}
