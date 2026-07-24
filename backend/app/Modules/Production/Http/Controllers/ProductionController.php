<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Production\Http\ProductionProjection;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Services\ProductionReadyService;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Production\Services\QualityControlService;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT E. The production HTTP surface
 * (/api/v1/production). Every action: tenant/outlet-authorized, RBAC-gated via
 * ProductionJobPolicy (a foreign job 404s exactly like an absent one — Rule 48),
 * idempotent on client_reference, optimistic-concurrency on expected_version, no
 * mass assignment (each field is explicitly validated), minimal response
 * exposure. The server owns the transition; the client sends a command.
 */
final class ProductionController
{
    private const SORTABLE = ['updated_at', 'state'];

    public function __construct(
        private readonly ProductionRegistry $registry,
        private readonly QualityControlService $qc,
        private readonly ProductionReadyService $ready,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionJob::class);
        $context = app(TenantContext::class);

        $validated = $request->validate([
            'state' => ['sometimes', 'string', 'in:' . implode(',', ProductionJob::STATES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:' . implode(',', self::SORTABLE)],
        ]);

        $query = ProductionJob::query()->forTenant($context->tenantId());
        if ($context->outletId() !== null) {
            $query->where('outlet_id', $context->outletId());
        }
        if (isset($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        $page = $query->orderBy($validated['sort'] ?? 'updated_at', 'desc')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            ['jobs' => array_map(static fn (ProductionJob $j) => ProductionProjection::summary($j), $page->items())],
            200,
            ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function show(string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('view', $model);

        return ApiResponse::success([
            'job' => ProductionProjection::detail($model),
            'timeline' => ProductionProjection::timeline($model),
        ]);
    }

    public function advance(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('operate', $model);
        $v = $request->validate([
            'stage' => ['required', 'string', 'max:32'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->registry->startStage($context, $model->id, $v['expected_version'] ?? null, $v['client_reference'], $v['stage']);

        return ApiResponse::success(['job' => ProductionProjection::summary($result)]);
    }

    public function block(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('operate', $model);
        $v = $request->validate([
            'reason_code' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->registry->block($context, $model->id, $v['expected_version'] ?? null, $v['client_reference'], $v['reason_code'], $v['reason'] ?? null);

        return ApiResponse::success(['job' => ProductionProjection::summary($result)]);
    }

    public function resume(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('operate', $model);
        $v = $request->validate([
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->registry->resume($context, $model->id, $v['expected_version'] ?? null, $v['client_reference']);

        return ApiResponse::success(['job' => ProductionProjection::summary($result)]);
    }

    public function sendToQualityControl(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('operate', $model);
        $v = $request->validate([
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->registry->sendToQualityControl($context, $model->id, $v['expected_version'] ?? null, $v['client_reference']);

        return ApiResponse::success(['job' => ProductionProjection::summary($result)]);
    }

    public function recordQualityControl(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('qualityControl', $model);
        $v = $request->validate([
            'verdict' => ['required', 'string', 'in:PASSED,FAILED_REWORK_REQUIRED,WAIVED_WITH_AUTHORIZATION'],
            'defect_reason_code' => ['nullable', 'string', 'max:64'],
            'defect_reason' => ['nullable', 'string', 'max:2000'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $inspection = $this->qc->recordInspection(
            $context, $model->id, $v['verdict'], $v['expected_version'] ?? null, $v['client_reference'],
            $v['defect_reason_code'] ?? null, $v['defect_reason'] ?? null,
        );

        return ApiResponse::success([
            'inspection' => ['id' => $inspection->id, 'verdict' => $inspection->verdict],
            'job' => ProductionProjection::summary($model->fresh()),
        ], 201);
    }

    public function completeRework(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('qualityControl', $model);
        $v = $request->validate([
            'reason_code' => ['required', 'string', 'max:64'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->qc->completeRework($context, $model->id, $v['expected_version'] ?? null, $v['client_reference'], $v['reason_code']);

        return ApiResponse::success(['job' => ProductionProjection::summary($result)]);
    }

    public function markReady(Request $request, string $job): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $job);
        Gate::authorize('operate', $model);
        $v = $request->validate([
            'client_reference' => ['required', 'uuid'],
        ]);

        $order = $this->ready->markReady($context, $model->id, $v['client_reference']);

        return ApiResponse::success(['order' => ['id' => $order->id, 'status' => $order->status]]);
    }

    private function findOrFail(TenantContext $context, string $id): ProductionJob
    {
        $model = ProductionJob::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
