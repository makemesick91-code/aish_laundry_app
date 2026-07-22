<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Http\AddressProjection;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;

/**
 * THE ALLOW-LIST PROJECTION for customer data (Rule 32, hard rule 7).
 *
 * WHY AN ALLOW-LIST AND NOT A DENYLIST
 * ------------------------------------
 * A denylist ("hide internal_notes") is safe only until somebody adds a column.
 * The new column leaks by DEFAULT, and nothing fails — the leak is discovered by
 * a customer, not by a test. An allow-list inverts that: a field absent from
 * this file is never assembled, so it cannot leak (FR-030).
 *
 * MASKING IS THE DEFAULT, NOT THE EXCEPTION
 * -----------------------------------------
 * `summary()` — the list-row shape — masks the phone and omits the address
 * ENTIRELY. Rule 32 hard rule 4 forbids rendering an address in a list row at
 * all, not merely masking it there.
 *
 * `detail()` still masks the phone. Unmasking is a deliberate, per-record,
 * permissioned, recorded action and is never a side effect of opening a record
 * (Rule 32, hard rule 5). Step 4 offers no unmasking endpoint, so no unmasked
 * phone leaves this application.
 *
 * WHAT IS NEVER HERE
 * ------------------
 * `phone_normalized` (a match key, not display data), `internal_notes` on any
 * customer-facing surface, and anything belonging to another tenant.
 */
final class CustomerProjection
{
    /**
     * List-row shape. No address, masked phone.
     *
     * @return array<string, mixed>
     */
    public static function summary(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
            'phone_masked' => PhoneNumber::mask($customer->phone_normalized),
            'status' => $customer->status,
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),

            // The optimistic-concurrency token a client echoes back when it
            // edits (threat T-12). Opaque: compare it and return it, never parse
            // it. Note it is NOT `updated_at`, which is second-precision here
            // and therefore blind to two edits in the same second — see
            // `SharedKernel\Concerns\HasOptimisticVersion`.
            'version' => OptimisticConcurrency::versionOf($customer),
        ];
    }

    /**
     * Detail shape for an authorised staff member inside the tenant.
     *
     * THE CONTEXT GOVERNS TWO FIELDS, NOT ONE.
     *
     * `internal_notes` used to be emitted unconditionally, at every context
     * including `NONE`. That made `NONE` mean "no address" rather than "no
     * detail", which is not what the name says and not what a caller reading
     * the name would assume.
     *
     * It is the same class of datum as an address `notes` field, which IS
     * withheld below `FULL` precisely because operator free text carries
     * location — "antar ke rumah sebelah pagar hijau" is an address written in
     * prose. `internal_notes` carries that and more: service history,
     * complaints, and whatever a staff member thought worth recording about a
     * person. Withholding the street while emitting the commentary about who
     * lives there would be a strange place to draw a line.
     *
     * No shipped role currently reaches `AREA`, so this was latent rather than
     * live. That is a fact about today's permission topology and not a control
     * (Rule 03: hiding is never the access control), which is exactly why the
     * gate is here and not left to the topology.
     *
     * FR-030 requires internal notes never to reach the public tracking portal.
     * The portal is Step 7 and is a separate allow-list projection that shares
     * no code with this class; this gate is the additional server-side
     * restriction within the staff surfaces themselves.
     *
     * @param  string|null  $context  A masking context from
     *   `AddressProjection::contextFor()`. Nullable ONLY so an existing caller
     *   cannot silently pass the wrong context by omission — null yields
     *   neither addresses nor internal notes, which fails closed.
     *
     * @return array<string, mixed>
     */
    public static function detail(Customer $customer, ?string $context = null): array
    {
        $resolved = $context ?? AddressProjection::CONTEXT_NONE;

        return array_merge(self::summary($customer), [
            'email' => $customer->email,

            // ASSEMBLED, not filtered. At anything below FULL the key is not
            // present at all, so there is no hidden value in the payload for a
            // client to recover — the same discipline AddressProjection uses.
            ...($resolved === AddressProjection::CONTEXT_FULL
                ? ['internal_notes' => $customer->internal_notes]
                : []),
            // Routed through the SAME masking projection as the dedicated
            // address endpoints (FR-025). A masked address endpoint would be
            // pointless if the customer detail endpoint embedded the full
            // address alongside it — the relationship path is an access path
            // like any other (Rule 48 hard rule 3).
            'addresses' => $customer->addresses
                ->map(static fn (CustomerAddress $a): ?array => AddressProjection::forContext(
                    $a,
                    $resolved
                ))
                ->filter()
                ->values()
                ->values()
                ->all(),
        ]);
    }

    // `address()` USED TO LIVE HERE and has been removed (SEC-05, NEW-02).
    //
    // It was a `public static` serializer returning `address_line`,
    // `postal_code` and `notes` with NO permission context — a full-precision
    // address emitter sitting in the same file whose doctrine is that a field
    // not named is never assembled. It had zero callers, so it leaked nothing;
    // it was one inviting call site away from bypassing FR-025 entirely, and
    // "there are no callers today" is a statement about today.
    //
    // Every address projection now goes through `AddressProjection`, which
    // requires a context and fails closed without one.
}
