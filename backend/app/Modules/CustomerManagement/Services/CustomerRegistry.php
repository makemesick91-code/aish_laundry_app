<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * The only writer of customer master data.
 *
 * Every method takes the resolved TenantContext explicitly rather than reading
 * an ambient one. A service that resolves its own tenant is a service that can
 * be called from a queue worker with the wrong one (Rule 20, hard rule 6).
 *
 * DUPLICATE POLICY: DETECT, NEVER MERGE
 * ------------------------------------
 * A create whose normalized phone already exists in this tenant is REJECTED and
 * the existing customer's id is returned, so an operator decides what to do. The
 * system never merges two profiles on its own.
 *
 * Cross-tenant duplicate detection does not exist here and must never be added:
 * the same phone in two tenants is two unrelated people as far as this product
 * is concerned, and looking across the boundary to notice otherwise is itself
 * the leak (Rule 02 hard rule 11, Rule 18 invariant 8, threat T-08).
 */
final class CustomerRegistry
{
    /**
     * Bounded retry for customer-code allocation. Three is enough for an
     * ordinary race; a fourth failure means something other than concurrency is
     * wrong, and spinning would hide it.
     */
    private const CODE_ALLOCATION_ATTEMPTS = 3;

    /**
     * @param  array{name: string, phone: string, email?: ?string, internal_notes?: ?string}  $attributes
     */
    public function create(TenantContext $context, array $attributes): Customer
    {
        $normalized = $this->normalizeOrFail($attributes['phone']);

        // Detect within THIS tenant only.
        $existing = Customer::query()
            ->forTenant($context->tenantId())
            ->where('phone_normalized', $normalized)
            ->first();

        if ($existing !== null) {
            // The id is safe to return: the caller is already authorised for
            // this tenant, and without it the operator cannot act on the
            // conflict except by searching again.
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Pelanggan dengan nomor telepon ini sudah terdaftar di tenant ini.',
                ['phone' => ['duplicate'], 'existing_customer_id' => $existing->id]
            );
        }

        // The code allocator races under concurrency; `(tenant_id, code)` is
        // unique, so the loser of a race gets a constraint violation rather than
        // a duplicate code. Retrying is the correct response — see allocateCode().
        for ($attempt = 1; ; $attempt++) {
            $customer = new Customer($this->fillableFrom($attributes));

            // Server-derived, never from input.
            $customer->tenant_id = $context->tenantId();
            $customer->phone_normalized = $normalized;
            $customer->code = $this->allocateCode($context->tenantId());
            $customer->status = Customer::STATUS_ACTIVE;

            try {
                $customer->save();

                return $customer;
            } catch (UniqueConstraintViolationException $exception) {
                // A concurrent create took the phone number between the check
                // above and this write. That is the duplicate case, not a code
                // collision, and it must surface as such.
                if (str_contains($exception->getMessage(), 'customers_tenant_phone_unique')) {
                    throw ApiException::of(
                        ErrorCode::VALIDATION_FAILED,
                        'Pelanggan dengan nomor telepon ini sudah terdaftar di tenant ini.',
                        ['phone' => ['duplicate']]
                    );
                }

                // Otherwise it is a code collision. Bounded, because an
                // unbounded retry on a defect that is not a race would spin.
                if ($attempt >= self::CODE_ALLOCATION_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TenantContext $context, Customer $customer, array $attributes): Customer
    {
        $this->assertSameTenant($context, $customer);

        if (array_key_exists('phone', $attributes) && $attributes['phone'] !== null) {
            $normalized = $this->normalizeOrFail((string) $attributes['phone']);

            $clash = Customer::query()
                ->forTenant($context->tenantId())
                ->where('phone_normalized', $normalized)
                ->whereKeyNot($customer->getKey())
                ->exists();

            if ($clash) {
                throw ApiException::of(
                    ErrorCode::VALIDATION_FAILED,
                    'Nomor telepon ini sudah dipakai pelanggan lain di tenant ini.',
                    ['phone' => ['duplicate']]
                );
            }

            $customer->phone_normalized = $normalized;
        }

        $customer->fill($this->fillableFrom($attributes));
        $customer->save();

        return $customer;
    }

    /**
     * Archive, never delete (threat T-18).
     */
    public function archive(TenantContext $context, Customer $customer): Customer
    {
        $this->assertSameTenant($context, $customer);

        $customer->status = Customer::STATUS_ARCHIVED;
        $customer->save();

        return $customer;
    }

    public function restore(TenantContext $context, Customer $customer): Customer
    {
        $this->assertSameTenant($context, $customer);

        $customer->status = Customer::STATUS_ACTIVE;
        $customer->save();

        return $customer;
    }

    /**
     * Append a consent record. Never updates an existing one (FR-027, FR-028).
     */
    public function recordConsent(
        TenantContext $context,
        Customer $customer,
        string $consentType,
        string $state,
        string $source,
        ?string $note = null,
    ): CustomerConsent {
        $this->assertSameTenant($context, $customer);

        $consent = new CustomerConsent([
            'consent_type' => $consentType,
            'state' => $state,
            'source' => $source,
            'note' => $note,
        ]);

        $consent->tenant_id = $context->tenantId();
        $consent->customer_id = $customer->id;
        $consent->recorded_by_membership_id = $context->membershipId();

        // SERVER-SIDE timestamp. A client-suppliable value here is a backdated
        // consent record (threat T-07).
        $consent->recorded_at = now();

        $consent->save();

        return $consent;
    }

    /**
     * A per-tenant sequential customer code, human-usable at the counter.
     *
     * NOT LOCKED, DELIBERATELY. The obvious implementation — count the tenant's
     * rows `FOR UPDATE` — is invalid in PostgreSQL, which rejects `FOR UPDATE`
     * alongside an aggregate. It would also serialise every customer creation in
     * a tenant behind one lock for a value that is a label, not an invariant.
     *
     * The real guarantee is the unique index on `(tenant_id, code)`. This
     * allocator may lose a race; the caller retries, bounded. That is the
     * correct shape: the database decides, and the application recovers.
     *
     * The code is NEVER a credential and never grants access to anything
     * (Rule 03's principle for order numbers, applied here).
     */
    private function allocateCode(string $tenantId): string
    {
        $used = Customer::query()
            ->withTrashed()
            ->forTenant($tenantId)
            ->count();

        return sprintf('PLG-%06d', $used + 1);
    }

    private function normalizeOrFail(string $raw): string
    {
        try {
            return PhoneNumber::normalize($raw);
        } catch (InvalidArgumentException $exception) {
            throw ApiException::of(
                ErrorCode::VALIDATION_FAILED,
                'Nomor telepon tidak valid.',
                ['phone' => [$exception->getMessage()]]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function fillableFrom(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'name', 'phone', 'email', 'internal_notes',
        ]));
    }

    /**
     * Defence in depth. Queries are already tenant-scoped, so a foreign
     * customer should never reach this service — but if one ever does, it fails
     * loudly rather than being written to under the wrong tenant.
     */
    private function assertSameTenant(TenantContext $context, Customer $customer): void
    {
        if (! hash_equals($context->tenantId(), $customer->tenant_id)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
