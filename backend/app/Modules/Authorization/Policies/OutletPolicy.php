<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Outlet;

final class OutletPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::OUTLET_VIEW);
    }

    public function view(User $user, Outlet $outlet): bool
    {
        return $this->allowsWithin(PermissionRegistry::OUTLET_VIEW, $outlet->tenant_id);
    }

    /**
     * Make this outlet the active outlet.
     *
     * Note this is a DIFFERENT permission from viewing. A courier can see the
     * outlet they are assigned to without being able to roam the tenant's
     * outlets (Rule 32, hard rule 11 — the courier surface offers no traversal
     * path).
     */
    public function switchTo(User $user, Outlet $outlet): bool
    {
        return $this->allowsWithin(PermissionRegistry::OUTLET_SWITCH, $outlet->tenant_id);
    }

    public function manage(User $user, Outlet $outlet): bool
    {
        return $this->allowsWithin(PermissionRegistry::OUTLET_MANAGE, $outlet->tenant_id);
    }
}
