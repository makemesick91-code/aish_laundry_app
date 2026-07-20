<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Context;

use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;

/**
 * TURNS AN UNTRUSTED REQUEST INTO A SERVER-DECIDED CONTEXT.
 *
 * This is the single most security-critical class in Step 3, so its contract is
 * stated explicitly:
 *
 *   INPUT:  an authenticated User (established by the auth guard) and a
 *           CLIENT-SUPPLIED tenant identifier, which is a REQUEST and nothing
 *           more.
 *   OUTPUT: a TenantContext, or an exception. Never a partially-trusted state.
 *
 * THE RULE IT ENFORCES
 * --------------------
 * Rule 02 hard rule 9: "A client-supplied tenant ID is never authorization
 * proof. It is an untrusted hint that must be validated against the
 * authenticated user's memberships."
 * Rule 02 hard rule 10: "The backend verifies membership and permission on every
 * request that touches tenant data."
 *
 * FAIL-CLOSED: every path that does not positively establish an ACTIVE
 * membership throws. There is no branch that returns a context on a doubt, and
 * no "default tenant" fallback — a fallback tenant is how a request ends up
 * acting on data nobody authorised.
 *
 * NO ENUMERATION ORACLE
 * ---------------------
 * "Tenant does not exist" and "tenant exists but you have no membership" both
 * produce TENANT_ACCESS_DENIED. Distinguishing them would let any authenticated
 * user enumerate the platform's tenant list by probing identifiers (Rule 02;
 * Rule 32 hard rule 2 — denial and absence are indistinguishable across a tenant
 * boundary).
 *
 * `suspended` and `revoked` DO get distinct codes, but only for a membership
 * that genuinely belongs to the CALLER. Telling a user about their own
 * membership discloses nothing they do not already know, and leaving them with a
 * generic denial would strand them with no idea what to do (Rule 29 hard
 * rule 9).
 */
final class TenantContextResolver
{
    /**
     * Resolve and validate. Throws rather than returning a doubtful context.
     *
     * @throws ApiException
     */
    public function resolve(User $user, string $requestedTenantId): TenantContext
    {
        // The membership lookup is keyed on BOTH the requested tenant and the
        // AUTHENTICATED user's id. The user id comes from the auth guard, never
        // from the request body, which is what makes this a verification rather
        // than a lookup of whatever the client asked for.
        $membership = Membership::query()
            ->where('tenant_id', $requestedTenantId)
            ->where('user_id', $user->id)
            ->first();

        if ($membership === null) {
            // Covers BOTH "no such tenant" and "no membership". Deliberately
            // indistinguishable — see the class docblock.
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        // Status is checked BEFORE the tenant row is loaded, so a suspended
        // member learns nothing new about the tenant itself.
        match ($membership->status) {
            Membership::STATUS_ACTIVE => null,
            Membership::STATUS_SUSPENDED => throw ApiException::of(ErrorCode::MEMBERSHIP_SUSPENDED),
            Membership::STATUS_REVOKED => throw ApiException::of(ErrorCode::MEMBERSHIP_REVOKED),
            // `invited` has not been accepted, so it grants nothing. It is
            // reported as plain access denial rather than as its own code: the
            // recovery action is to accept the invitation, which happens outside
            // this endpoint.
            default => throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED),
        };

        $tenant = Tenant::query()->whereKey($requestedTenantId)->first();

        if ($tenant === null) {
            // A live membership pointing at a missing tenant is a data-integrity
            // problem, not an access decision. Still fails closed.
            throw ApiException::of(ErrorCode::TENANT_ACCESS_DENIED);
        }

        return new TenantContext($tenant, $membership);
    }

    /**
     * Attach an outlet to an already-resolved context.
     *
     * The outlet lookup is SCOPED BY THE RESOLVED TENANT, not by the requested
     * one. An outlet belonging to another tenant is therefore not "rejected" —
     * it is simply not found, because the query never had access to it. That is
     * the difference between failing closed and remembering to check.
     *
     * @throws ApiException
     */
    public function attachOutlet(TenantContext $context, string $requestedOutletId): TenantContext
    {
        $outlet = Outlet::query()
            ->where('tenant_id', $context->tenantId())
            ->whereKey($requestedOutletId)
            ->first();

        if ($outlet === null) {
            throw ApiException::of(ErrorCode::OUTLET_ACCESS_DENIED);
        }

        return $context->withOutlet($outlet);
    }
}
