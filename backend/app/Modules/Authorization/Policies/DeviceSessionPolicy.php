<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\DeviceSession;

/**
 * Authorization for DEVICE SESSION inspection and revocation.
 *
 * THE SELF-SERVICE CARVE-OUT
 * --------------------------
 * A user may ALWAYS revoke their own device session, with no tenant permission
 * required beyond the baseline. "Sign this device out" must never be gated
 * behind an administrative role: a user who suspects their phone is compromised
 * needs to act immediately, not wait for an admin.
 *
 * Revoking SOMEBODY ELSE'S device session is administrative and does require the
 * tenant permission, plus the target session belonging to the active tenant.
 */
final class DeviceSessionPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::DEVICE_SESSION_VIEW);
    }

    public function view(User $user, DeviceSession $session): bool
    {
        if ($this->isOwn($user, $session)) {
            return $this->sameTenant($session->tenant_id)
                && $this->allows(PermissionRegistry::SESSION_VIEW_SELF);
        }

        return $this->allowsWithin(PermissionRegistry::DEVICE_SESSION_VIEW, $session->tenant_id);
    }

    public function revoke(User $user, DeviceSession $session): bool
    {
        if ($this->isOwn($user, $session)) {
            return $this->sameTenant($session->tenant_id)
                && $this->allows(PermissionRegistry::SESSION_REVOKE_SELF);
        }

        return $this->allowsWithin(PermissionRegistry::DEVICE_SESSION_REVOKE, $session->tenant_id);
    }

    private function isOwn(User $user, DeviceSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
