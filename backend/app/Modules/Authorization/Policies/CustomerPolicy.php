<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Identity\Models\User;

/**
 * Server-side authorization for customer master data (Rule 40, hard rule 2).
 *
 * Inherits both invariants from InteractsWithTenantContext: no tenant context
 * means no permission, and the resource must belong to the ACTIVE tenant. The
 * second is defence in depth — queries are already tenant-scoped, so a foreign
 * customer should never reach a policy at all, but if one ever does the policy
 * denies rather than authorising it.
 *
 * CONSENT IS A SEPARATE PERMISSION FROM MANAGEMENT.
 * Editing a customer's name and changing what they agreed to receive are
 * different acts with different consequences: the first is a correction, the
 * second is a legal position (Rule 08). A kasir may do the first and not the
 * second.
 */
final class CustomerPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::CUSTOMER_VIEW);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allowsWithin(PermissionRegistry::CUSTOMER_VIEW, $customer->tenant_id);
    }

    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::CUSTOMER_MANAGE);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->allowsWithin(PermissionRegistry::CUSTOMER_MANAGE, $customer->tenant_id);
    }

    /**
     * Archive, not delete.
     *
     * There is deliberately no `delete` method on this policy. A customer
     * referenced by a future order must remain resolvable, so no hard-delete
     * path is offered to any role (T-18).
     */
    public function archive(User $user, Customer $customer): bool
    {
        return $this->allowsWithin(PermissionRegistry::CUSTOMER_MANAGE, $customer->tenant_id);
    }

    public function manageConsent(User $user, Customer $customer): bool
    {
        return $this->allowsWithin(
            PermissionRegistry::CUSTOMER_CONSENT_MANAGE,
            $customer->tenant_id
        );
    }
}
