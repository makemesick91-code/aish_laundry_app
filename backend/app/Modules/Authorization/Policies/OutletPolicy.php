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

    /**
     * Maintain the outlet's Step 4 master data: hours, capacity, quiet hours,
     * zones, shifts, and printers (FR-041 … FR-047).
     *
     * A SEPARATE PERMISSION FROM `manage`, AND THAT SEPARATION IS THE POINT.
     * `OUTLET_MANAGE` creates outlets and re-parents them to brands — an
     * organisational act reserved to the owner and tenant admin. Running an
     * outlet's day-to-day configuration is what an outlet manager does, so
     * `OUTLET_MASTER_DATA_MANAGE` is granted to that role and `OUTLET_MANAGE` is
     * not (Rule 03 hard rule 1 — least privilege, by role, not by convenience).
     *
     * Collapsing the two would hand every outlet manager the ability to create
     * outlets, which is a wider grant than the job needs.
     */
    public function manageMasterData(User $user, Outlet $outlet): bool
    {
        return $this->allowsWithin(
            PermissionRegistry::OUTLET_MASTER_DATA_MANAGE,
            $outlet->tenant_id
        );
    }

    /**
     * Read outlet master data.
     *
     * Reading configuration is reading the outlet, so this rides on
     * `OUTLET_VIEW` rather than inventing a third permission. A permission that
     * gates nothing anyone would separately grant is noise in the matrix.
     */
    public function viewMasterData(User $user, Outlet $outlet): bool
    {
        return $this->allowsWithin(PermissionRegistry::OUTLET_VIEW, $outlet->tenant_id);
    }

    /**
     * Configure the tenant-wide proof policy (FR-046).
     *
     * TENANT-WIDE, NOT PER-OUTLET, so it takes no Outlet. A custody-proof
     * requirement that varied by outlet would mean a parcel's evidence
     * requirement depended on which counter it passed through, and Rule 09 hard
     * rule 2 admits no such variation.
     */
    public function manageProofPolicy(User $user): bool
    {
        return $this->allows(PermissionRegistry::OUTLET_MASTER_DATA_MANAGE);
    }
}
