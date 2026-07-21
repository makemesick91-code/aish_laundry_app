<?php

declare(strict_types=1);

namespace App\Modules\ServiceCatalog\Http\Controllers;

use App\Modules\ServiceCatalog\Http\CatalogProjection;
use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\PriceListItem;
use App\Modules\ServiceCatalog\Services\PriceListItemRegistry;
use App\Modules\ServiceCatalog\Services\PriceListPublisher;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * PER-BRAND PRICE LISTS (FR-034 … FR-040).
 *
 * THREE PERMISSIONS, NOT ONE, AND THE SPLIT IS FINANCIAL RATHER THAN
 * ORGANISATIONAL.
 *   - `price_list.view`    — reading what things cost. Widely granted.
 *   - `price_list.manage`  — authoring a DRAFT. Costs nothing until published.
 *   - `price_list.publish` — freezing a version and making it the price
 *                            customers are charged. Irreversible, so it is its
 *                            own permission (FR-035, Rule 04).
 *
 * A kasir may read prices and may not change them: a cashier changing a price
 * is precisely the financial control point FR-039 exists to guard.
 *
 * PUBLISHING IS THE IRREVERSIBLE ACT, AND THIS CONTROLLER DOES NOT SOFTEN IT.
 * A published version is immutable; editing it is refused by the model guard for
 * every writer, not only for requests arriving here. Superseding creates a NEW
 * version and leaves the prior one byte-identical, so a Step 5 order can always
 * resolve the price that actually applied (FR-036).
 *
 * OVERLAP IS THE DATABASE'S ANSWER, not a validation query — see
 * PriceListPublisher. Two concurrent publishes cannot both win.
 *
 * NO ORDER, NO INVOICE, NO DISCOUNT, NO OVERRIDE FLOW. `price.override` is
 * registered as a permission contract only; the override acts on an order and is
 * Step 5 (DEC-0031 B).
 */
final class PriceListController
{
    private const SORTABLE = ['code', 'name', 'effective_from', 'created_at', 'updated_at'];

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly PriceListPublisher $publisher,
        private readonly PriceListItemRegistry $items,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriceList::class);

        $context = $this->context();

        $sort = $request->query('sort', 'effective_from');

        if (! in_array($sort, self::SORTABLE, true)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Kolom pengurutan tidak dikenal.',
                ['sort' => self::SORTABLE]
            );
        }

        $query = PriceList::query()->forTenant($context->tenantId());

        if (($status = $request->query('status')) !== null) {
            $statuses = [
                PriceList::STATUS_DRAFT,
                PriceList::STATUS_ACTIVE,
                PriceList::STATUS_SUPERSEDED,
                PriceList::STATUS_ARCHIVED,
            ];

            if (! in_array($status, $statuses, true)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Status daftar harga tidak dikenal.',
                    ['status' => $statuses]
                );
            }

            $query->where('status', $status);
        }

        if (($brandId = $request->query('laundry_brand_id')) !== null) {
            // Narrows a query that is ALREADY tenant-scoped, so a brand id from
            // another tenant matches nothing rather than widening the surface.
            $query->where('laundry_brand_id', $brandId);
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), self::MAX_PER_PAGE);

        $page = $query->orderBy($sort)->paginate($perPage);

        return ApiResponse::success([
            'price_lists' => array_map(
                static fn (PriceList $p): array => CatalogProjection::priceList($p),
                $page->items()
            ),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $priceList): JsonResponse
    {
        $context = $this->context();
        $model = $this->findPriceList($context, $priceList);

        Gate::authorize('view', $model);

        return ApiResponse::success([
            'price_list' => CatalogProjection::priceList($model, $this->itemsOf($model)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', PriceList::class);

        $validated = $request->validate([
            // FR-034 — a price list belongs to a BRAND. The brand's tenant is
            // re-derived server-side by the publisher; this id is an untrusted
            // hint that must resolve within the active tenant or 404.
            'laundry_brand_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $draft = $this->publisher->createDraft(
            $this->context(),
            $validated['laundry_brand_id'],
            $validated
        );

        return ApiResponse::success(['price_list' => CatalogProjection::priceList($draft)], 201);
    }

    /**
     * Publish a draft, optionally superseding a currently active list.
     *
     * The supersede path CLOSES the outgoing list's window rather than deleting
     * it: FR-036 requires a past order to still resolve the price that applied
     * when it was created, so history is never removed.
     */
    public function publish(Request $request, string $priceList): JsonResponse
    {
        $context = $this->context();
        $model = $this->findPriceList($context, $priceList);

        Gate::authorize('publish', $model);

        $validated = $request->validate([
            'supersedes_price_list_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $supersedes = null;

        if (! empty($validated['supersedes_price_list_id'])) {
            $supersedes = $this->findPriceList($context, $validated['supersedes_price_list_id']);
        }

        $published = $this->publisher->publish($context, $model, $supersedes);

        return ApiResponse::success([
            'price_list' => CatalogProjection::priceList($published, $this->itemsOf($published)),
        ]);
    }

    // ------------------------------------------------------------------
    // Items — the money-bearing rows
    // ------------------------------------------------------------------

    public function storeItem(Request $request, string $priceList): JsonResponse
    {
        $context = $this->context();
        $model = $this->findPriceList($context, $priceList);

        Gate::authorize('update', $model);

        $validated = $request->validate([
            'service_id' => ['sometimes', 'nullable', 'uuid'],
            'service_package_id' => ['sometimes', 'nullable', 'uuid'],
            'service_addon_id' => ['sometimes', 'nullable', 'uuid'],

            // NOT `numeric`, and NOT `decimal`. `integer` here plus
            // RupiahRounding::fromInput() in the registry means a float or a
            // formatted string is refused rather than coerced — money enters as
            // whole Rupiah or it does not enter (Rule 04 hard rule 2).
            'amount_rupiah' => ['required', 'integer', 'min:0'],
        ]);

        $item = $this->items->addItem($context, $model, $validated);

        return ApiResponse::success(['item' => CatalogProjection::priceListItem($item)], 201);
    }

    public function updateItem(Request $request, string $priceList, string $item): JsonResponse
    {
        $context = $this->context();
        $list = $this->findPriceList($context, $priceList);

        Gate::authorize('update', $list);

        $model = PriceListItem::query()
            ->forTenant($context->tenantId())
            ->where('price_list_id', $list->id)
            ->whereKey($item)
            ->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'amount_rupiah' => ['required', 'integer', 'min:0'],
        ]);

        $updated = $this->items->updateItem($context, $model, $validated['amount_rupiah']);

        return ApiResponse::success(['item' => CatalogProjection::priceListItem($updated)]);
    }

    /**
     * Remove an item from a DRAFT list.
     *
     * The only DELETE verb in the Step 4 surface, and it is safe precisely
     * because the registry refuses it on anything published: a draft has never
     * priced an order, so removing a line from one destroys no history
     * (FR-036, threat T-18).
     */
    public function destroyItem(string $priceList, string $item): JsonResponse
    {
        $context = $this->context();
        $list = $this->findPriceList($context, $priceList);

        Gate::authorize('update', $list);

        $model = PriceListItem::query()
            ->forTenant($context->tenantId())
            ->where('price_list_id', $list->id)
            ->whereKey($item)
            ->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        $this->items->removeItem($context, $model);

        return ApiResponse::success(['deleted' => true]);
    }

    // ------------------------------------------------------------------
    // Plumbing
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function itemsOf(PriceList $priceList): array
    {
        return PriceListItem::query()
            ->forTenant($priceList->tenant_id)
            ->where('price_list_id', $priceList->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (PriceListItem $i): array => CatalogProjection::priceListItem($i))
            ->all();
    }

    private function findPriceList(TenantContext $context, string $id): PriceList
    {
        $model = PriceList::query()
            ->forTenant($context->tenantId())
            ->whereKey($id)
            ->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }

    private function context(): TenantContext
    {
        return app(TenantContext::class);
    }
}
