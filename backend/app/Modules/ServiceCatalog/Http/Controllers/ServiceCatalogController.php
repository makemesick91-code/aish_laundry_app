<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Http\Controllers;

use App\Modules\ServiceCatalog\Http\CatalogProjection;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\ServiceCatalog\Models\ServiceAddon;
use App\Modules\ServiceCatalog\Models\ServiceCategory;
use App\Modules\ServiceCatalog\Models\ServicePackage;
use App\Modules\ServiceCatalog\Services\ServiceCatalogRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * SERVICE CATALOGUE — categories, services, packages, and add-ons
 * (FR-031 … FR-033, FR-040).
 *
 * EVERY LOOKUP IS TENANT-SCOPED BEFORE IT IS ANYTHING ELSE. A foreign id and an
 * absent id produce the SAME 404, with the same body (Rule 48 hard rule 5,
 * threat T-06).
 *
 * SORT AND FILTER FIELDS ARE ALLOW-LISTED ENUMS, never raw column names, and
 * pagination is hard-bounded (threats T-17, T-03).
 *
 * THE CATALOGUE CARRIES NO PRICE. A service says WHAT is sold; what it COSTS is
 * on a per-brand price list, because FR-034 requires the same service to be
 * priced differently per brand and FR-040 requires exactly one canonical source.
 *
 * NO ORDER, NO BASKET, NO AVAILABILITY CHECK. This surface configures a
 * catalogue. Selecting from it is Step 5 (DEC-0030, Rule 42).
 */
final class ServiceCatalogController
{
    private const SORTABLE = ['code', 'name', 'display_order', 'created_at', 'updated_at'];

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ServiceCatalogRegistry $registry)
    {
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('viewCatalog', Service::class);

        return $this->paginated(
            $request,
            ServiceCategory::query()->forTenant($this->context()->tenantId()),
            'categories',
            static fn (ServiceCategory $c): array => CatalogProjection::category($c),
            'display_order'
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success(
            ['category' => CatalogProjection::category($this->registry->createCategory($this->context(), $validated))],
            201
        );
    }

    public function updateCategory(Request $request, string $category): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $context = $this->context();
        $model = $this->find(ServiceCategory::query()->forTenant($context->tenantId()), $category);

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::success([
            'category' => CatalogProjection::category($this->registry->updateCategory($context, $model, $validated)),
        ]);
    }

    // ------------------------------------------------------------------
    // Services (FR-031)
    // ------------------------------------------------------------------

    public function services(Request $request): JsonResponse
    {
        Gate::authorize('viewCatalog', Service::class);

        $query = Service::query()->forTenant($this->context()->tenantId());

        // Allow-listed filter. `unit_kind` is an enum, so an arbitrary value is
        // refused rather than silently matching nothing.
        if (($unitKind = $request->query('unit_kind')) !== null) {
            if (! in_array($unitKind, [Service::UNIT_KILOAN, Service::UNIT_SATUAN], true)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Jenis satuan tidak dikenal.',
                    ['unit_kind' => [Service::UNIT_KILOAN, Service::UNIT_SATUAN]]
                );
            }

            $query->where('unit_kind', $unitKind);
        }

        if (($categoryId = $request->query('service_category_id')) !== null) {
            // Filtered WITHIN the tenant scope already applied above, so a
            // category id from another tenant matches nothing rather than
            // widening the query.
            $query->where('service_category_id', $categoryId);
        }

        return $this->paginated(
            $request,
            $query,
            'services',
            static fn (Service $s): array => CatalogProjection::service($s),
            'display_order'
        );
    }

    public function showService(string $service): JsonResponse
    {
        Gate::authorize('viewCatalog', Service::class);

        $model = $this->find(Service::query()->forTenant($this->context()->tenantId()), $service);

        return ApiResponse::success(['service' => CatalogProjection::service($model)]);
    }

    public function storeService(Request $request): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $validated = $request->validate($this->serviceRules(required: true));

        return ApiResponse::success(
            ['service' => CatalogProjection::service($this->registry->createService($this->context(), $validated))],
            201
        );
    }

    public function updateService(Request $request, string $service): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $context = $this->context();
        $model = $this->find(Service::query()->forTenant($context->tenantId()), $service);

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate($this->serviceRules(required: false));

        return ApiResponse::success([
            'service' => CatalogProjection::service($this->registry->updateService($context, $model, $validated)),
        ]);
    }

    // ------------------------------------------------------------------
    // Packages (FR-032)
    // ------------------------------------------------------------------

    public function packages(Request $request): JsonResponse
    {
        Gate::authorize('viewCatalog', Service::class);

        return $this->paginated(
            $request,
            ServicePackage::query()->forTenant($this->context()->tenantId()),
            'packages',
            fn (ServicePackage $p): array => CatalogProjection::package($p, $this->packageItems($p)),
            'display_order'
        );
    }

    public function storePackage(Request $request): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $package = $this->registry->createPackage($this->context(), $validated);

        return ApiResponse::success(['package' => CatalogProjection::package($package)], 201);
    }

    public function updatePackage(Request $request, string $package): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $context = $this->context();
        $model = $this->find(ServicePackage::query()->forTenant($context->tenantId()), $package);

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $updated = $this->registry->updatePackage($context, $model, $validated);

        return ApiResponse::success([
            'package' => CatalogProjection::package($updated, $this->packageItems($updated)),
        ]);
    }

    /**
     * Replace a package's composition wholesale.
     *
     * PUT rather than PATCH, and replace rather than merge, because a
     * composition is only meaningful as a whole: adding and removing lines one
     * request at a time leaves the package transiently describing something the
     * tenant never intended, and a failure halfway leaves it there.
     */
    public function setPackageItems(Request $request, string $package): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $context = $this->context();
        $model = $this->find(ServicePackage::query()->forTenant($context->tenantId()), $package);

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.service_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $updated = $this->registry->setPackageItems($context, $model, $validated['items']);

        return ApiResponse::success([
            'package' => CatalogProjection::package($updated, $this->packageItems($updated)),
        ]);
    }

    // ------------------------------------------------------------------
    // Add-ons (FR-033) — CATALOGUE ENTRIES ONLY. Applying one to an order
    // line is Step 5 (DEC-0031 B).
    // ------------------------------------------------------------------

    public function addons(Request $request): JsonResponse
    {
        Gate::authorize('viewCatalog', Service::class);

        return $this->paginated(
            $request,
            ServiceAddon::query()->forTenant($this->context()->tenantId()),
            'addons',
            static fn (ServiceAddon $a): array => CatalogProjection::addon($a),
            'display_order'
        );
    }

    public function storeAddon(Request $request): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::success(
            ['addon' => CatalogProjection::addon($this->registry->createAddon($this->context(), $validated))],
            201
        );
    }

    public function updateAddon(Request $request, string $addon): JsonResponse
    {
        Gate::authorize('manageCatalog', Service::class);

        $context = $this->context();
        $model = $this->find(ServiceAddon::query()->forTenant($context->tenantId()), $addon);

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        return ApiResponse::success([
            'addon' => CatalogProjection::addon($this->registry->updateAddon($context, $model, $validated)),
        ]);
    }

    // ------------------------------------------------------------------
    // Plumbing
    // ------------------------------------------------------------------

    /** @return array<string, list<mixed>> */
    private function serviceRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'code' => [$presence, 'string', 'max:32'],
            'name' => [$presence, 'string', 'max:255'],

            // FR-031 — enumerated, mirroring the database CHECK. A free-text
            // unit would make it impossible for a later step to know whether a
            // quantity is a weight or a count.
            'unit_kind' => [$presence, Rule::in([Service::UNIT_KILOAN, Service::UNIT_SATUAN])],

            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'service_category_id' => ['sometimes', 'nullable', 'uuid'],

            // Grams for kiloan, item count for satuan. Integer either way — a
            // floating-point weight is something the scale and the counter can
            // disagree about.
            'minimum_quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'turnaround_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'nullable', 'date'],
            'effective_until' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'display_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return list<array{service_id: string, quantity: int}> */
    private function packageItems(ServicePackage $package): array
    {
        return DB::table('service_package_items')
            ->where('tenant_id', $package->tenant_id)
            ->where('service_package_id', $package->id)
            ->orderBy('service_id')
            ->get(['service_id', 'quantity'])
            ->map(static fn (object $row): array => [
                'service_id' => (string) $row->service_id,
                'quantity' => (int) $row->quantity,
            ])
            ->all();
    }

    private function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scoped
     * @return TModel
     */
    private function find(Builder $scoped, string $id): Model
    {
        $model = $scoped->whereKey($id)->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }

    /**
     * @param  callable(Model): array<string, mixed>  $project
     */
    private function paginated(
        Request $request,
        Builder $scoped,
        string $key,
        callable $project,
        string $defaultSort,
    ): JsonResponse {
        $sort = $request->query('sort', $defaultSort);

        if (! in_array($sort, self::SORTABLE, true)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Kolom pengurutan tidak dikenal.',
                ['sort' => self::SORTABLE]
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

        if (($term = trim((string) $request->query('q', ''))) !== '') {
            // The tenant filter was applied by the CALLER before this method saw
            // the builder, so a user-supplied term can only ever narrow an
            // already-scoped query. A search that filtered by term first and
            // scoped afterwards is one refactor away from leaking (threat T-02).
            $scoped->where(function (Builder $q) use ($term): void {
                $q->where('name', 'ilike', '%'.$term.'%')
                    ->orWhere('code', 'ilike', '%'.$term.'%');
            });
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
