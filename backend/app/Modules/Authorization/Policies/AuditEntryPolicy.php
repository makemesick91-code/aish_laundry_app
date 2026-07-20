<?php

declare(strict_types=1);

namespace App\Modules\Authorization\Policies;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\User;

/**
 * READ authorization for the audit trail. There is deliberately no create,
 * update, or delete method: audit entries are written only by AuditRecorder and
 * are never edited or deleted through any interface (Rule 04, hard rule 8).
 *
 * An entry with a NULL tenant is identity/platform scope and is NEVER visible to
 * a tenant member, regardless of their permissions. `sameTenant(null)` returns
 * false, so this is enforced by the shared trait rather than remembered here.
 */
final class AuditEntryPolicy
{
    use InteractsWithTenantContext;

    public function viewAny(User $user): bool
    {
        return $this->allows(PermissionRegistry::AUDIT_VIEW);
    }

    public function view(User $user, AuditEntry $entry): bool
    {
        return $this->allowsWithin(PermissionRegistry::AUDIT_VIEW, $entry->tenant_id);
    }
}
