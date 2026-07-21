<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Membership;

/**
 * Authorization for MEMBERSHIP administration within the active tenant.
 *
 * Every method requires BOTH the permission AND that the target membership
 * belongs to the active tenant. A membership from another tenant is denied even
 * to a tenant owner — ownership of tenant A confers nothing in tenant B
 * (Rule 02).
 */
final class MembershipPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::MEMBERSHIP_VIEW);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->allowsWithin(PermissionRegistry::MEMBERSHIP_VIEW, $membership->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::MEMBERSHIP_INVITE);
    }

    public function suspend(User $user, Membership $membership): bool
    {
        // A member may not suspend themselves: it would lock the tenant's only
        // owner out of their own tenant with no recovery path inside the product.
        if ($membership->user_id === $user->id) {
            return false;
        }

        return $this->allowsWithin(PermissionRegistry::MEMBERSHIP_SUSPEND, $membership->tenant_id);
    }

    public function revoke(User $user, Membership $membership): bool
    {
        if ($membership->user_id === $user->id) {
            return false;
        }

        return $this->allowsWithin(PermissionRegistry::MEMBERSHIP_REVOKE, $membership->tenant_id);
    }

    /**
     * Assign a TENANT role. Platform roles are rejected separately, by
     * PermissionRegistry::assertAssignableToMembership() at the write boundary —
     * a policy answers "may this actor act", not "is this value legal"
     * (DEC-0025 §8).
     */
    public function assignRole(User $user, Membership $membership): bool
    {
        return $this->allowsWithin(PermissionRegistry::MEMBERSHIP_ROLE_ASSIGN, $membership->tenant_id);
    }

    public function removeRole(User $user, Membership $membership): bool
    {
        return $this->allowsWithin(PermissionRegistry::MEMBERSHIP_ROLE_REMOVE, $membership->tenant_id);
    }

    /**
     * Assign this membership to an outlet, or revoke that assignment
     * (Step 4 staff assignment, DEC-0031 A).
     *
     * A SEPARATE PERMISSION FROM `assignRole`, because the two acts differ in
     * consequence. Rostering somebody to a counter confers no capability at all;
     * granting a role confers exactly capability. Collapsing them would mean the
     * person who maintains the duty roster could also hand out authority — and
     * would let the escalation guard on role assignment be walked around by
     * calling the innocent-looking endpoint instead (threat T-14).
     *
     * Self-assignment is permitted here, unlike suspension and revocation: an
     * owner rostering themselves onto their own outlet grants themselves nothing
     * they did not already have.
     */
    public function assignStaff(User $user, Membership $membership): bool
    {
        return $this->allowsWithin(
            PermissionRegistry::STAFF_ASSIGNMENT_MANAGE,
            $membership->tenant_id
        );
    }
}
