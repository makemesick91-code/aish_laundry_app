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


    // --- Step 4: master-data writes (SEC-10) ------------------------------
    //
    // Every state-changing Step 4 command has its own action. They are NOT
    // collapsed into a generic `master_data.changed`, because the question an
    // auditor actually asks is "who changed a PRICE", not "who touched master
    // data" — and a generic action cannot be alerted on differently from a
    // routine one.
    //
    // Coverage is enforced against the live route inventory by
    // `Step04AuditCoverageTest`, so a new write route cannot be added without
    // either an action here or a deliberate, justified exemption.

    public const CUSTOMER_CREATED = 'customer.created';

    public const CUSTOMER_UPDATED = 'customer.updated';

    public const CUSTOMER_ARCHIVED = 'customer.archived';

    // Consent is append-only in the database (FR-028). The audit entry records
    // WHO recorded the change and when; the consent row itself remains the
    // authority on what was consented to.
    public const CUSTOMER_CONSENT_RECORDED = 'customer.consent.recorded';

    // FR-024. An address is RESTRICTED data, so the action is recorded and the
    // address itself never is.
    public const CUSTOMER_ADDRESS_CREATED = 'customer.address.created';

    public const CUSTOMER_ADDRESS_UPDATED = 'customer.address.updated';

    public const CUSTOMER_ADDRESS_ARCHIVED = 'customer.address.archived';

    public const CUSTOMER_ADDRESS_REACTIVATED = 'customer.address.reactivated';

    public const SERVICE_CREATED = 'service.created';

    public const SERVICE_UPDATED = 'service.updated';

    public const SERVICE_CATEGORY_CREATED = 'service_category.created';

    public const SERVICE_CATEGORY_UPDATED = 'service_category.updated';

    public const SERVICE_PACKAGE_CREATED = 'service_package.created';

    public const SERVICE_PACKAGE_UPDATED = 'service_package.updated';

    public const SERVICE_PACKAGE_ITEMS_REPLACED = 'service_package.items.replaced';

    public const SERVICE_ADDON_CREATED = 'service_addon.created';

    public const SERVICE_ADDON_UPDATED = 'service_addon.updated';

    // Price actions are separated from every other master-data action because
    // they are the ones a financial dispute reaches for (Rule 04).
    public const PRICE_LIST_CREATED = 'price_list.created';

    public const PRICE_LIST_PUBLISHED = 'price_list.published';

    public const PRICE_LIST_ITEM_ADDED = 'price_list.item.added';

    public const PRICE_LIST_ITEM_UPDATED = 'price_list.item.updated';

    public const PRICE_LIST_ITEM_REMOVED = 'price_list.item.removed';

    public const OUTLET_MASTER_DATA_UPDATED = 'outlet.master_data.updated';

    public const OUTLET_ZONE_CREATED = 'outlet.zone.created';

    public const OUTLET_ZONE_UPDATED = 'outlet.zone.updated';

    public const OUTLET_SHIFT_CREATED = 'outlet.shift.created';

    public const OUTLET_SHIFT_UPDATED = 'outlet.shift.updated';

    public const OUTLET_PRINTER_CREATED = 'outlet.printer.created';

    public const OUTLET_PRINTER_UPDATED = 'outlet.printer.updated';

    public const PROOF_POLICY_UPDATED = 'proof_policy.updated';

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
            self::CUSTOMER_CREATED,
            self::CUSTOMER_UPDATED,
            self::CUSTOMER_ARCHIVED,
            self::CUSTOMER_CONSENT_RECORDED,
            self::CUSTOMER_ADDRESS_CREATED,
            self::CUSTOMER_ADDRESS_UPDATED,
            self::CUSTOMER_ADDRESS_ARCHIVED,
            self::CUSTOMER_ADDRESS_REACTIVATED,
            self::SERVICE_CREATED,
            self::SERVICE_UPDATED,
            self::SERVICE_CATEGORY_CREATED,
            self::SERVICE_CATEGORY_UPDATED,
            self::SERVICE_PACKAGE_CREATED,
            self::SERVICE_PACKAGE_UPDATED,
            self::SERVICE_PACKAGE_ITEMS_REPLACED,
            self::SERVICE_ADDON_CREATED,
            self::SERVICE_ADDON_UPDATED,
            self::PRICE_LIST_CREATED,
            self::PRICE_LIST_PUBLISHED,
            self::PRICE_LIST_ITEM_ADDED,
            self::PRICE_LIST_ITEM_UPDATED,
            self::PRICE_LIST_ITEM_REMOVED,
            self::OUTLET_MASTER_DATA_UPDATED,
            self::OUTLET_ZONE_CREATED,
            self::OUTLET_ZONE_UPDATED,
            self::OUTLET_SHIFT_CREATED,
            self::OUTLET_SHIFT_UPDATED,
            self::OUTLET_PRINTER_CREATED,
            self::OUTLET_PRINTER_UPDATED,
            self::PROOF_POLICY_UPDATED,
        ];
    }
}
