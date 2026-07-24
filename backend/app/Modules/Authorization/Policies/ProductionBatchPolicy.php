<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Production\Models\ProductionBatch;

/**
 * Server-side authorization for production batches (Step 6, FR-074).
 *
 * Batches are part of the production-operations surface, so they use the same two
 * permissions as production jobs (Rule 40, PermissionRegistry — PRODUCTION_OPERATE
 * is documented to cover "batch"):
 *   view    — listing batches, reading a batch, its members, and its timeline.
 *   operate — creating, updating, adding/removing members, and closing a batch.
 *
 * Held by outlet_manager and production_operator; a cashier, a courier, a
 * customer, and finance hold NONE of operate. Each check is BOTH a permission
 * check AND a same-tenant check (`allowsWithin`), so a denial for a foreign batch
 * is indistinguishable from "does not exist" (Rule 48, Rule 40).
 */
final class ProductionBatchPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::PRODUCTION_VIEW);
    }

    public function create(User $user): bool
    {
        // Creating a batch acts on the ACTIVE tenant (there is no resource yet),
        // so the permission check is against the active context.
        return $this->allows(PermissionRegistry::PRODUCTION_OPERATE);
    }

    public function view(User $user, ProductionBatch $batch): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRODUCTION_VIEW, $batch->tenant_id);
    }

    public function operate(User $user, ProductionBatch $batch): bool
    {
        return $this->allowsWithin(PermissionRegistry::PRODUCTION_OPERATE, $batch->tenant_id);
    }
}
