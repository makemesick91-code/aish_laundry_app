<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Tracking\Models\TrackingToken;

/**
 * Server-side authorization for tracking links (Step 7, FR-086 … FR-088).
 *
 * TWO PERMISSIONS, split on consequence (Rule 40):
 *   view   — reading a link's METADATA: state, issue and expiry times, view count.
 *            It never reads the token, because the token is not stored (TRK-002).
 *   manage — issuing, rotating, and revoking. Rotation invalidates the link the
 *            customer is currently holding and revocation is terminal with no undo,
 *            so it is a distinct control point from merely looking.
 *
 * Every check is BOTH a permission check AND a same-tenant check (`allowsWithin`),
 * so a denial for a foreign tenant's link is indistinguishable from "does not
 * exist" (Rule 48 hard rule 5).
 *
 * NOTE WHAT IS ABSENT: there is no `read the plaintext` capability, at any
 * permission level, for any role. The plaintext is returned once at issuance and
 * once at rotation and is unrecoverable thereafter — not because a policy forbids
 * reading it, but because nothing stored can produce it.
 */
final class TrackingTokenPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::TRACKING_VIEW);
    }

    public function view(User $user, TrackingToken $token): bool
    {
        return $this->allowsWithin(PermissionRegistry::TRACKING_VIEW, $token->tenant_id);
    }

    public function manage(User $user, TrackingToken $token): bool
    {
        return $this->allowsWithin(PermissionRegistry::TRACKING_MANAGE, $token->tenant_id);
    }

    /** Issuing has no existing token to check a tenant against; the order does. */
    public function create(User $user): bool
    {
        return $this->allows(PermissionRegistry::TRACKING_MANAGE);
    }
}
