<?php

declare(strict_types=1);

namespace App\Modules\Authorization;

use InvalidArgumentException;

/**
 * THE CANONICAL PERMISSION REGISTRY — the single source of truth for RBAC.
 *
 * Backed by DEC-0025 (Platform-Managed Role Catalogues and Tenant-Scoped
 * Authorization). Read that decision before changing anything here.
 *
 * WHY A PHP CONSTANT TABLE AND NOT DATABASE ROWS AS THE SOURCE
 * -----------------------------------------------------------
 * DEC-0025 §1: "`roles` and `permissions` are canonical platform-managed
 * catalogues. Their contents are defined by the platform, not by tenants, and
 * are seeded and versioned as part of the application." The database tables are
 * a PROJECTION of this file, written by the seeder. This file is what gets code
 * review, what the permission matrix is generated from, and what tests assert
 * against. If the two ever disagree, this file is right and the database is
 * stale — re-seed it.
 *
 * THE TWO CATEGORIES ARE NOT INTERCHANGEABLE
 * ------------------------------------------
 * DEC-0025 §8: a platform role is NOT assignable through `membership_role`, and
 * a tenant role confers no platform capability. `assertAssignableToMembership()`
 * enforces the first half at the application boundary; the second half is
 * structural, because platform permissions are simply absent from every tenant
 * role's grant list.
 *
 * DEC-0025 §9: Platform Support defaults to NO tenant-data access. Note below
 * that `platform_support` holds exactly one permission, and it concerns the role
 * catalogue — not any tenant's data. A platform role that could read tenant data
 * by default would be the silent back door Rule 02 exists to prevent.
 *
 * DEC-0025 §10/§11: tenant-defined custom roles are DEFERRED and NOT
 * IMPLEMENTED. There is no code path here that creates a role at runtime.
 */
final class PermissionRegistry
{
    // ---------------------------------------------------------------------
    // PERMISSION KEYS — Step 3 resources only.
    //
    // Step 3 delivers runtime, authentication, multi-tenancy and RBAC. It
    // delivers NO business feature. There is deliberately no permission here
    // for a customer, a service, a price list, an order, a payment, a
    // delivery, or a report: those belong to Step 4 and beyond, and adding
    // their permissions early would be scope leakage (Rule 03 of the roadmap
    // lock, CLAUDE.md §3).
    // ---------------------------------------------------------------------

    /** Read the active tenant context and the caller's own tenant list. */
    public const TENANT_VIEW = 'tenant.view';

    /** Change the active tenant for the current session. */
    public const TENANT_SWITCH = 'tenant.switch';

    /** Read laundry brands belonging to the active tenant. */
    public const BRAND_VIEW = 'brand.view';

    /** Create or modify laundry brands in the active tenant. */
    public const BRAND_MANAGE = 'brand.manage';

    /** Read outlets belonging to the active tenant. */
    public const OUTLET_VIEW = 'outlet.view';

    /** Change the active outlet within the active tenant. */
    public const OUTLET_SWITCH = 'outlet.switch';

    /** Create or modify outlets in the active tenant. */
    public const OUTLET_MANAGE = 'outlet.manage';

    /** Read memberships of the active tenant. */
    public const MEMBERSHIP_VIEW = 'membership.view';

    /** Invite a user into the active tenant. */
    public const MEMBERSHIP_INVITE = 'membership.invite';

    /** Suspend an existing membership in the active tenant. */
    public const MEMBERSHIP_SUSPEND = 'membership.suspend';

    /** Revoke an existing membership in the active tenant. */
    public const MEMBERSHIP_REVOKE = 'membership.revoke';

    /** Assign a TENANT role to a membership in the active tenant. */
    public const MEMBERSHIP_ROLE_ASSIGN = 'membership.role.assign';

    /** Remove a TENANT role from a membership in the active tenant. */
    public const MEMBERSHIP_ROLE_REMOVE = 'membership.role.remove';

    /** List and revoke one's OWN authenticated sessions. Self-service, always granted. */
    public const SESSION_VIEW_SELF = 'session.view.self';

    public const SESSION_REVOKE_SELF = 'session.revoke.self';

    /** Read device sessions of the active tenant (other users included). */
    public const DEVICE_SESSION_VIEW = 'device_session.view';

    /** Revoke another user's device session within the active tenant. */
    public const DEVICE_SESSION_REVOKE = 'device_session.revoke';

    /** Inspect one's own effective permissions in the active tenant. Always granted. */
    public const PERMISSION_INSPECT = 'authorization.permission.inspect';

    /** Read the audit trail of the active tenant. */
    public const AUDIT_VIEW = 'audit.view';

    // ---------------------------------------------------------------------
    // STEP 4 — LAUNDRY MASTER DATA (DEC-0028, DEC-0030).
    //
    // These extend this registry; they do NOT create a second one. DEC-0031 A
    // is explicit that Step 4 consumes the existing authorization source of
    // truth rather than introducing a parallel RBAC system.
    //
    // Still deliberately absent: any permission for an order, a payment, a
    // receipt, production, tracking, a delivery, a reminder, or a subscription.
    // Those are Step 5+ and adding them early is scope leakage (Rule 36).
    // ---------------------------------------------------------------------

    /** Read customer master data within the active tenant. */
    public const CUSTOMER_VIEW = 'customer.view';

    /** Create, update, and archive customers, contacts, and addresses. */
    public const CUSTOMER_MANAGE = 'customer.manage';

    /**
     * Record or withdraw marketing consent for a customer.
     *
     * Separate from CUSTOMER_MANAGE on purpose. Consent is a legal obligation
     * with its own audit trail (Rule 08, FR-027); editing a customer's name and
     * changing what they agreed to receive are not the same act.
     */
    public const CUSTOMER_CONSENT_MANAGE = 'customer.consent.manage';

    /** Read the service catalogue of the active tenant. */
    public const SERVICE_VIEW = 'service.view';

    /** Create, update, and deactivate services, packages, and add-ons. */
    public const SERVICE_MANAGE = 'service.manage';

    /** Read price lists of the active tenant. */
    public const PRICE_LIST_VIEW = 'price_list.view';

    /** Create and edit DRAFT price lists. Never edits a published one. */
    public const PRICE_LIST_MANAGE = 'price_list.manage';

    /**
     * Publish a price list, freezing it permanently.
     *
     * Separate from PRICE_LIST_MANAGE because publishing is a commercial act
     * with an irreversible effect: a published version is immutable and becomes
     * the price customers are charged (FR-035, Rule 04).
     */
    public const PRICE_LIST_PUBLISH = 'price_list.publish';

    /**
     * Override a price on an order, with a recorded reason (FR-039).
     *
     * REGISTERED AS A CONTRACT ONLY. The override flow acts on an order, and
     * orders are Step 5. Step 4 defines the permission and the mandatory-reason
     * obligation so Step 5 inherits them rather than retrofitting them after a
     * financial control point has already shipped (DEC-0031 B).
     */
    public const PRICE_OVERRIDE = 'price.override';

    // --- Step 5: orders (FR-048 … FR-060) --------------------------------
    public const ORDER_VIEW = 'order.view';

    public const ORDER_CREATE = 'order.create';

    // Edit a DRAFT order before it is placed. Necessary but not sufficient:
    // OrderRegistry refuses a write against a placed or terminal order whatever
    // permission the caller holds.
    public const ORDER_MANAGE = 'order.manage';

    // Separate from ORDER_MANAGE because a cancellation is a control point that
    // carries a mandatory reason and an actor (FR-058), the same reasoning that
    // keeps PRICE_LIST_PUBLISH separate from PRICE_LIST_MANAGE.
    public const ORDER_CANCEL = 'order.cancel';

    // --- Step 5: payments (FR-061 … FR-069) ------------------------------
    public const PAYMENT_VIEW = 'payment.view';

    public const PAYMENT_RECORD = 'payment.record';

    // Refund/void is a financial control point (FR-065), separated from recording
    // and withheld from the admin deputy exactly as PRICE_OVERRIDE is.
    public const PAYMENT_REFUND = 'payment.refund';

    /** Manage outlet master data: hours, capacity, zones, shifts, printers. */
    public const OUTLET_MASTER_DATA_MANAGE = 'outlet.master_data.manage';

    /** Assign a membership to an outlet within the active tenant. */
    public const STAFF_ASSIGNMENT_MANAGE = 'staff.assignment.manage';

    // --- Platform-category permissions. Never reachable through a tenant role. ---

    /** Read the platform-managed role/permission catalogue. Carries no tenant data. */
    public const PLATFORM_ROLE_CATALOGUE_VIEW = 'platform.role_catalogue.view';

    /** Read the tenant registry at platform level (names and status only). */
    public const PLATFORM_TENANT_VIEW = 'platform.tenant.view';

    /** Read platform-scope audit entries. NOT tenant audit entries. */
    public const PLATFORM_AUDIT_VIEW = 'platform.audit.view';

    // ---------------------------------------------------------------------
    // ROLE KEYS
    // ---------------------------------------------------------------------

    public const ROLE_TENANT_OWNER = 'tenant_owner';

    public const ROLE_TENANT_ADMIN = 'tenant_admin';

    public const ROLE_OUTLET_MANAGER = 'outlet_manager';

    public const ROLE_CASHIER = 'cashier';

    public const ROLE_PRODUCTION_OPERATOR = 'production_operator';

    public const ROLE_QUALITY_CONTROL = 'quality_control';

    public const ROLE_COURIER = 'courier';

    public const ROLE_FINANCE = 'finance';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_PLATFORM_SUPER_ADMIN = 'platform_super_admin';

    public const ROLE_PLATFORM_SUPPORT = 'platform_support';

    public const CATEGORY_TENANT = 'tenant';

    public const CATEGORY_PLATFORM = 'platform';

    /**
     * Every permission with its human description and its category.
     *
     * @return array<string, array{description: string, category: string}>
     */
    public static function permissions(): array
    {
        return [
            self::TENANT_VIEW => ['description' => 'Melihat konteks tenant aktif dan daftar tenant sendiri', 'category' => self::CATEGORY_TENANT],
            self::TENANT_SWITCH => ['description' => 'Mengganti tenant aktif pada sesi berjalan', 'category' => self::CATEGORY_TENANT],
            self::BRAND_VIEW => ['description' => 'Melihat brand laundry milik tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::BRAND_MANAGE => ['description' => 'Mengelola brand laundry milik tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::OUTLET_VIEW => ['description' => 'Melihat outlet milik tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::OUTLET_SWITCH => ['description' => 'Mengganti outlet aktif dalam tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::OUTLET_MANAGE => ['description' => 'Mengelola outlet milik tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_VIEW => ['description' => 'Melihat keanggotaan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_INVITE => ['description' => 'Mengundang pengguna ke tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_SUSPEND => ['description' => 'Menangguhkan keanggotaan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_REVOKE => ['description' => 'Mencabut keanggotaan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_ROLE_ASSIGN => ['description' => 'Memberikan peran tenant kepada keanggotaan', 'category' => self::CATEGORY_TENANT],
            self::MEMBERSHIP_ROLE_REMOVE => ['description' => 'Mencabut peran tenant dari keanggotaan', 'category' => self::CATEGORY_TENANT],
            self::SESSION_VIEW_SELF => ['description' => 'Melihat sesi milik sendiri', 'category' => self::CATEGORY_TENANT],
            self::SESSION_REVOKE_SELF => ['description' => 'Mencabut sesi milik sendiri', 'category' => self::CATEGORY_TENANT],
            self::DEVICE_SESSION_VIEW => ['description' => 'Melihat sesi perangkat pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::DEVICE_SESSION_REVOKE => ['description' => 'Mencabut sesi perangkat pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::PERMISSION_INSPECT => ['description' => 'Melihat izin efektif milik sendiri pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::AUDIT_VIEW => ['description' => 'Membaca jejak audit tenant aktif', 'category' => self::CATEGORY_TENANT],

            // --- Step 4 master data (DEC-0028, DEC-0030) ---
            self::CUSTOMER_VIEW => ['description' => 'Melihat data pelanggan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::CUSTOMER_MANAGE => ['description' => 'Mengelola pelanggan, kontak, dan alamat pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::CUSTOMER_CONSENT_MANAGE => ['description' => 'Mencatat atau menarik persetujuan pemasaran pelanggan', 'category' => self::CATEGORY_TENANT],
            self::SERVICE_VIEW => ['description' => 'Melihat katalog layanan tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::SERVICE_MANAGE => ['description' => 'Mengelola layanan, paket, dan tambahan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::PRICE_LIST_VIEW => ['description' => 'Melihat daftar harga tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::PRICE_LIST_MANAGE => ['description' => 'Mengelola daftar harga berstatus draf', 'category' => self::CATEGORY_TENANT],
            self::PRICE_LIST_PUBLISH => ['description' => 'Menerbitkan daftar harga sehingga menjadi permanen', 'category' => self::CATEGORY_TENANT],
            self::PRICE_OVERRIDE => ['description' => 'Mengubah harga pada pesanan dengan alasan tercatat', 'category' => self::CATEGORY_TENANT],
            self::ORDER_VIEW => ['description' => 'Melihat pesanan pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::ORDER_CREATE => ['description' => 'Membuat pesanan baru pada outlet aktif', 'category' => self::CATEGORY_TENANT],
            self::ORDER_MANAGE => ['description' => 'Mengubah pesanan draf sebelum diterima', 'category' => self::CATEGORY_TENANT],
            self::ORDER_CANCEL => ['description' => 'Membatalkan pesanan dengan alasan tercatat', 'category' => self::CATEGORY_TENANT],
            self::PAYMENT_VIEW => ['description' => 'Melihat pembayaran pada tenant aktif', 'category' => self::CATEGORY_TENANT],
            self::PAYMENT_RECORD => ['description' => 'Mencatat pembayaran pada pesanan', 'category' => self::CATEGORY_TENANT],
            self::PAYMENT_REFUND => ['description' => 'Membalik atau mengembalikan pembayaran dengan alasan tercatat', 'category' => self::CATEGORY_TENANT],
            self::OUTLET_MASTER_DATA_MANAGE => ['description' => 'Mengelola data induk outlet: jam, kapasitas, zona, shift, printer', 'category' => self::CATEGORY_TENANT],
            self::STAFF_ASSIGNMENT_MANAGE => ['description' => 'Menugaskan keanggotaan ke outlet pada tenant aktif', 'category' => self::CATEGORY_TENANT],

            self::PLATFORM_ROLE_CATALOGUE_VIEW => ['description' => 'Membaca katalog peran dan izin platform', 'category' => self::CATEGORY_PLATFORM],
            self::PLATFORM_TENANT_VIEW => ['description' => 'Membaca daftar tenant pada tingkat platform', 'category' => self::CATEGORY_PLATFORM],
            self::PLATFORM_AUDIT_VIEW => ['description' => 'Membaca jejak audit lingkup platform', 'category' => self::CATEGORY_PLATFORM],
        ];
    }

    /**
     * Permissions every authenticated member holds in a tenant they are ACTIVE
     * in, regardless of role.
     *
     * These are strictly self-service: see your own context, see your own
     * sessions, revoke your own sessions, inspect your own permissions. They
     * grant visibility of nothing belonging to anybody else, so granting them
     * unconditionally widens no attack surface — while withholding them would
     * make a member unable to sign themselves out.
     *
     * @return list<string>
     */
    public static function baselineTenantPermissions(): array
    {
        return [
            self::TENANT_VIEW,
            self::TENANT_SWITCH,
            self::SESSION_VIEW_SELF,
            self::SESSION_REVOKE_SELF,
            self::PERMISSION_INSPECT,
        ];
    }

    /**
     * ROLE -> PERMISSION mapping. The authorization matrix, in one table.
     *
     * @return array<string, array{description: string, category: string, permissions: list<string>}>
     */
    public static function roles(): array
    {
        $baseline = self::baselineTenantPermissions();

        $ownerPermissions = [
            self::BRAND_VIEW,
            self::BRAND_MANAGE,
            self::OUTLET_VIEW,
            self::OUTLET_SWITCH,
            self::OUTLET_MANAGE,
            self::MEMBERSHIP_VIEW,
            self::MEMBERSHIP_INVITE,
            self::MEMBERSHIP_SUSPEND,
            self::MEMBERSHIP_REVOKE,
            self::MEMBERSHIP_ROLE_ASSIGN,
            self::MEMBERSHIP_ROLE_REMOVE,
            self::DEVICE_SESSION_VIEW,
            self::DEVICE_SESSION_REVOKE,
            self::AUDIT_VIEW,

            // Step 4 master data.
            self::CUSTOMER_VIEW,
            self::CUSTOMER_MANAGE,
            self::CUSTOMER_CONSENT_MANAGE,
            self::SERVICE_VIEW,
            self::SERVICE_MANAGE,
            self::PRICE_LIST_VIEW,
            self::PRICE_LIST_MANAGE,
            self::PRICE_LIST_PUBLISH,
            self::PRICE_OVERRIDE,
            self::OUTLET_MASTER_DATA_MANAGE,
            self::STAFF_ASSIGNMENT_MANAGE,

            // Step 5 orders. Operational, so the admin deputy inherits them; the
            // financial control (PRICE_OVERRIDE) stays owner-only, above.
            self::ORDER_VIEW,
            self::ORDER_CREATE,
            self::ORDER_MANAGE,
            self::ORDER_CANCEL,

            // Step 5 payments. Recording is operational; PAYMENT_REFUND is a
            // financial control point withheld from the admin deputy (below).
            self::PAYMENT_VIEW,
            self::PAYMENT_RECORD,
            self::PAYMENT_REFUND,
        ];

        // The admin is an operational deputy, not a co-owner. Two capabilities
        // are deliberately withheld: revoking a membership outright, and
        // managing brands (a commercial-identity change). Both are owner acts.
        //
        // Step 4 adds a third withheld capability: PRICE_OVERRIDE. Overriding a
        // price is a financial control point (FR-039, Rule 04), and the same
        // reasoning that keeps BRAND_MANAGE with the owner applies to it.
        $adminPermissions = array_values(array_diff($ownerPermissions, [
            self::MEMBERSHIP_REVOKE,
            self::BRAND_MANAGE,
            self::PRICE_OVERRIDE,
            // Refund/void is a financial control point (FR-065), the same class
            // as PRICE_OVERRIDE. The admin records payments but does not reverse
            // them.
            self::PAYMENT_REFUND,
        ]));

        return [
            self::ROLE_TENANT_OWNER => [
                'description' => 'Pemilik tenant — wewenang penuh pada tenant',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, $ownerPermissions),
            ],
            self::ROLE_TENANT_ADMIN => [
                'description' => 'Admin tenant — pengelolaan operasional tenant',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, $adminPermissions),
            ],
            self::ROLE_OUTLET_MANAGER => [
                'description' => 'Manager outlet — pengelolaan satu atau beberapa outlet',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::BRAND_VIEW,
                    self::OUTLET_VIEW,
                    self::OUTLET_SWITCH,
                    self::MEMBERSHIP_VIEW,
                    self::DEVICE_SESSION_VIEW,
                    self::DEVICE_SESSION_REVOKE,
                    self::AUDIT_VIEW,

                    // Step 4. An outlet manager runs an outlet: they maintain
                    // its master data and its customers, and they read the
                    // catalogue and prices they operate under. They do NOT
                    // author the catalogue or publish prices — those are
                    // tenant-wide commercial acts (FR-034, FR-035).
                    self::CUSTOMER_VIEW,
                    self::CUSTOMER_MANAGE,
                    self::SERVICE_VIEW,
                    self::PRICE_LIST_VIEW,
                    self::OUTLET_MASTER_DATA_MANAGE,

                    // Step 5. A manager runs an outlet's counter and may cancel.
                    self::ORDER_VIEW,
                    self::ORDER_CREATE,
                    self::ORDER_MANAGE,
                    self::ORDER_CANCEL,
                    self::PAYMENT_VIEW,
                    self::PAYMENT_RECORD,
                    self::PAYMENT_REFUND,
                ]),
            ],
            self::ROLE_CASHIER => [
                'description' => 'Kasir — operasi konter pada outlet aktif',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::OUTLET_VIEW,
                    self::OUTLET_SWITCH,

                    // Step 4. The counter needs to find and register a customer
                    // and to read the prices it quotes (FR-023, FR-040). It
                    // does not manage consent, author the catalogue, or change
                    // a price — a kasir changing prices is the financial
                    // control point FR-039 exists to guard.
                    self::CUSTOMER_VIEW,
                    self::CUSTOMER_MANAGE,
                    self::SERVICE_VIEW,
                    self::PRICE_LIST_VIEW,

                    // Step 5. The counter creates and manages orders and may
                    // cancel a mistaken draft (with a recorded reason, FR-058).
                    // It does NOT hold PRICE_OVERRIDE — a kasir changing a price
                    // is the control point FR-039 guards.
                    self::ORDER_VIEW,
                    self::ORDER_CREATE,
                    self::ORDER_MANAGE,
                    self::ORDER_CANCEL,

                    // The counter takes payment but does NOT refund — a refund is
                    // the financial control point FR-065 guards.
                    self::PAYMENT_VIEW,
                    self::PAYMENT_RECORD,
                ]),
            ],
            self::ROLE_PRODUCTION_OPERATOR => [
                'description' => 'Operator produksi — pengerjaan cucian pada outlet aktif',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::OUTLET_VIEW,
                    self::OUTLET_SWITCH,
                ]),
            ],
            self::ROLE_QUALITY_CONTROL => [
                'description' => 'Quality control — pemeriksaan mutu pada outlet aktif',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::OUTLET_VIEW,
                    self::OUTLET_SWITCH,
                ]),
            ],
            self::ROLE_COURIER => [
                // No OUTLET_SWITCH: a courier works an assignment, and does not
                // roam the tenant's outlets. Rule 32 hard rule 11 — the courier
                // surface shows the minimum and offers no traversal path.
                'description' => 'Kurir — penjemputan dan pengantaran sesuai penugasan',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::OUTLET_VIEW,
                ]),
            ],
            self::ROLE_FINANCE => [
                'description' => 'Finance — pembacaan data keuangan tenant',
                'category' => self::CATEGORY_TENANT,
                'permissions' => self::merge($baseline, [
                    self::BRAND_VIEW,
                    self::OUTLET_VIEW,
                    self::OUTLET_SWITCH,
                    self::AUDIT_VIEW,

                    // Step 4. Finance reads prices and the catalogue they are
                    // attached to. Read only: authoring and publishing prices
                    // are separated from reading them so the role that reports
                    // on revenue is not the role that sets it.
                    self::SERVICE_VIEW,
                    self::PRICE_LIST_VIEW,

                    // Step 5. Finance READS orders for reconciliation; it does
                    // not create or cancel them. Finance owns refund/reversal
                    // (FR-065, FR-067) but does not take payment at the counter.
                    self::ORDER_VIEW,
                    self::PAYMENT_VIEW,
                    self::PAYMENT_REFUND,
                ]),
            ],
            self::ROLE_CUSTOMER => [
                // A customer's membership grants them their own context and
                // nothing about the tenant's operation.
                'description' => 'Pelanggan — akses mandiri terhadap data miliknya sendiri',
                'category' => self::CATEGORY_TENANT,
                'permissions' => $baseline,
            ],

            // --- PLATFORM CATEGORY (DEC-0025 §8, §9) -------------------------
            self::ROLE_PLATFORM_SUPER_ADMIN => [
                'description' => 'Platform super admin — administrasi platform, tanpa akses data tenant secara diam-diam',
                'category' => self::CATEGORY_PLATFORM,
                'permissions' => [
                    self::PLATFORM_ROLE_CATALOGUE_VIEW,
                    self::PLATFORM_TENANT_VIEW,
                    self::PLATFORM_AUDIT_VIEW,
                ],
            ],
            self::ROLE_PLATFORM_SUPPORT => [
                // DEC-0025 §9: Platform Support defaults to NO tenant-data
                // access. It holds one catalogue-reading permission and nothing
                // else. Any future support access to tenant data must be
                // explicit, time-bound, reason-bound and audited (Rule 03) — it
                // is NOT granted here and is NOT IMPLEMENTED in Step 3.
                'description' => 'Platform support — tanpa akses data tenant secara bawaan',
                'category' => self::CATEGORY_PLATFORM,
                'permissions' => [
                    self::PLATFORM_ROLE_CATALOGUE_VIEW,
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function roleKeys(): array
    {
        return array_keys(self::roles());
    }

    /** @return list<string> */
    public static function tenantRoleKeys(): array
    {
        return array_values(array_keys(array_filter(
            self::roles(),
            static fn (array $role): bool => $role['category'] === self::CATEGORY_TENANT
        )));
    }

    /** @return list<string> */
    public static function platformRoleKeys(): array
    {
        return array_values(array_keys(array_filter(
            self::roles(),
            static fn (array $role): bool => $role['category'] === self::CATEGORY_PLATFORM
        )));
    }

    public static function isTenantRole(string $roleKey): bool
    {
        return in_array($roleKey, self::tenantRoleKeys(), true);
    }

    public static function isPlatformRole(string $roleKey): bool
    {
        return in_array($roleKey, self::platformRoleKeys(), true);
    }

    /**
     * Permissions granted by a set of role keys.
     *
     * PLATFORM roles are IGNORED here even if one is somehow passed in: this
     * method answers "what may this membership do in this tenant", and DEC-0025
     * §8 says a platform role is not reachable through a membership. Silently
     * ignoring rather than throwing is correct at this layer because the throw
     * belongs at the WRITE boundary — see assertAssignableToMembership().
     *
     * @param  list<string>  $roleKeys
     * @return list<string>
     */
    public static function permissionsForTenantRoles(array $roleKeys): array
    {
        $roles = self::roles();
        $granted = [];

        foreach ($roleKeys as $key) {
            if (! isset($roles[$key])) {
                continue;
            }

            if ($roles[$key]['category'] !== self::CATEGORY_TENANT) {
                continue;
            }

            foreach ($roles[$key]['permissions'] as $permission) {
                $granted[$permission] = true;
            }
        }

        $result = array_keys($granted);
        sort($result);

        return $result;
    }

    /**
     * Guard for the assignment write path.
     *
     * DEC-0025 §8: "A platform role is not assignable through `membership_role`."
     * This is the application-layer half of that guarantee. The tenant-binding
     * half is structural — the composite foreign key
     * `membership_role_tenant_membership_foreign` rejects a cross-tenant
     * assignment in PostgreSQL regardless of what this code does.
     *
     * @throws InvalidArgumentException
     */
    public static function assertAssignableToMembership(string $roleKey): void
    {
        $roles = self::roles();

        if (! isset($roles[$roleKey])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown role "%s". Roles are a platform-managed catalogue; tenant-defined '
                .'custom roles are DEFERRED and NOT IMPLEMENTED in Step 3 (DEC-0025 §10).',
                $roleKey
            ));
        }

        if ($roles[$roleKey]['category'] !== self::CATEGORY_TENANT) {
            throw new InvalidArgumentException(sprintf(
                'Role "%s" is a PLATFORM role and is not assignable through membership_role '
                .'(DEC-0025 §8). Platform and tenant roles are separate categories and are '
                .'never interchangeable.',
                $roleKey
            ));
        }
    }

    /**
     * The full matrix, for the permission-inspection endpoint and for tests.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $matrix = [];

        foreach (self::roles() as $key => $role) {
            $matrix[$key] = $role['permissions'];
        }

        return $matrix;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private static function merge(array $a, array $b): array
    {
        $merged = array_values(array_unique(array_merge($a, $b)));
        sort($merged);

        return $merged;
    }
}
