<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http\Controllers;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\CustomerManagement\Services\CustomerRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Marketing-consent history for a customer (FR-027, FR-028).
 *
 * TWO VERBS ONLY: read the history, append a record. There is no update and no
 * delete route, because consent history is append-only — withdrawal is a new
 * record, not the removal of an old one.
 *
 * The absence of those routes is one of three layers; the model refuses the
 * operation and PostgreSQL rules make it a no-op even from outside the
 * application. FR-028 names a MIGRATION among the things that must not reset an
 * opt-out, and a migration never passes through this controller.
 */
final class CustomerConsentController
{
    public function __construct(private readonly CustomerRegistry $registry)
    {
    }

    public function index(string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('view', $model);

        $records = CustomerConsent::query()
            ->forTenant($context->tenantId())
            ->where('customer_id', $model->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success([
            'consents' => $records->map(static fn (CustomerConsent $c): array => [
                'id' => $c->id,
                'consent_type' => $c->consent_type,
                'state' => $c->state,
                'source' => $c->source,
                'recorded_at' => $c->recorded_at?->toIso8601String(),
                'recorded_by_membership_id' => $c->recorded_by_membership_id,
                'note' => $c->note,
            ])->values()->all(),

            // The derived current state per type, so a caller does not have to
            // re-implement "latest record wins" and get the tie-break wrong.
            'current' => [
                CustomerConsent::TYPE_MARKETING_WHATSAPP => $model->currentConsentState(CustomerConsent::TYPE_MARKETING_WHATSAPP),
                CustomerConsent::TYPE_MARKETING_EMAIL => $model->currentConsentState(CustomerConsent::TYPE_MARKETING_EMAIL),
                CustomerConsent::TYPE_MARKETING_SMS => $model->currentConsentState(CustomerConsent::TYPE_MARKETING_SMS),
            ],
        ]);
    }

    public function store(Request $request, string $customer): JsonResponse
    {
        $context = app(TenantContext::class);
        $model = $this->findCustomerOrFail($context, $customer);

        Gate::authorize('manageConsent', $model);

        // `recorded_at` is deliberately NOT accepted. A client-suppliable
        // consent timestamp is a backdated consent record (threat T-07).
        $validated = $request->validate([
            'consent_type' => ['required', 'string', 'in:'.implode(',', [
                CustomerConsent::TYPE_MARKETING_WHATSAPP,
                CustomerConsent::TYPE_MARKETING_EMAIL,
                CustomerConsent::TYPE_MARKETING_SMS,
            ])],
            'state' => ['required', 'string', 'in:'.implode(',', [
                CustomerConsent::STATE_GRANTED,
                CustomerConsent::STATE_WITHDRAWN,
            ])],
            'source' => ['required', 'string', 'in:counter,customer_app,written_form,phone,import'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $consent = $this->registry->recordConsent(
            $context,
            $model,
            $validated['consent_type'],
            $validated['state'],
            $validated['source'],
            $validated['note'] ?? null,
        );

        return ApiResponse::success([
            'consent' => [
                'id' => $consent->id,
                'consent_type' => $consent->consent_type,
                'state' => $consent->state,
                'source' => $consent->source,
                'recorded_at' => $consent->recorded_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function findCustomerOrFail(TenantContext $context, string $id): Customer
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
