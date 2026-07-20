<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Context;

use App\Modules\Organization\Models\Outlet;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use RuntimeException;

/**
 * THE RESOLVED, SERVER-DECIDED TENANT CONTEXT for the current request.
 *
 * IMMUTABLE BY CONSTRUCTION. Every property is `readonly` and there is no
 * setter. Once the context is resolved it cannot be changed for the remainder of
 * the request — not by a controller, not by a service, not by a later
 * middleware. This matters because authorization decisions are made against this
 * object: a mutable tenant context means a request could be authorised as tenant
 * A and then act as tenant B.
 *
 * Switching tenant is therefore not an in-request mutation. It is an explicit
 * endpoint (`POST /api/v1/context/tenant`) that affects the NEXT request, and it
 * is audited.
 *
 * WHAT THIS OBJECT IS NOT
 * -----------------------
 * It is NOT built from a client-supplied tenant id. The client supplies a
 * REQUEST; ResolveTenantContext looks up an ACTIVE membership for
 * (authenticated user, requested tenant) and constructs this only if one exists.
 * A client-supplied tenant id is never authorization proof (Rule 02, hard
 * rule 9).
 */
final class TenantContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Membership $membership,
        public readonly ?Outlet $outlet = null,
    ) {
        if ($membership->tenant_id !== $tenant->id) {
            // Unreachable through the resolver, but a context whose membership
            // belongs to a different tenant would be a cross-tenant
            // authorization bug of the most severe kind, so it fails loudly
            // rather than trusting that it cannot happen.
            throw new RuntimeException(
                'TenantContext constructed with a membership belonging to a different tenant. '
                .'This is a tenant-isolation defect and must never be caught and ignored.'
            );
        }

        if ($outlet !== null && $outlet->tenant_id !== $tenant->id) {
            throw new RuntimeException(
                'TenantContext constructed with an outlet belonging to a different tenant. '
                .'This is a tenant-isolation defect and must never be caught and ignored.'
            );
        }
    }

    public function tenantId(): string
    {
        return $this->tenant->id;
    }

    public function membershipId(): string
    {
        return $this->membership->id;
    }

    public function userId(): string
    {
        return $this->membership->user_id;
    }

    public function outletId(): ?string
    {
        return $this->outlet?->id;
    }

    public function withOutlet(Outlet $outlet): self
    {
        // Returns a NEW context rather than mutating this one — immutability is
        // preserved even for the legitimate case of adding outlet scope.
        return new self($this->tenant, $this->membership, $outlet);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant' => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
                'timezone' => $this->tenant->timezone,
            ],
            'membership' => [
                'id' => $this->membership->id,
                'status' => $this->membership->status,
            ],
            'outlet' => $this->outlet === null ? null : [
                'id' => $this->outlet->id,
                'name' => $this->outlet->name,
                'code' => $this->outlet->code,
                'timezone' => $this->outlet->timezone,
            ],
        ];
    }
}
