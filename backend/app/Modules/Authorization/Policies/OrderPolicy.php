<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;

/**
 * Server-side authorization for orders (FR-048 … FR-060).
 *
 * FOUR PERMISSIONS, SPLIT ON CONSEQUENCE rather than convenience:
 *   view   — reading an order. Kasir, outlet manager, finance, admin, owner.
 *   create — taking a new order at the counter.
 *   manage — editing a DRAFT before it is placed.
 *   cancel — a control point that carries a mandatory reason and an actor.
 *
 * Each check is BOTH a permission check AND a same-tenant check (`allowsWithin`),
 * so holding `order.view` in tenant A never authorises reading tenant B's order —
 * and a denial for a foreign order is indistinguishable from "does not exist",
 * because the query that would find it is tenant-scoped and returns nothing
 * (Rule 48, Rule 40).
 *
 * There is deliberately no `delete`: an order is cancelled (with a reason), never
 * removed, so its financial history survives (Rule 04, FR-066).
 */
final class OrderPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::ORDER_VIEW);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->allowsWithin(PermissionRegistry::ORDER_VIEW, $order->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::ORDER_CREATE);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->allowsWithin(PermissionRegistry::ORDER_MANAGE, $order->tenant_id);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->allowsWithin(PermissionRegistry::ORDER_CANCEL, $order->tenant_id);
    }
}
