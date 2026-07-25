<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\NotificationIntent;

/**
 * Server-side authorization for notification intents (Step 7, FR-093 … FR-099).
 *
 * TWO PERMISSIONS, split on COST:
 *   view — reading what was sent, to whom, when, and with what outcome.
 *   send — causing another send: retrying a failure, or preparing the manual
 *          WhatsApp fallback.
 *
 * The split is not ceremony. Every send costs the tenant real money with a
 * third-party provider, billed separately from the subscription (Rule 14 guardrail
 * 8, NOT-020). Reading a message history and spending a tenant's messaging budget
 * are different acts and are granted separately — which is also why finance holds
 * `view` and not `send`.
 *
 * Both checks are tenant-bound, so another tenant's notification history is
 * indistinguishable from an absent one (Rule 48).
 */
final class NotificationIntentPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::NOTIFICATION_VIEW);
    }

    public function view(User $user, NotificationIntent $intent): bool
    {
        return $this->allowsWithin(PermissionRegistry::NOTIFICATION_VIEW, $intent->tenant_id);
    }

    public function send(User $user, NotificationIntent $intent): bool
    {
        return $this->allowsWithin(PermissionRegistry::NOTIFICATION_SEND, $intent->tenant_id);
    }
}
