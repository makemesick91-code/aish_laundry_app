<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlEvidence;
use App\Modules\Production\Services\ImageEvidenceValidator;
use App\Modules\Production\Services\QualityControlEvidenceService;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * STEP 6 · FR-083 — the QC defect-photo evidence HTTP surface. Upload is gated on
 * production.qc (the inspector role); reads and signed-URL retrieval on
 * production.view. A foreign job/inspection/evidence 404s exactly like an absent
 * one (Rule 48). The response never carries the bytes and never a permanent URL —
 * only metadata, and a SHORT-LIVED signed URL on explicit request.
 */
final class QualityControlEvidenceController
{
    public function __construct(
        private readonly QualityControlEvidenceService $evidence,
    ) {
    }

    public function store(Request $request, string $job, string $inspection): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findJob($context, $job);
        // Recording defect evidence is a QC act.
        Gate::authorize('qualityControl', $model);

        $request->validate([
            'photo' => ['required', 'file', 'max:' . (ImageEvidenceValidator::MAX_BYTES / 1024)],
            'client_reference' => ['required', 'uuid'],
        ]);

        $file = $request->file('photo');
        $bytes = $file?->get();
        if (! is_string($bytes) || $bytes === '') {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Berkas bukti tidak dapat dibaca.',
                ['photo' => ['unreadable']],
            );
        }

        $stored = $this->evidence->attach(
            $context, $model->id, $inspection, $bytes, (string) $request->input('client_reference'),
        );

        return ApiResponse::success(['evidence' => $this->projection($stored)], 201);
    }

    public function index(string $job, string $inspection): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findJob($context, $job);
        Gate::authorize('view', $model);

        $items = $this->evidence->list($context, $model->id, $inspection)
            ->map(fn (QualityControlEvidence $e) => $this->projection($e))->all();

        return ApiResponse::success(['evidence' => $items]);
    }

    public function url(string $job, string $inspection, string $evidence): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findJob($context, $job);
        Gate::authorize('view', $model);

        $url = $this->evidence->signedUrl($context, $model->id, $inspection, $evidence);

        return ApiResponse::success([
            'url' => $url,
            'expires_in' => QualityControlEvidenceService::URL_TTL_SECONDS,
        ]);
    }

    /** Minimal metadata exposure (Rule 32) — no bytes, no permanent URL, no storage key. */
    private function projection(QualityControlEvidence $e): array
    {
        return [
            'id' => $e->id,
            'inspection_id' => $e->inspection_id,
            'content_type' => $e->content_type,
            'byte_size' => (int) $e->byte_size,
            'checksum_sha256' => $e->checksum_sha256,
            'status' => $e->status,
            'uploaded_at' => optional($e->occurred_at)->toIso8601String(),
        ];
    }

    private function findJob(TenantContext $context, string $id): ProductionJob
    {
        $model = ProductionJob::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
