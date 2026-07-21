<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\ServiceCatalog\Models\PriceList;

/**
 * Server-side authorization for per-brand price lists (FR-034 … FR-040).
 *
 * THREE PERMISSIONS, SPLIT ON FINANCIAL CONSEQUENCE RATHER THAN ON CONVENIENCE.
 *
 *   view    — reading what things cost. Granted to every role that quotes or
 *             reports a price: kasir, outlet manager, finance, admin, owner.
 *   manage  — authoring a DRAFT. A draft charges nobody anything.
 *   publish — freezing a version and making it the price customers are actually
 *             charged. Irreversible, and therefore its own permission.
 *
 * Collapsing `publish` into `manage` would mean anyone who can prepare a price
 * can also put it into force, and FR-035's immutability guarantee would rest on
 * a permission granted for a much smaller act (Rule 04).
 *
 * A kasir holds `view` and not `manage`: a cashier changing a price is exactly
 * the financial control point FR-039 exists to guard.
 */
final class PriceListPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::PRICE_LIST_VIEW);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRICE_LIST_VIEW, $priceList->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::PRICE_LIST_MANAGE);
    }

    /**
     * Edit a draft and its items.
     *
     * The permission is necessary and not sufficient: `PriceListItemRegistry`
     * and the `PriceList` model guard both refuse a write touching a PUBLISHED
     * list, whatever permission the caller holds. Immutability is a property of
     * the record, not a privilege level (FR-035, FR-036).
     */
    public function update(User $user, PriceList $priceList): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRICE_LIST_MANAGE, $priceList->tenant_id);
    }

    public function publish(User $user, PriceList $priceList): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRICE_LIST_PUBLISH, $priceList->tenant_id);
    }

    /**
     * There is deliberately no `delete` method.
     *
     * A published price list is the record of what a past order was charged.
     * Removing one would break FR-036 for every order that referenced it, so no
     * role is offered a delete path — superseding is how a price list stops
     * applying.
     */
}
