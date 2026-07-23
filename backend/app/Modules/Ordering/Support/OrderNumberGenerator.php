<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Support;

use App\Modules\Ordering\Models\Order;

/**
 * A per-tenant, human-usable order number that GRANTS NO ACCESS (FR-053).
 *
 * The number is a label a customer and a cashier speak at a counter. It is never
 * the primary key, never an authorization token, and knowing one confers nothing
 * — an order is reached only through a tenant-scoped, permission-checked query,
 * and a request for another tenant's order number returns "not found" exactly as
 * a non-existent one does (Rule 48).
 *
 * NOT LOCKED, DELIBERATELY — the same shape as the Step 4 customer code
 * allocator. The real guarantee is the UNIQUE (tenant_id, order_number)
 * constraint; this allocator may lose a race and the caller retries, bounded.
 * The database decides; the application recovers. Sequential-per-tenant means a
 * competitor counting order numbers across tenants learns nothing about any
 * other tenant's volume.
 */
final class OrderNumberGenerator
{
    public function next(string $tenantId): string
    {
        $used = Order::query()
            ->withTrashed()
            ->forTenant($tenantId)
            ->count();

        return sprintf('ORD-%06d', $used + 1);
    }
}
