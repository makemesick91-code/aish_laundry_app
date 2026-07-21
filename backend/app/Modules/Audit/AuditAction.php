<?php

declare(strict_types=1);

namespace App\Modules\Audit;

/**
 * THE CLOSED AUDIT ACTION VOCABULARY.
 *
 * `audit_entries.action` is a technical identifier from a closed vocabulary,
 * never free text. Free-text actions cannot be aggregated, cannot be alerted on,
 * and turn every typo into an event that silently disappears from a report.
 *
 * Naming convention: `<subject>.<event>`, past-tense event.
 */
final class AuditAction
{
    // --- Identity scope (no tenant) --------------------------------------
    public const AUTH_LOGIN_SUCCEEDED = 'auth.login.succeeded';

    public const AUTH_LOGIN_FAILED = 'auth.login.failed';

    public const AUTH_LOGOUT = 'auth.logout';

    public const AUTH_PASSWORD_RESET_REQUESTED = 'auth.password_reset.requested';

    public const AUTH_PASSWORD_RESET_COMPLETED = 'auth.password_reset.completed';

    public const AUTH_SESSION_REVOKED = 'auth.session.revoked';

    public const AUTH_SESSION_REVOKED_OTHERS = 'auth.session.revoked_others';

    // --- Tenant scope -----------------------------------------------------
    public const TENANT_CONTEXT_SWITCHED = 'tenant.context.switched';

    public const OUTLET_CONTEXT_SWITCHED = 'outlet.context.switched';

    public const MEMBERSHIP_CREATED = 'membership.created';

    public const MEMBERSHIP_SUSPENDED = 'membership.suspended';

    public const MEMBERSHIP_REVOKED = 'membership.revoked';

    public const MEMBERSHIP_ROLE_ASSIGNED = 'membership.role.assigned';

    public const MEMBERSHIP_ROLE_REMOVED = 'membership.role.removed';

    public const DEVICE_SESSION_REVOKED = 'device_session.revoked';

    // --- Step 4: staff assigned to outlet master data (DEC-0031 A) --------
    //
    // Distinct from the MEMBERSHIP_ROLE_* actions above, because they answer
    // different questions. A role assignment changes what somebody MAY DO; an
    // outlet assignment changes WHERE they work and confers no capability at
    // all. Folding them into one action would make "who gained access in March"
    // unanswerable from the trail.

    public const STAFF_OUTLET_ASSIGNED = 'staff.outlet.assigned';

    public const STAFF_OUTLET_REVOKED = 'staff.outlet.revoked';

    /**
     * Reason codes for a failed login.
     *
     * CRITICAL: these are recorded in the audit trail for the OPERATOR, and are
     * NEVER returned to the client. The client receives one generic failure for
     * every cause, because telling a caller "no such account" versus "wrong
     * password" hands them a user-enumeration oracle (Rule 21 — abuse case:
     * tenant/account enumeration).
     */
    public const FAILURE_UNKNOWN_IDENTIFIER = 'unknown_identifier';

    public const FAILURE_INVALID_PASSWORD = 'invalid_password';

    public const FAILURE_ACCOUNT_DISABLED = 'account_disabled';

    public const FAILURE_NO_PASSWORD_SET = 'no_password_set';

    public const FAILURE_RATE_LIMITED = 'rate_limited';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::AUTH_LOGIN_SUCCEEDED,
            self::AUTH_LOGIN_FAILED,
            self::AUTH_LOGOUT,
            self::AUTH_PASSWORD_RESET_REQUESTED,
            self::AUTH_PASSWORD_RESET_COMPLETED,
            self::AUTH_SESSION_REVOKED,
            self::AUTH_SESSION_REVOKED_OTHERS,
            self::TENANT_CONTEXT_SWITCHED,
            self::OUTLET_CONTEXT_SWITCHED,
            self::MEMBERSHIP_CREATED,
            self::MEMBERSHIP_SUSPENDED,
            self::MEMBERSHIP_REVOKED,
            self::MEMBERSHIP_ROLE_ASSIGNED,
            self::MEMBERSHIP_ROLE_REMOVED,
            self::DEVICE_SESSION_REVOKED,
            self::STAFF_OUTLET_ASSIGNED,
            self::STAFF_OUTLET_REVOKED,
        ];
    }
}
