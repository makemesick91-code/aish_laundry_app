<?php

declare(strict_types=1);

namespace App\Modules\Production\Services;

use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlEvidence;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Storage\PrivateObjectStorage;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 6 · FR-083 — QC defect-photo evidence. Attaches a photograph to a FAILED
 * quality-control inspection, stored PRIVATELY under a random key and read only
 * through a short-lived signed URL.
 *
 * INVARIANTS (Rule 18):
 *   - failed-QC precondition: evidence attaches only to a FAILED_REWORK_REQUIRED
 *     inspection (a defect photo has no meaning on a pass/waiver);
 *   - tenant/outlet isolation: the inspection and job are re-resolved within the
 *     caller's tenant; a foreign one is indistinguishable from absent (Rule 48);
 *   - content trust: the stored content_type is the SERVER-DETECTED type, never
 *     the client's declared one (Rule 03 hard rule 12);
 *   - idempotency (Rule 07/20): a replay with the same client_reference returns
 *     the original row and stores no second object;
 *   - append-only: the evidence row is never updated or deleted (DB triggers).
 */
final class QualityControlEvidenceService
{
    /** Short-lived signed-URL lifetime. */
    public const URL_TTL_SECONDS = 300;

    public function __construct(
        private readonly PrivateObjectStorage $storage,
        private readonly ImageEvidenceValidator $validator,
    ) {
    }

    public function attach(
        TenantContext $context,
        string $jobId,
        string $inspectionId,
        string $bytes,
        string $clientReference,
    ): QualityControlEvidence {
        return DB::transaction(function () use ($context, $jobId, $inspectionId, $bytes, $clientReference) {
            // Idempotency FIRST: a replay of the same upload returns the original
            // row and never stores a second object.
            $prior = QualityControlEvidence::query()->forTenant($context->tenantId())
                ->where('client_reference', $clientReference)->first();
            if ($prior !== null) {
                return $prior;
            }

            $inspection = QualityControlInspection::query()->forTenant($context->tenantId())
                ->where('id', $inspectionId)->where('job_id', $jobId)->first();
            // Absent or cross-tenant/foreign-job are indistinguishable (Rule 48).
            if ($inspection === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            // A defect photo attaches only to a FAILED verdict.
            if ($inspection->verdict !== QualityControlInspection::VERDICT_FAILED) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Bukti cacat hanya dapat dilampirkan pada inspeksi yang gagal.',
                    ['inspection' => ['not_failed']],
                );
            }

            $job = ProductionJob::query()->forTenant($context->tenantId())->whereKey($jobId)->first();
            if ($job === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
            }

            $meta = $this->validator->validate($bytes);
            $checksum = hash('sha256', $bytes);
            $key = $this->storage->randomKey($meta['extension']);

            // Store the bytes privately BEFORE recording the row, so a stored row
            // always has a backing object.
            $this->storage->put($key, $bytes, $meta['content_type']);

            return QualityControlEvidence::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $job->outlet_id,
                'job_id' => $jobId,
                'inspection_id' => $inspectionId,
                'uploaded_by_membership_id' => $context->membershipId(),
                'content_type' => $meta['content_type'],
                'byte_size' => strlen($bytes),
                'checksum_sha256' => $checksum,
                'storage_key' => $key,
                'status' => QualityControlEvidence::STATUS_STORED,
                'client_reference' => $clientReference,
                'occurred_at' => now(),
            ]);
        });
    }

    /** A short-lived signed URL for one evidence object. The bytes never pass through the app. */
    public function signedUrl(
        TenantContext $context,
        string $jobId,
        string $inspectionId,
        string $evidenceId,
    ): string {
        $evidence = $this->find($context, $jobId, $inspectionId, $evidenceId);
        $extension = match ($evidence->content_type) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return $this->storage->temporaryUrl(
            $evidence->storage_key,
            self::URL_TTL_SECONDS,
            "bukti-{$evidence->id}.{$extension}",
        );
    }

    /** @return Collection<int, QualityControlEvidence> */
    public function list(TenantContext $context, string $jobId, string $inspectionId): Collection
    {
        return QualityControlEvidence::query()->forTenant($context->tenantId())
            ->where('job_id', $jobId)->where('inspection_id', $inspectionId)
            ->orderBy('occurred_at')->get();
    }

    private function find(
        TenantContext $context,
        string $jobId,
        string $inspectionId,
        string $evidenceId,
    ): QualityControlEvidence {
        $evidence = QualityControlEvidence::query()->forTenant($context->tenantId())
            ->where('id', $evidenceId)
            ->where('job_id', $jobId)
            ->where('inspection_id', $inspectionId)
            ->first();
        if ($evidence === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $evidence;
    }
}
