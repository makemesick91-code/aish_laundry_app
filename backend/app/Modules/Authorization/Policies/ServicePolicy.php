<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;

/**
 * Server-side authorization for the service catalogue (FR-031 … FR-033).
 *
 * READING IS WIDE, AUTHORING IS NARROW. A kasir, an outlet manager, and finance
 * all need to see what the tenant sells; authoring the catalogue is a tenant-wide
 * commercial act reserved to the owner and admin (Rule 03 hard rule 1).
 *
 * The methods are named `viewCatalog` / `manageCatalog` rather than the usual
 * `viewAny` / `create` / `update` because ONE policy governs four resources —
 * category, service, package, add-on. They share a permission pair, and giving
 * each its own policy class would be four files asserting the same two lines.
 *
 * Registered against `Service::class` as the catalogue's representative model.
 * The catalogue is one aggregate for authorization purposes: nobody would
 * sensibly grant the right to author services while withholding the right to
 * author the categories they sit in.
 */
final class ServicePolicy
{
    use InteractsWithTenantContext;

    public function viewCatalog(User $user): bool
    {
        return $this->allows(PermissionRegistry::SERVICE_VIEW);
    }

    public function manageCatalog(User $user): bool
    {
        return $this->allows(PermissionRegistry::SERVICE_MANAGE);
    }

    /**
     * There is deliberately no `delete` method.
     *
     * A service referenced by a future order must remain resolvable, so
     * deactivation replaces deletion and no role is offered a hard-delete path
     * (threat T-18).
     */
    public function viewAny(User $user): bool
    {
        return $this->viewCatalog($user);
    }
}
