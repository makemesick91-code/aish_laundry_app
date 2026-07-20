<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;

/**
 * COMPUTES WHAT A MEMBERSHIP MAY ACTUALLY DO, FROM CURRENT STATE, EVERY TIME.
 *
 * DEC-0025 §5: "Effective permissions are recomputed from current active
 * membership and current role assignments on every authorization decision. They
 * are never cached into a long-lived token and never trusted from the client."
 *
 * WHY THERE IS NO DURABLE CACHE HERE
 * ----------------------------------
 * DEC-0025 §6 and §7 require membership revocation and role removal to take
 * effect IMMEDIATELY — "nothing waits for a token to expire". Any durable cache
 * (Redis, a claim in a JWT, a column on the session) reintroduces exactly the
 * staleness those clauses exclude. The registry's own trade-off note accepts the
 * per-request cost deliberately: "the alternative — embedding authorization
 * facts in a token — is what makes revocation slow, so the cost is accepted
 * deliberately."
 *
 * THERE IS NO MEMOISATION EITHER, AND THAT IS DELIBERATE.
 * ------------------------------------------------------
 * An earlier revision memoised the result per instance, on the reasoning that
 * the instance was request-scoped and so the memo was too. That reasoning does
 * not hold: a `scoped` binding is only flushed between requests by a runtime
 * that does so explicitly (Octane), and under a persistent worker the instance —
 * and its memo — outlive the request that created it. The observable effect was
 * that a role removed between two requests in the same process kept working,
 * which is precisely the staleness §6 and §7 exclude.
 *
 * Recomputing on every call costs one indexed query against `membership_role`.
 * That cost is the one the decision record already accepted; a cache that can
 * serve a revoked capability is not an optimisation, it is an authorization bug.
 *
 * SUSPENDED / REVOKED => ZERO PERMISSIONS
 * ---------------------------------------
 * A membership that is not ACTIVE yields an empty set, not a reduced set. There
 * is no residual capability left over from a revoked membership.
 */
final class EffectivePermissions
{
    /**
     * @return list<string>
     */
    public function forMembership(Membership $membership): array
    {
        if (! $membership->isActive()) {
            // DEC-0025 §6/§7 — immediate invalidation. No residual permission.
            return [];
        }

        // Read the CURRENT role assignments from the database. The pivot is
        // tenant-bound by composite foreign key, so nothing returned here can
        // belong to another tenant (DEC-0025 §4).
        $roleKeys = $membership->roles()
            ->pluck('roles.key')
            ->all();

        // permissionsForTenantRoles() ignores platform roles by category, so
        // even a corrupt assignment row could not confer a platform capability
        // through a membership (DEC-0025 §8).
        return PermissionRegistry::permissionsForTenantRoles($roleKeys);
    }

    /**
     * @return list<string>
     */
    public function forContext(TenantContext $context): array
    {
        return $this->forMembership($context->membership);
    }

    public function has(TenantContext $context, string $permission): bool
    {
        return in_array($permission, $this->forContext($context), true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAny(TenantContext $context, array $permissions): bool
    {
        $effective = $this->forContext($context);

        foreach ($permissions as $permission) {
            if (in_array($permission, $effective, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The role keys currently assigned to this membership, for display.
     *
     * Display only. An authorization decision is made on PERMISSIONS, never by
     * comparing a role name — role-name comparisons are how a renamed role
     * silently grants or removes access.
     *
     * @return list<string>
     */
    public function roleKeysForMembership(Membership $membership): array
    {
        if (! $membership->isActive()) {
            return [];
        }

        /** @var list<string> $keys */
        $keys = $membership->roles()->pluck('roles.key')->all();
        sort($keys);

        return $keys;
    }
}
