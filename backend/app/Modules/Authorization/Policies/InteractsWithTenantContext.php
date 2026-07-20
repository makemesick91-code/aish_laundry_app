<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Tenancy\Context\TenantContext;

/**
 * Shared plumbing for every Step 3 policy.
 *
 * TWO INVARIANTS EVERY POLICY INHERITS
 * ------------------------------------
 * 1. NO TENANT CONTEXT => NO PERMISSION. A policy consulted outside a resolved
 *    tenant context denies. It never falls back to "any tenant the user belongs
 *    to", because a policy that guesses the tenant will eventually guess wrong.
 *
 * 2. THE RESOURCE MUST BELONG TO THE ACTIVE TENANT. `sameTenant()` is checked in
 *    every resource policy method. This is defence in depth: queries are already
 *    tenant-scoped, so a foreign resource should never reach a policy at all —
 *    but if one ever does, the policy denies rather than authorising it.
 */
trait InteractsWithTenantContext
{
    protected function context(): ?TenantContext
    {
        if (! app()->bound(TenantContext::class)) {
            return null;
        }

        return app(TenantContext::class);
    }

    /**
     * Does the caller hold this permission in the ACTIVE tenant right now?
     *
     * Recomputed per call from live membership and role assignments — never read
     * from a token or a cache (DEC-0025 §5).
     */
    protected function allows(string $permission): bool
    {
        $context = $this->context();

        if ($context === null) {
            return false;
        }

        return app(EffectivePermissions::class)->has($context, $permission);
    }

    /**
     * Is this resource owned by the active tenant?
     */
    protected function sameTenant(?string $resourceTenantId): bool
    {
        $context = $this->context();

        if ($context === null || $resourceTenantId === null) {
            return false;
        }

        return hash_equals($context->tenantId(), $resourceTenantId);
    }

    /**
     * Permission AND ownership. Both, always.
     */
    protected function allowsWithin(string $permission, ?string $resourceTenantId): bool
    {
        return $this->sameTenant($resourceTenantId) && $this->allows($permission);
    }
}
