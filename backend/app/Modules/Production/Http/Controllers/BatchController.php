<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers;

use App\Modules\Production\Http\BatchProjection;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Services\ProductionBatchService;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * STEP 6 — PRODUCTION OPERATIONS · FR-074 batch HTTP surface
 * (/api/v1/production/batches). Every action: tenant/outlet-authorized, RBAC-gated
 * via ProductionBatchPolicy (a foreign batch 404s exactly like an absent one —
 * Rule 48), idempotent on client_reference, optimistic-concurrency on
 * expected_version, no mass assignment (each field is explicitly validated),
 * minimal response exposure. The server owns every invariant; the client sends a
 * command.
 */
final class BatchController
{
    private const SORTABLE = ['updated_at', 'code', 'stage', 'status'];

    public function __construct(
        private readonly ProductionBatchService $batches,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductionBatch::class);
        $context = app(TenantContext::class);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:' . implode(',', ProductionBatch::STATUSES)],
            'stage' => ['sometimes', 'string', 'max:32'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', 'in:' . implode(',', self::SORTABLE)],
        ]);

        $query = ProductionBatch::query()->forTenant($context->tenantId());
        if ($context->outletId() !== null) {
            $query->where('outlet_id', $context->outletId());
        }
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (isset($validated['stage'])) {
            $query->where('stage', $validated['stage']);
        }

        $page = $query->orderBy($validated['sort'] ?? 'updated_at', 'desc')
            ->paginate($validated['per_page'] ?? 25);

        return ApiResponse::success(
            ['batches' => array_map(static fn (ProductionBatch $b) => BatchProjection::summary($b), $page->items())],
            200,
            ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        );
    }

    public function show(string $batch): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('view', $model);

        return ApiResponse::success([
            'batch' => BatchProjection::detail($model),
            'timeline' => BatchProjection::timeline($model),
        ]);
    }

    public function timeline(string $batch): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('view', $model);

        return ApiResponse::success(['timeline' => BatchProjection::timeline($model)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ProductionBatch::class);
        $context = app(TenantContext::class);

        $v = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'stage' => ['required', 'string', 'max:32'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->batches->createBatch($context, $v['code'], $v['stage'], $v['client_reference']);

        return ApiResponse::success(['batch' => BatchProjection::summary($result)], 201);
    }

    public function update(Request $request, string $batch): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('operate', $model);

        $v = $request->validate([
            'code' => ['sometimes', 'string', 'max:64'],
            'stage' => ['sometimes', 'string', 'max:32'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->batches->updateBatch(
            $context, $model->id,
            $v['code'] ?? null, $v['stage'] ?? null,
            $v['expected_version'] ?? null, $v['client_reference'],
        );

        return ApiResponse::success(['batch' => BatchProjection::summary($result)]);
    }

    public function close(Request $request, string $batch): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('operate', $model);

        $v = $request->validate([
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->batches->closeBatch($context, $model->id, $v['expected_version'] ?? null, $v['client_reference']);

        return ApiResponse::success(['batch' => BatchProjection::summary($result)]);
    }

    public function addItem(Request $request, string $batch): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('operate', $model);

        $v = $request->validate([
            'production_item_id' => ['required', 'uuid'],
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->batches->addItem($context, $model->id, $v['production_item_id'], $v['expected_version'] ?? null, $v['client_reference']);

        return ApiResponse::success(['batch' => BatchProjection::detail($result)], 201);
    }

    public function removeItem(Request $request, string $batch, string $item): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $batch);
        Gate::authorize('operate', $model);

        $v = $request->validate([
            'expected_version' => ['nullable', 'integer'],
            'client_reference' => ['required', 'uuid'],
        ]);

        $result = $this->batches->removeItem($context, $model->id, $item, $v['expected_version'] ?? null, $v['client_reference']);

        return ApiResponse::success(['batch' => BatchProjection::detail($result)]);
    }

    private function findOrFail(TenantContext $context, string $id): ProductionBatch
    {
        $model = ProductionBatch::query()->forTenant($context->tenantId())->whereKey($id)->first();
        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
