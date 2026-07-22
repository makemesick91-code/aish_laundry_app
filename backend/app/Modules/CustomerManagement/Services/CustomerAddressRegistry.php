<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Services;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\AuditRecorder;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * FR-024 — SAVED CUSTOMER ADDRESSES. The writer that did not exist (SEC-05).
 *
 * The table, the model and the read projection all existed. Nothing wrote to
 * them. A requirement whose storage exists and whose writer does not is not
 * partially implemented — it is unimplemented with the furniture arranged, and
 * calling it delivered is the exact failure Rule 50 names: "a migration is not a
 * tested schema, and a table is not a feature."
 *
 * WHAT THIS DELIBERATELY IS NOT
 * -----------------------------
 * It stores addresses a customer has saved. It does not schedule a pickup,
 * assign a courier, sequence a route, or record a delivery attempt — all of
 * which are Step 8 and are not pulled forward on the strength of an address
 * table existing (Rule 36 hard rule 4). `is_pickup_suitable` and
 * `is_delivery_suitable` are MASTER DATA FLAGS describing whether an address
 * can take a pickup or a delivery at all. They record a property of the place,
 * not a decision about a parcel.
 *
 * THE PRIMARY-ADDRESS INVARIANT
 * -----------------------------
 * At most one active primary address per customer, and it is maintained inside
 * a transaction with a row lock rather than by checking first and hoping. Two
 * staff members setting different primaries at the same counter is an ordinary
 * event, and "two primary addresses" would make "where does this customer
 * usually want it delivered" a question with two answers.
 *
 * Archiving the primary does NOT silently promote another. A promotion nobody
 * asked for is a delivery address chosen by software, and Rule 09's custody
 * concerns start at the address. The customer is simply left with no primary
 * until somebody decides.
 */
final class CustomerAddressRegistry
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        TenantContext $context,
        Customer $customer,
        array $attributes,
    ): CustomerAddress {
        $this->assertSameTenant($context, $customer);

        return DB::transaction(function () use ($context, $customer, $attributes): CustomerAddress {
            $address = new CustomerAddress($this->fillableFrom($attributes));
            $address->tenant_id = $context->tenantId();
            $address->customer_id = $customer->id;

            // `is_primary` is not fillable: it is an invariant-bearing field, and
            // mass-assigning it would let a request body silently displace
            // another address.
            $wantsPrimary = (bool) ($attributes['is_primary'] ?? false);

            // The FIRST address a customer has becomes their primary whether or
            // not the caller asked. A customer with exactly one saved address
            // and no primary is a state that helps nobody.
            $isFirst = ! CustomerAddress::query()
                ->forTenant($context->tenantId())
                ->where('customer_id', $customer->id)
                ->exists();

            $address->is_primary = $wantsPrimary || $isFirst;

            // DEMOTE FIRST, then save. A partial unique index
            // (`customer_addresses_one_primary`) enforces at most one primary
            // per customer at the engine, so saving the new primary before
            // clearing the old one is refused by PostgreSQL rather than
            // silently producing two. The database is right and the ordering
            // was wrong; the index is what makes the invariant hold under
            // concurrency, so it stays and the code accommodates it.
            if ($address->is_primary) {
                $this->demoteOtherPrimaries($context, $customer, null);
            }

            $address->save();

            $this->audit->record(
                action: AuditAction::CUSTOMER_ADDRESS_CREATED,
                subjectType: CustomerAddress::class,
                subjectId: $address->id,
                tenantId: $context->tenantId(),
                actorUserId: $context->userId(),
                actorMembershipId: $context->membershipId(),
                // The LABEL and the customer, never the address itself. An
                // audit row holding a street address is the same RESTRICTED
                // datum again, in a table with different retention and a
                // different audience (Rule 46 hard rule 2).
                metadata: [
                    'customer_id' => $customer->id,
                    'label' => $address->label,
                    'is_primary' => $address->is_primary,
                ],
            );

            return $address->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        TenantContext $context,
        CustomerAddress $address,
        array $attributes,
    ): CustomerAddress {
        $this->assertSameTenantAddress($context, $address);

        return DB::transaction(function () use ($context, $address, $attributes): CustomerAddress {
            $address->fill($this->fillableFrom($attributes));

            if (array_key_exists('is_primary', $attributes)) {
                $wantsPrimary = (bool) $attributes['is_primary'];

                if (! $wantsPrimary && $address->is_primary) {
                    // Demoting the only primary is refused rather than silently
                    // accepted. "No primary at all" reached by unticking a box
                    // reads like a formatting change and is not one.
                    throw ApiException::of(
                        ErrorCode::VALIDATION_FAILED,
                        'Tandai alamat lain sebagai utama terlebih dahulu, '
                        .'bukan menghapus tanda dari alamat ini.',
                        ['is_primary' => ['promote_another_first']]
                    );
                }

                $address->is_primary = $wantsPrimary;
            }

            $changedFields = array_keys($address->getDirty());

            // Same ordering as create, for the same reason: the partial unique
            // index refuses a second primary at the engine.
            if ($address->is_primary) {
                $this->demoteOtherPrimaries($context, $address->customer, $address->id);
            }

            $address->save();

            $this->audit->record(
                action: AuditAction::CUSTOMER_ADDRESS_UPDATED,
                subjectType: CustomerAddress::class,
                subjectId: $address->id,
                tenantId: $context->tenantId(),
                actorUserId: $context->userId(),
                actorMembershipId: $context->membershipId(),
                // FIELD NAMES ONLY. The values are the address.
                metadata: [
                    'customer_id' => $address->customer_id,
                    'changed_fields' => $changedFields,
                ],
            );

            return $address->refresh();
        });
    }

    /**
     * Deactivate, never delete (threat T-18).
     *
     * An address a customer no longer uses is still the address a past pickup
     * went to. Removing the row would make a past custody transfer unexplainable.
     */
    public function archive(TenantContext $context, CustomerAddress $address): CustomerAddress
    {
        $this->assertSameTenantAddress($context, $address);

        $address->is_active = false;

        // The archived address stops being primary. Leaving it primary would
        // mean the customer's default delivery target is an address staff have
        // marked unusable. NOTHING IS PROMOTED IN ITS PLACE — see the class
        // note; software does not choose where a parcel goes.
        $address->is_primary = false;
        $address->save();

        $this->audit->record(
            action: AuditAction::CUSTOMER_ADDRESS_ARCHIVED,
            subjectType: CustomerAddress::class,
            subjectId: $address->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: ['customer_id' => $address->customer_id],
        );

        return $address->refresh();
    }

    public function reactivate(TenantContext $context, CustomerAddress $address): CustomerAddress
    {
        $this->assertSameTenantAddress($context, $address);

        $address->is_active = true;

        // Reactivation restores the address, NOT its former primary status. A
        // primary that came back on its own would displace whatever the customer
        // has been using since (the same discipline as SEC-08's reactivation
        // rule: restore the record, never a decision somebody reversed).
        $address->save();

        $this->audit->record(
            action: AuditAction::CUSTOMER_ADDRESS_REACTIVATED,
            subjectType: CustomerAddress::class,
            subjectId: $address->id,
            tenantId: $context->tenantId(),
            actorUserId: $context->userId(),
            actorMembershipId: $context->membershipId(),
            metadata: ['customer_id' => $address->customer_id],
        );

        return $address->refresh();
    }

    /**
     * Tenant-scoped lookup. A foreign id and an absent id are indistinguishable
     * (Rule 48 hard rule 5).
     */
    public function resolve(TenantContext $context, Customer $customer, string $id): CustomerAddress
    {
        $address = CustomerAddress::query()
            ->forTenant($context->tenantId())
            ->where('customer_id', $customer->id)
            ->whereKey($id)
            ->first();

        if ($address === null) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }

        return $address;
    }

    /**
     * @param  string|null  $keepId  Null when the incoming primary has no id yet,
     *                               which is the case during creation.
     */
    private function demoteOtherPrimaries(
        TenantContext $context,
        Customer $customer,
        ?string $keepId,
    ): void {
        // A direct UPDATE rather than a load-and-save loop: it is one statement,
        // it cannot interleave with itself, and it does not depend on how many
        // addresses happen to be wrong.
        $query = CustomerAddress::query()
            ->forTenant($context->tenantId())
            ->where('customer_id', $customer->id)
            ->where('is_primary', true);

        if ($keepId !== null) {
            $query->whereKeyNot($keepId);
        }

        $query->update(['is_primary' => false, 'version' => DB::raw('version + 1')]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function fillableFrom(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'label',
            'address_line',
            'district',
            'city',
            'province',
            'postal_code',
            'notes',
            'is_pickup_suitable',
            'is_delivery_suitable',
        ]));
    }

    private function assertSameTenant(TenantContext $context, Customer $customer): void
    {
        if (! hash_equals($context->tenantId(), (string) $customer->tenant_id)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }

    private function assertSameTenantAddress(TenantContext $context, CustomerAddress $address): void
    {
        if (! hash_equals($context->tenantId(), (string) $address->tenant_id)) {
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data tidak ditemukan.');
        }
    }
}
