<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http\Controllers;

use App\Modules\CustomerManagement\Http\CustomerProjection;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Services\CustomerRegistry;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Customer master data (FR-021 … FR-030).
 *
 * EVERY LOOKUP IS TENANT-SCOPED BEFORE IT IS ANYTHING ELSE.
 * `findOrFail()` below filters on the verified tenant id first, so a foreign or
 * absent id produce the SAME 404. A caller cannot tell whether a record exists
 * in another tenant, which is the point (Rule 48, hard rule 5).
 *
 * SORT AND FILTER FIELDS ARE ALLOW-LISTED ENUMS, never raw column names, so a
 * client cannot sort by `internal_notes` or inject a column reference
 * (threat T-17).
 *
 * THERE IS NO BULK MUTATION AND NO EXPORT ENDPOINT in Step 4. Their absence is
 * asserted by a route test rather than assumed (threats T-19, T-20).
 */
final class CustomerController
{
    /** Columns a client may sort by. Never interpolated from input. */
    private const SORTABLE = ['name', 'code', 'created_at', 'updated_at'];

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CustomerRegistry $registry)
    {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $context = app(TenantContext::class);

        $sort = $request->query('sort', 'name');
        if (! in_array($sort, self::SORTABLE, true)) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Kolom pengurutan tidak dikenal.',
                ['sort' => self::SORTABLE]
            );
        }

        $perPage = min(
            max((int) $request->query('per_page', 25), 1),
            self::MAX_PER_PAGE
        );

        $query = Customer::query()->forTenant($context->tenantId());

        // FR-023: find by phone, name, or code within the tenant.
        //
        // The tenant filter is applied ABOVE, before any user-supplied term
        // touches the query. A search that filters by term first and scopes
        // afterwards is one refactor away from leaking (threat T-02).
        if (($term = trim((string) $request->query('q', ''))) !== '') {
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'ilike', '%'.$term.'%')
                    ->orWhere('code', 'ilike', '%'.$term.'%');

                // A phone term is matched on the NORMALIZED form, so `0812…`
                // and `+62812…` find the same customer.
                try {
                    $q->orWhere('phone_normalized', PhoneNumber::normalize($term));
                } catch (InvalidArgumentException) {
                    // Not a phone-shaped term. Name and code matching stands.
                }
            });
        }

        if (($status = $request->query('status')) !== null) {
            if (! in_array($status, [Customer::STATUS_ACTIVE, Customer::STATUS_ARCHIVED], true)) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Status tidak dikenal.',
                    ['status' => [Customer::STATUS_ACTIVE, Customer::STATUS_ARCHIVED]]
                );
            }

            $query->where('status', $status);
        }

        $page = $query->orderBy($sort)->paginate($perPage);

        return ApiResponse::success([
            'customers' => array_map(
                static fn (Customer $c): array => CustomerProjection::summary($c),
                $page->items()
            ),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $customer);

        Gate::authorize('view', $model);

        $model->load('addresses');

        return ApiResponse::success(['customer' => CustomerProjection::detail($model)]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $context = app(TenantContext::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = $this->registry->create($context, $validated);

        return ApiResponse::success(
            ['customer' => CustomerProjection::detail($customer->load('addresses'))],
            201
        );
    }

    public function update(Request $request, string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $customer);

        Gate::authorize('update', $model);

        // Two operators editing the same customer must not silently overwrite
        // each other. A caller that sends the version it read is never
        // overridden; the conflict is surfaced for a human to resolve rather
        // than resolved by whoever saved last (threat T-12).
        OptimisticConcurrency::assertFresh($request, $model);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->registry->update($context, $model, $validated);

        return ApiResponse::success(
            ['customer' => CustomerProjection::detail($updated->load('addresses'))]
        );
    }

    /**
     * Archive. There is no destroy endpoint, deliberately (threat T-18).
     */
    public function archive(string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findOrFail($context, $customer);

        Gate::authorize('archive', $model);

        $archived = $this->registry->archive($context, $model);

        return ApiResponse::success(['customer' => CustomerProjection::summary($archived)]);
    }

    /**
     * Tenant-scoped lookup.
     *
     * An id belonging to another tenant and an id that does not exist produce
     * the SAME 404, with the same body. Distinguishing them would confirm the
     * existence of another tenant's record (Rule 48, hard rule 5).
     */
    private function findOrFail(TenantContext $context, string $id): Customer
    {
        $model = Customer::query()
            ->forTenant($context->tenantId())
            ->whereKey($id)
            ->first();

        if ($model === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $model;
    }
}
