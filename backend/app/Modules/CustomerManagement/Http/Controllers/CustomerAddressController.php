<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http\Controllers;

use App\Modules\CustomerManagement\Http\AddressProjection;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\CustomerManagement\Services\CustomerAddressRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * FR-024 / FR-025 — saved customer addresses.
 *
 * THE TENANT IS NEVER READ FROM THE REQUEST. It comes from the verified
 * `TenantContext`, and the customer is resolved WITHIN that tenant, so a
 * customer id belonging to another tenant produces the same 404 as one that does
 * not exist (Rule 39 hard rule 1, Rule 48 hard rule 5).
 *
 * MASKING IS APPLIED HERE, ON THE SERVER, by `AddressProjection`. A client that
 * ignores the response shape learns nothing extra, because the fields it would
 * need were never serialised (FR-025).
 *
 * NO ADDRESS EVER APPEARS IN A URL. Addresses are referenced by opaque
 * identifier only — a path or query string is logged by every proxy in front of
 * the application, appears in a browser history, and is passed on in a referrer
 * (Rule 32, Rule 46).
 */
final class CustomerAddressController
{
    public function __construct(private readonly CustomerAddressRegistry $registry) {}

    public function index(string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('view', $model);

        $projectionContext = AddressProjection::contextFor($context);

        $addresses = CustomerAddress::query()
            ->forTenant($context->tenantId())
            ->where('customer_id', $model->id)
            ->orderByDesc('is_primary')
            ->orderBy('label')
            ->get();

        return ApiResponse::success([
            // The list shape carries NO location at any permission level
            // (Rule 32 §2.2 rule 7). The precision the caller would get on a
            // detail read is reported so a client can render honestly rather
            // than implying it is showing everything.
            'addresses' => $addresses
                ->map(static fn (CustomerAddress $a): array => AddressProjection::listRow($a))
                ->all(),
            'precision' => $projectionContext,
        ]);
    }

    public function show(string $customer, string $address): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('view', $model);

        $record = $this->registry->resolve($context, $model, $address);
        $projected = AddressProjection::forContext($record, AddressProjection::contextFor($context));

        if ($projected === null) {
            // Reached only if a caller holds neither customer permission, which
            // the Gate above already refuses. Kept as a second, independent
            // refusal: the projection must never be able to emit an address it
            // was not asked to.
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return ApiResponse::success(['address' => $projected]);
    }

    public function store(Request $request, string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('update', $model);

        $address = $this->registry->create($context, $model, $this->rules($request, required: true));

        return ApiResponse::success([
            'address' => AddressProjection::forContext($address, AddressProjection::contextFor($context)),
        ], 201);
    }

    public function update(Request $request, string $customer, string $address): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('update', $model);

        $record = $this->registry->resolve($context, $model, $address);

        // Refused with 409 before anything is written, so a stale edit never
        // half-applies (threat T-12).
        OptimisticConcurrency::assertFresh($request, $record);

        $updated = $this->registry->update($context, $record, $this->rules($request, required: false));

        return ApiResponse::success([
            'address' => AddressProjection::forContext($updated, AddressProjection::contextFor($context)),
        ]);
    }

    public function archive(Request $request, string $customer, string $address): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('update', $model);

        $record = $this->registry->resolve($context, $model, $address);
        OptimisticConcurrency::assertFresh($request, $record);

        $archived = $this->registry->archive($context, $record);

        return ApiResponse::success([
            'address' => AddressProjection::forContext($archived, AddressProjection::contextFor($context)),
        ]);
    }

    public function reactivate(Request $request, string $customer, string $address): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('update', $model);

        $record = $this->registry->resolve($context, $model, $address);
        OptimisticConcurrency::assertFresh($request, $record);

        $restored = $this->registry->reactivate($context, $record);

        return ApiResponse::success([
            'address' => AddressProjection::forContext($restored, AddressProjection::contextFor($context)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return $request->validate([
            'label' => [$presence, 'string', 'max:64'],
            'address_line' => [$presence, 'string', 'max:500'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],

            // Indonesian postal codes are exactly five digits. Validated so a
            // typo is caught at the counter rather than at the doorstep.
            'postal_code' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{5}$/'],

            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_pickup_suitable' => ['sometimes', 'boolean'],
            'is_delivery_suitable' => ['sometimes', 'boolean'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
    }

    private function findCustomerOrFail(TenantContext $context, string $id): Customer
    {
        $customer = Customer::query()
            ->forTenant($context->tenantId())
            ->whereKey($id)
            ->first();

        if ($customer === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $customer;
    }
}
