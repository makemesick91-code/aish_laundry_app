<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Modules\Organization\Http\OutletProjection;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Organization\Models\OutletPrinter;
use App\Modules\Organization\Models\OutletServiceZone;
use App\Modules\Organization\Models\OutletShift;
use App\Modules\Organization\Services\OutletMasterDataRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * OUTLET MASTER DATA — hours, capacity, quiet hours, zones, shifts, printers,
 * and the tenant proof policy (FR-041 … FR-047).
 *
 * EVERY LOOKUP IS TENANT-SCOPED BEFORE IT IS ANYTHING ELSE, exactly as
 * CustomerController does it. A foreign id and an absent id produce the SAME
 * 404, with the same body, so a caller cannot use this surface to discover that
 * another tenant holds a record (Rule 48 hard rule 5, threat T-06).
 *
 * `outlet_id` AND `tenant_id` ARE NEVER READ FROM A REQUEST BODY. The outlet
 * comes from the tenant-scoped path lookup and the tenant from the verified
 * TenantContext. A satellite therefore cannot be aimed at another tenant's
 * outlet by any payload (threat T-05, Rule 39 hard rule 1).
 *
 * SORT FIELDS ARE ALLOW-LISTED ENUMS and pagination is hard-bounded (T-17, T-03).
 *
 * THERE IS NO BULK MUTATION, NO EXPORT, AND NO HARD DELETE on this surface.
 * Their absence is asserted by a route test rather than assumed (T-18, T-19,
 * T-20).
 *
 * WHAT THIS CONTROLLER DOES NOT DO, DELIBERATELY: it does not defer a message
 * during quiet hours (Step 7), close a shift or reconcile its cash (Step 5),
 * route or sequence anything within a zone (Step 8), or print a document
 * (Step 5, FR-052). It configures. Nothing here acts.
 */
final class OutletMasterDataController
{
    /**
     * Sortable columns, PER COLLECTION.
     *
     * This was one shared list, and that was the defect (SEC-09). `display_order`
     * is a real column on zones and shifts and does NOT exist on
     * `outlet_printers`, so `GET .../printers?sort=display_order` passed the
     * allow-list — the value was, after all, on the list — and then reached
     * `orderBy('display_order')` against a table with no such column. PostgreSQL
     * refused it, the refusal surfaced as HTTP 500, and an allow-list that
     * cannot prevent a 500 is not doing the job it was written for. An unhandled
     * database error is also the wrong shape to hand a client: it carries
     * driver-level text, and with debug rendering on it would carry the SQL.
     *
     * An allow-list is only meaningful against ONE schema. Sharing it across
     * three tables silently asserted that the three have the same columns, and
     * nothing checked that assertion. `test_every_sortable_column_exists_on_its
     * _table` now checks it against the live schema for every collection here,
     * so adding a fourth collection or dropping a column breaks a test rather
     * than a request.
     *
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'zones' => ['code', 'name', 'display_order', 'created_at', 'updated_at'],
        'shifts' => ['code', 'name', 'display_order', 'created_at', 'updated_at'],
        'printers' => ['code', 'name', 'created_at', 'updated_at'],
    ];

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly OutletMasterDataRegistry $registry)
    {
    }

    // ------------------------------------------------------------------
    // The outlet itself
    // ------------------------------------------------------------------

    public function show(string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('viewMasterData', $model);

        return ApiResponse::success(['outlet' => OutletProjection::detail($model)]);
    }

    public function update(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $model);
        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],

            // FR-041 — a real IANA identifier, not a free-text label. `timezone`
            // validates against the tz database, so "WIB" and "GMT+7" are
            // refused: neither can resolve a wall-clock time.
            'timezone' => ['sometimes', 'string', 'timezone'],

            'address_line' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],

            // FR-042.
            'daily_capacity_kg' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'daily_capacity_orders' => ['sometimes', 'nullable', 'integer', 'min:0'],

            // FR-047. 24-hour local wall clock.
            'quiet_hours_start' => ['sometimes', 'string', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'string', 'date_format:H:i'],

            'is_active' => ['sometimes', 'boolean'],

            // FR-041. Structure is validated by OperatingHours, which produces a
            // message naming the offending DAY — a generic "invalid json" would
            // leave an operator guessing which of seven entries was wrong.
            'operating_hours' => ['sometimes', 'nullable', 'array'],
        ]);

        $updated = $this->registry->updateOutlet($context, $model, $validated);

        return ApiResponse::success(['outlet' => OutletProjection::detail($updated)]);
    }

    // ------------------------------------------------------------------
    // Service zones (FR-043) — coverage definition only.
    // ------------------------------------------------------------------

    public function zones(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('viewMasterData', $model);

        return $this->paginated(
            $request,
            OutletServiceZone::query()->forTenant($context->tenantId())->where('outlet_id', $model->id),
            'zones',
            static fn (OutletServiceZone $z): array => OutletProjection::zone($z),
            'display_order'
        );
    }

    public function storeZone(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $model);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'postal_codes' => ['nullable', 'array', 'max:200'],
            'postal_codes.*' => ['string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $zone = $this->registry->createZone($context, $model, $validated);

        return ApiResponse::success(['zone' => OutletProjection::zone($zone)], 201);
    }

    public function updateZone(Request $request, string $outlet, string $zone): JsonResponse
    {
        $context = app(TenantContext::class);
        $outletModel = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $outletModel);

        $model = $this->findSatellite(
            OutletServiceZone::query()->forTenant($context->tenantId())->where('outlet_id', $outletModel->id),
            $zone
        );

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'postal_codes' => ['sometimes', 'nullable', 'array', 'max:200'],
            'postal_codes.*' => ['string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $updated = $this->registry->updateZone($context, $model, $validated);

        return ApiResponse::success(['zone' => OutletProjection::zone($updated)]);
    }

    // ------------------------------------------------------------------
    // Shifts (FR-044) — definitions only. Shift closing is Step 5.
    // ------------------------------------------------------------------

    public function shifts(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('viewMasterData', $model);

        return $this->paginated(
            $request,
            OutletShift::query()->forTenant($context->tenantId())->where('outlet_id', $model->id),
            'shifts',
            static fn (OutletShift $s): array => OutletProjection::shift($s),
            'display_order'
        );
    }

    public function storeShift(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $model);

        $validated = $request->validate($this->shiftRules(required: true));

        $shift = $this->registry->createShift($context, $model, $validated);

        return ApiResponse::success(['shift' => OutletProjection::shift($shift)], 201);
    }

    public function updateShift(Request $request, string $outlet, string $shift): JsonResponse
    {
        $context = app(TenantContext::class);
        $outletModel = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $outletModel);

        $model = $this->findSatellite(
            OutletShift::query()->forTenant($context->tenantId())->where('outlet_id', $outletModel->id),
            $shift
        );

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate($this->shiftRules(required: false));

        // A partial update must still leave a coherent pair, because
        // `crosses_midnight` is derived from BOTH times. Filling the missing one
        // from the stored row keeps the derivation honest.
        $validated['starts_at'] ??= $model->starts_at;
        $validated['ends_at'] ??= $model->ends_at;

        $updated = $this->registry->updateShift($context, $model, $validated);

        return ApiResponse::success(['shift' => OutletProjection::shift($updated)]);
    }

    // ------------------------------------------------------------------
    // Printers (FR-045) — a device, not a document (DEC-0030).
    // ------------------------------------------------------------------

    public function printers(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('viewMasterData', $model);

        return $this->paginated(
            $request,
            OutletPrinter::query()->forTenant($context->tenantId())->where('outlet_id', $model->id),
            'printers',
            static fn (OutletPrinter $p): array => OutletProjection::printer($p),
            'code'
        );
    }

    public function storePrinter(Request $request, string $outlet): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $model);

        $validated = $request->validate($this->printerRules(required: true));

        $printer = $this->registry->createPrinter($context, $model, $validated);

        return ApiResponse::success(['printer' => OutletProjection::printer($printer)], 201);
    }

    public function updatePrinter(Request $request, string $outlet, string $printer): JsonResponse
    {
        $context = app(TenantContext::class);
        $outletModel = $this->registry->resolveOutlet($context, $outlet);

        Gate::authorize('manageMasterData', $outletModel);

        $model = $this->findSatellite(
            OutletPrinter::query()->forTenant($context->tenantId())->where('outlet_id', $outletModel->id),
            $printer
        );

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate($this->printerRules(required: false));

        $updated = $this->registry->updatePrinter($context, $model, $validated);

        return ApiResponse::success(['printer' => OutletProjection::printer($updated)]);
    }

    // ------------------------------------------------------------------
    // Tenant proof policy (FR-046) — configuration only. Capture is Step 8.
    // ------------------------------------------------------------------

    public function proofPolicy(): JsonResponse
    {
        Gate::authorize('viewAny', Outlet::class);

        $policy = $this->registry->proofPolicy(app(TenantContext::class));

        return ApiResponse::success(['proof_policy' => OutletProjection::proofPolicy($policy)]);
    }

    public function updateProofPolicy(Request $request): JsonResponse
    {
        Gate::authorize('manageProofPolicy', Outlet::class);

        $context = app(TenantContext::class);

        OptimisticConcurrency::assertFresh($request, $this->registry->proofPolicy($context));

        $rules = [];

        foreach (['pickup', 'delivery'] as $leg) {
            foreach (['photo', 'signature', 'recipient_name', 'otp'] as $proof) {
                $rules["{$leg}_requires_{$proof}"] = ['sometimes', 'boolean'];
            }
        }

        $updated = $this->registry->updateProofPolicy($context, $request->validate($rules));

        return ApiResponse::success(['proof_policy' => OutletProjection::proofPolicy($updated)]);
    }

    // ------------------------------------------------------------------
    // Shared plumbing
    // ------------------------------------------------------------------

    /** @return array<string, list<mixed>> */
    private function shiftRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'code' => [$presence, 'string', 'max:32'],
            'name' => [$presence, 'string', 'max:255'],
            'starts_at' => [$presence, 'string', 'date_format:H:i'],
            'ends_at' => [$presence, 'string', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function printerRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'code' => [$presence, 'string', 'max:32'],
            'name' => [$presence, 'string', 'max:255'],

            // Enumerated, mirroring the database check constraints. A free-text
            // device kind would be unusable by the Step 5 printing path.
            'device_kind' => [$presence, Rule::in(OutletPrinter::deviceKinds())],
            'connection_kind' => [$presence, Rule::in(OutletPrinter::connectionKinds())],

            'device_identifier' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Resolve a satellite from an ALREADY tenant-and-outlet-scoped query.
     *
     * The caller narrows the builder first, so this method cannot be handed an
     * unscoped one by accident. A miss is a plain 404 that says nothing about
     * why.
     *
     * The template annotation carries the concrete model type through, so a
     * caller that narrowed to `OutletShift` gets an `OutletShift` back and static
     * analysis catches a mismatched registry call rather than deferring it to a
     * runtime TypeError.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scoped
     * @return TModel
     */
    private function findSatellite(Builder $scoped, string $id): Model
    {
        $model = $scoped->whereKey($id)->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }

    /**
     * Bounded, allow-listed listing.
     *
     * @param  callable(Model): array<string, mixed>  $project
     */
    private function paginated(
        Request $request,
        Builder $scoped,
        string $key,
        callable $project,
        string $defaultSort,
    ): JsonResponse {
        // Keyed by the collection, because what is sortable depends on which
        // table is being read (SEC-09).
        $sortable = self::SORTABLE[$key];

        $sort = $request->query('sort', $defaultSort);

        if (! in_array($sort, $sortable, true)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Kolom pengurutan tidak dikenal.',
                // The permitted set for THIS collection. Previously this told a
                // client that `display_order` was accepted on printers, which
                // was how the 500 got requested in the first place.
                ['sort' => $sortable]
            );
        }

        if (($active = $request->query('is_active')) !== null) {
            if (! in_array($active, ['true', 'false', '1', '0'], true)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Nilai filter is_active tidak dikenal.',
                    ['is_active' => ['true', 'false']]
                );
            }

            $scoped->where('is_active', in_array($active, ['true', '1'], true));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), self::MAX_PER_PAGE);

        $page = $scoped->orderBy($sort)->paginate($perPage);

        return ApiResponse::success([
            $key => array_map($project, $page->items()),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
