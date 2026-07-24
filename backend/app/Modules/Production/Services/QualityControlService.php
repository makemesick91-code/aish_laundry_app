<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Models\ReworkCycle;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT C. Quality control and rework.
 *
 * A verdict is recorded against a job that is AWAITING_QC and DRIVES the
 * policy transitions (PRODUCTION_STATE_MACHINE.md P-07 / P-10):
 *   PASSED / WAIVED_WITH_AUTHORIZATION -> CLOSED
 *   FAILED_REWORK_REQUIRED             -> REWORK_IN_PROGRESS (+ a rework cycle)
 *
 * A recorded verdict is APPEND-ONLY (the table refuses UPDATE at the engine): a
 * FAIL is never rewritten into a PASS; a re-inspection is a new row. Rework
 * completion returns the job to AWAITING_QC (P-08). The order's
 * READY_FOR_PICKUP transition on a passing verdict is Unit D — this service
 * closes the production job and records the fact; it does not write order money.
 */
class QualityControlService
{
    /** Record a QC verdict and apply the resulting production transition. */
    public function recordInspection(
        TenantContext $context,
        string $jobId,
        string $verdict,
        ?int $expectedVersion,
        string $clientReference,
        ?string $defectReasonCode = null,
        ?string $defectReason = null,
        ?string $evidencePath = null,
    ): QualityControlInspection {
        $this->assertVerdict($verdict);

        return DB::transaction(function () use ($context, $jobId, $verdict, $expectedVersion, $clientReference, $defectReasonCode, $defectReason, $evidencePath) {
            $job = $this->lockJob($context, $jobId);

            // Idempotency: a prior QC event with this reference means the verdict
            // already applied — return the ORIGINAL inspection, no second effect.
            $prior = ProductionEvent::query()
                ->forTenant($context->tenantId())
                ->where('client_reference', $clientReference)
                ->first();
            if ($prior !== null) {
                if ($prior->type !== 'QualityControlRecorded') {
                    throw ApiException::of(
                        ErrorCode::CONFLICT,
                        'client_reference telah dipakai untuk perintah yang berbeda.',
                        ['client_reference' => ['reused_different_payload']],
                    );
                }

                return QualityControlInspection::query()
                    ->forTenant($context->tenantId())
                    ->findOrFail($prior->payload['inspection_id']);
            }

            if ($job->state !== ProductionJob::STATE_AWAITING_QC) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Inspeksi QC hanya untuk pekerjaan yang menunggu QC.',
                    ['state' => ['not_awaiting_qc']],
                );
            }

            $this->assertVersion($job, $expectedVersion);

            if ($verdict === QualityControlInspection::VERDICT_FAILED && ($defectReasonCode === null || $defectReasonCode === '')) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Kode alasan cacat wajib untuk verdict gagal.',
                    ['defect_reason_code' => ['required']],
                );
            }

            $inspection = QualityControlInspection::create([
                'tenant_id' => $context->tenantId(),
                'job_id' => $job->id,
                'verdict' => $verdict,
                'defect_reason_code' => $defectReasonCode,
                'defect_reason' => $defectReason,
                'inspector_membership_id' => $context->membershipId(),
                'evidence_path' => $evidencePath,
                'inspected_at' => now(),
            ]);

            if ($verdict === QualityControlInspection::VERDICT_FAILED) {
                $job->state = ProductionJob::STATE_REWORK_IN_PROGRESS;
                $this->openReworkCycle($context, $job, $inspection, $defectReasonCode, $defectReason);
            } else {
                // PASSED or WAIVED_WITH_AUTHORIZATION -> the job is done. The
                // order READY_FOR_PICKUP transition + first-ready anchor is Unit D.
                $job->state = ProductionJob::STATE_CLOSED;
            }

            $job->updated_by_membership_id = $context->membershipId();
            $job->save();

            $this->recordEvent($context, $job, 'QualityControlRecorded', [
                'verdict' => $verdict,
                'inspection_id' => $inspection->id,
            ], $clientReference);

            return $inspection;
        });
    }

    /** Complete the open rework cycle and return the job to AWAITING_QC (P-08). */
    public function completeRework(
        TenantContext $context,
        string $jobId,
        ?int $expectedVersion,
        string $clientReference,
        string $reasonCode,
    ): ProductionJob {
        return DB::transaction(function () use ($context, $jobId, $expectedVersion, $clientReference, $reasonCode) {
            $job = $this->lockJob($context, $jobId);

            $replay = $this->replayGuard($context, $clientReference, 'ReworkCompleted');
            if ($replay !== null) {
                return $replay;
            }

            if ($job->state !== ProductionJob::STATE_REWORK_IN_PROGRESS) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Penyelesaian rework hanya saat pekerjaan sedang dikerjakan ulang.',
                    ['state' => ['not_in_rework']],
                );
            }

            $this->assertVersion($job, $expectedVersion);

            if ($reasonCode === '') {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Kode alasan wajib saat menyelesaikan rework.',
                    ['reason_code' => ['required']],
                );
            }

            $cycle = ReworkCycle::query()
                ->forTenant($context->tenantId())
                ->where('job_id', $job->id)
                ->whereNull('completed_at')
                ->orderByDesc('cycle_no')
                ->first();

            if ($cycle !== null) {
                $cycle->completed_at = now();
                $cycle->save();
            }

            $job->state = ProductionJob::STATE_AWAITING_QC;
            $job->updated_by_membership_id = $context->membershipId();
            $job->save();

            $this->recordEvent($context, $job, 'ReworkCompleted', ['reason_code' => $reasonCode], $clientReference);

            return $job;
        });
    }

    private function openReworkCycle(TenantContext $context, ProductionJob $job, QualityControlInspection $inspection, ?string $reasonCode, ?string $reason): void
    {
        $next = (int) (ReworkCycle::query()
            ->forTenant($context->tenantId())
            ->where('job_id', $job->id)
            ->max('cycle_no') ?? 0) + 1;

        ReworkCycle::create([
            'tenant_id' => $context->tenantId(),
            'job_id' => $job->id,
            'source_inspection_id' => $inspection->id,
            'cycle_no' => $next,
            'reason_code' => $reasonCode ?? 'REWORK',
            'reason' => $reason,
            'started_by_membership_id' => $context->membershipId(),
            'started_at' => now(),
        ]);
    }

    private function lockJob(TenantContext $context, string $jobId): ProductionJob
    {
        $job = ProductionJob::query()
            ->forTenant($context->tenantId())
            ->where('id', $jobId)
            ->lockForUpdate()
            ->first();

        if ($job === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $job;
    }

    private function replayGuard(TenantContext $context, string $clientReference, string $type): ?ProductionJob
    {
        $prior = ProductionEvent::query()
            ->forTenant($context->tenantId())
            ->where('client_reference', $clientReference)
            ->first();

        if ($prior === null) {
            return null;
        }

        if ($prior->type !== $type) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'client_reference telah dipakai untuk perintah yang berbeda.',
                ['client_reference' => ['reused_different_payload']],
            );
        }

        return ProductionJob::query()->forTenant($context->tenantId())->find($prior->job_id);
    }

    private function assertVersion(ProductionJob $job, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $job->version !== $expectedVersion) {
            throw ApiException::of(
                ErrorCode::CONFLICT,
                'Versi produksi sudah berubah; muat ulang keadaan terbaru.',
                ['version' => ['version_conflict']],
            );
        }
    }

    private function assertVerdict(string $verdict): void
    {
        $valid = [
            QualityControlInspection::VERDICT_PASSED,
            QualityControlInspection::VERDICT_FAILED,
            QualityControlInspection::VERDICT_WAIVED,
        ];
        if (! in_array($verdict, $valid, true)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Verdict QC tidak dikenal.',
                ['verdict' => ['invalid']],
            );
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
}
