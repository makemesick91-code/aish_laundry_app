<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Http;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\SharedKernel\Http\OptimisticConcurrency;
use App\Modules\Tenancy\Context\TenantContext;

/**
 * FR-025 — CONTEXT-AWARE ADDRESS MASKING, ENFORCED ON THE SERVER.
 *
 * A customer address is `RESTRICTED` data whose disclosure carries a
 * physical-safety dimension, not merely a privacy one: it is where somebody
 * sleeps (Rule 21, Rule 32 §2.2).
 *
 * THIS IS AN ALLOW-LIST PROJECTION, NOT A FILTERED MODEL.
 * The masked shapes are ASSEMBLED from named fields rather than produced by
 * removing fields from a full array. The difference is the whole design: a
 * filter has the full value in hand and must remember to drop it, so a new
 * column is exposed by default and a forgotten `unset` is a disclosure. Here a
 * field that is not named is never read, so the failure mode is a missing field
 * rather than a leaked one (Rule 32 hard rule 7).
 *
 * A masked payload therefore contains no hidden full value. There is nothing to
 * recover from the serialised form, because the precise fields were never put
 * into it.
 *
 * THE CONTEXTS, AND HOW THEY ARE DERIVED
 * --------------------------------------
 * Rule 32 §2.2 sets the rendering per surface and role:
 *
 *   Ops list view                     -> not rendered at all
 *   Ops detail, pickup/delivery roles -> full
 *   Ops detail, production roles      -> area only
 *   Public tracking portal            -> area only, never a full address
 *
 * Step 4 introduces NO new role or permission (DEC-0031 A2), so the context is
 * derived from the permissions that already exist:
 *
 *   `customer.manage` -> FULL. These are the roles that arrange a pickup or a
 *                        delivery — cashier, outlet manager, tenant admin,
 *                        tenant owner — and house-number precision is what the
 *                        job needs.
 *   `customer.view`   -> AREA. A viewer who cannot arrange logistics does not
 *                        need to know which house (Rule 32 §2.2 rule 8).
 *   neither           -> NO ADDRESS. In practice such a caller cannot reach the
 *                        customer resource at all, so this is defence in depth
 *                        rather than the primary control.
 *
 * THE HONEST LIMITATION. `customer.manage` is a PROXY for "performs pickup or
 * delivery", and it is a good proxy only because Step 4's roles happen to line
 * up that way. Step 8 introduces courier assignment, and a courier who needs
 * delivery precision without customer-management rights will not fit this
 * mapping. When that role arrives it needs its own permission and this class
 * must be revisited — it must NOT be handled by granting couriers
 * `customer.manage`, which would hand them the whole customer surface to solve
 * an address-precision problem.
 *
 * WHAT "AREA ONLY" MEANS, PRECISELY. District, city and province. Never the
 * street line, never the house number, never the postal code — a postal code
 * plus a city narrows a location far more than it appears to, and it is not
 * needed to say roughly where something is.
 */
final class AddressProjection
{
    /** House-number precision. */
    public const CONTEXT_FULL = 'full';

    /** District, city, province. Nothing that identifies a building. */
    public const CONTEXT_AREA = 'area';

    /** No address at all. */
    public const CONTEXT_NONE = 'none';

    /**
     * Decide the context from the caller's VERIFIED permissions.
     *
     * Read from the server's own membership record on every call, never from
     * anything the client sent (Rule 40 hard rule 1).
     */
    public static function contextFor(TenantContext $context): string
    {
        // Re-derived from the membership record on every call. A permission the
        // caller sent, or one cached from an earlier request, is not proof
        // (Rule 40 hard rules 1 and 3) — and a role removed a moment ago must
        // narrow the projection on the very next request.
        $held = app(EffectivePermissions::class)->forContext($context);

        if (in_array(PermissionRegistry::CUSTOMER_MANAGE, $held, true)) {
            return self::CONTEXT_FULL;
        }

        if (in_array(PermissionRegistry::CUSTOMER_VIEW, $held, true)) {
            return self::CONTEXT_AREA;
        }

        return self::CONTEXT_NONE;
    }

    /**
     * @return array<string, mixed>|null Null when the context grants no address.
     */
    public static function forContext(CustomerAddress $address, string $context): ?array
    {
        return match ($context) {
            self::CONTEXT_FULL => self::full($address),
            self::CONTEXT_AREA => self::area($address),
            default => null,
        };
    }

    /**
     * The list-row shape: NO ADDRESS, ever, at any permission level.
     *
     * Rule 32 §2.2 rule 7 — rendering fifty addresses on one screen is the
     * tenant's customer base in a single photograph, and a screenshot of it
     * leaves the building in a way fifty individual lookups do not. This is not
     * a permission question, so it takes no permission argument.
     *
     * @return array<string, mixed>
     */
    public static function listRow(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            // Suitability flags drive an operational choice — which saved
            // address can take a pickup — and disclose no location.
            'is_pickup_suitable' => (bool) $address->is_pickup_suitable,
            'is_delivery_suitable' => (bool) $address->is_delivery_suitable,
            'is_primary' => (bool) $address->is_primary,
            'is_active' => (bool) $address->is_active,
            'version' => OptimisticConcurrency::versionOf($address),
        ];
    }

    /** @return array<string, mixed> */
    private static function full(CustomerAddress $address): array
    {
        return [
            ...self::listRow($address),
            'precision' => self::CONTEXT_FULL,
            'address_line' => $address->address_line,
            'district' => $address->district,
            'city' => $address->city,
            'province' => $address->province,
            'postal_code' => $address->postal_code,
            // Operator-authored free text. It sits at full precision because it
            // routinely contains landmarks — "pagar hijau, seberang masjid" —
            // which are location data by another name.
            'notes' => $address->notes,
        ];
    }

    /**
     * Assembled from three named fields.
     *
     * `address_line`, `postal_code` and `notes` are not read here at all, so
     * there is no full value in the payload for a client to recover.
     *
     * @return array<string, mixed>
     */
    private static function area(CustomerAddress $address): array
    {
        return [
            ...self::listRow($address),
            'precision' => self::CONTEXT_AREA,
            'district' => $address->district,
            'city' => $address->city,
            'province' => $address->province,
        ];
    }
}
