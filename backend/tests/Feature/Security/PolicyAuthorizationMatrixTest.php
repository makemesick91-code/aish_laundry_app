<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Authorization\Policies\AuditEntryPolicy;
use App\Modules\Authorization\Policies\DeviceSessionPolicy;
use App\Modules\Authorization\Policies\LaundryBrandPolicy;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\DeviceSession;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * MATRIX F — POLICY AUTHORIZATION, for the policies no suite previously covered.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * A first-party relationship analysis at 1044441 asserted "zero policies without
 * tests" and found three that had none: `DeviceSessionPolicy`,
 * `LaundryBrandPolicy` and `AuditEntryPolicy`. All three are REGISTERED in
 * AuthorizationServiceProvider, so they are live authorization surface — they
 * were simply never exercised, because no Step 3 route consults them yet.
 *
 * That is precisely the dangerous shape. An untested policy is not inert: Step 4
 * attaches endpoints to these models, and it would inherit an authorization rule
 * nothing had ever checked. The cost of finding a wrong rule then is a
 * cross-tenant exposure; the cost of finding it here is this file.
 *
 * WHAT IS ASSERTED, AND WHY EACH MATTERS
 * --------------------------------------
 *   1. NO CONTEXT => DENY. Every policy denies when consulted outside a resolved
 *      tenant context. A policy that falls back to "some tenant the user belongs
 *      to" will eventually pick the wrong one.
 *   2. FOREIGN RESOURCE => DENY, even when the caller holds the permission.
 *      Queries are already tenant-scoped, so a foreign row should never reach a
 *      policy — this asserts the defence in depth actually denies if one does.
 *   3. PERMISSION IS REQUIRED, not merely membership. Being inside a tenant is
 *      necessary and never sufficient (Rule 40).
 *   4. The DeviceSession SELF-SERVICE CARVE-OUT works in both directions: a user
 *      may always revoke their OWN session with only the baseline self
 *      permission, and may NOT touch anybody else's without the administrative
 *      permission. A carve-out that leaks is worse than no carve-out.
 *   5. A NULL-tenant (identity/platform scope) audit entry is NEVER visible to a
 *      tenant member, whatever permissions they hold. This is stated in
 *      AuditEntryPolicy's docblock and was, until now, asserted nowhere.
 *
 * Policies are invoked DIRECTLY rather than through `Gate`, because the subject
 * under test is the policy's own decision. Gate adds resolution and before-hooks
 * that would make a passing test ambiguous about which layer actually denied.
 *
 * Every value here is fictional (Rule 23).
 */
final class PolicyAuthorizationMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    /**
     * Bind a resolved tenant context, exactly as ResolveTenantContext would.
     *
     * Built from a REAL membership row, never hand-forged: TenantContext's
     * constructor rejects a membership belonging to another tenant, and routing
     * around that guard in a test would make the test prove less than it claims.
     */
    private function bindContext(Tenant $tenant, Membership $membership): void
    {
        $this->app->instance(TenantContext::class, new TenantContext($tenant, $membership));
    }

    private function clearContext(): void
    {
        $this->app->forgetInstance(TenantContext::class);
        // forgetInstance leaves the binding registered; the policies test
        // `app()->bound(...)`, so the binding itself must go.
        unset($this->app[TenantContext::class]);
    }

    private function makeDeviceSession(Tenant $tenant, Membership $membership, User $user): DeviceSession
    {
        return DeviceSession::query()->create([
            'tenant_id' => $tenant->id,
            'membership_id' => $membership->id,
            'user_id' => $user->id,
            'device_identifier' => 'perangkat-uji-'.Str::lower(Str::random(10)),
            'device_name' => 'Perangkat Uji Fiktif',
            'platform' => 'android',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'AishLaundryUji/1.0 (fiktif)',
            'last_seen_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
    }

    private function makeAuditEntry(?Tenant $tenant, User $user): AuditEntry
    {
        return AuditEntry::query()->create([
            'tenant_id' => $tenant?->id,
            'actor_user_id' => $user->id,
            'action' => 'uji.fiktif',
            'subject_type' => 'UjiFiktif',
            'subject_id' => (string) Str::uuid(),
        ]);
    }

    // =====================================================================
    // F1 — no tenant context means no permission, for every policy
    // =====================================================================

    public function test_f1_every_policy_denies_without_a_resolved_tenant_context(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $brand = $this->makeBrand($tenant);
        $session = $this->makeDeviceSession($tenant, $membership, $user);
        $entry = $this->makeAuditEntry($tenant, $user);

        $this->clearContext();

        $this->assertFalse((new DeviceSessionPolicy)->viewAny($user), 'DeviceSession viewAny leaked without context');
        $this->assertFalse((new DeviceSessionPolicy)->view($user, $session), 'DeviceSession view leaked without context');
        $this->assertFalse((new DeviceSessionPolicy)->revoke($user, $session), 'DeviceSession revoke leaked without context');
        $this->assertFalse((new LaundryBrandPolicy)->viewAny($user), 'Brand viewAny leaked without context');
        $this->assertFalse((new LaundryBrandPolicy)->view($user, $brand), 'Brand view leaked without context');
        $this->assertFalse((new LaundryBrandPolicy)->manage($user, $brand), 'Brand manage leaked without context');
        $this->assertFalse((new AuditEntryPolicy)->viewAny($user), 'Audit viewAny leaked without context');
        $this->assertFalse((new AuditEntryPolicy)->view($user, $entry), 'Audit view leaked without context');
    }

    // =====================================================================
    // F2 — a resource belonging to ANOTHER tenant is denied, with permission held
    // =====================================================================

    public function test_f2_a_foreign_tenants_resource_is_denied_even_to_an_owner(): void
    {
        $tenantA = $this->makeTenant('tenant-a-fiktif');
        $tenantB = $this->makeTenant('tenant-b-fiktif');

        $user = $this->makeUser();
        $membershipA = $this->makeMembership($tenantA, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        // An unrelated identity owning tenant B's records.
        $otherUser = $this->makeUser();
        $membershipB = $this->makeMembership($tenantB, $otherUser, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $foreignBrand = $this->makeBrand($tenantB);
        $foreignSession = $this->makeDeviceSession($tenantB, $membershipB, $otherUser);
        $foreignEntry = $this->makeAuditEntry($tenantB, $otherUser);

        // The caller is a full owner — in tenant A.
        $this->bindContext($tenantA, $membershipA);

        $this->assertFalse((new LaundryBrandPolicy)->view($user, $foreignBrand), 'cross-tenant brand read allowed');
        $this->assertFalse((new LaundryBrandPolicy)->manage($user, $foreignBrand), 'cross-tenant brand write allowed');
        $this->assertFalse((new DeviceSessionPolicy)->view($user, $foreignSession), 'cross-tenant session read allowed');
        $this->assertFalse((new DeviceSessionPolicy)->revoke($user, $foreignSession), 'cross-tenant session revoke allowed');
        $this->assertFalse((new AuditEntryPolicy)->view($user, $foreignEntry), 'cross-tenant audit read allowed');
    }

    // =====================================================================
    // F3 — permission is required, not merely membership
    // =====================================================================

    public function test_f3_membership_alone_does_not_authorise_brand_or_audit_access(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        // `customer` holds baseline only — the deliberately weakest tenant role.
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CUSTOMER]);
        $brand = $this->makeBrand($tenant);
        $entry = $this->makeAuditEntry($tenant, $user);

        $this->bindContext($tenant, $membership);

        $this->assertFalse((new LaundryBrandPolicy)->view($user, $brand), 'brand visible to a baseline role');
        $this->assertFalse((new LaundryBrandPolicy)->manage($user, $brand), 'brand manageable by a baseline role');
        $this->assertFalse((new AuditEntryPolicy)->viewAny($user), 'audit trail listable by a baseline role');
        $this->assertFalse((new AuditEntryPolicy)->view($user, $entry), 'audit entry readable by a baseline role');
    }

    public function test_f3b_a_role_holding_the_permission_is_allowed_in_its_own_tenant(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $brand = $this->makeBrand($tenant);
        $entry = $this->makeAuditEntry($tenant, $user);

        $this->bindContext($tenant, $membership);

        // Guard against a vacuous suite: if these denied too, every assertion
        // above would pass for the wrong reason.
        $this->assertTrue((new LaundryBrandPolicy)->view($user, $brand), 'owner denied its own brand');
        $this->assertTrue((new LaundryBrandPolicy)->manage($user, $brand), 'owner denied managing its own brand');
        $this->assertTrue((new AuditEntryPolicy)->view($user, $entry), 'owner denied its own audit entry');
    }

    // =====================================================================
    // F4 — the DeviceSession self-service carve-out, both directions
    // =====================================================================

    public function test_f4_a_user_may_always_revoke_their_own_device_session(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        // Baseline role: holds session.view.self / session.revoke.self and
        // explicitly NOT device_session.view / device_session.revoke.
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CUSTOMER]);
        $own = $this->makeDeviceSession($tenant, $membership, $user);

        $this->bindContext($tenant, $membership);

        $this->assertTrue((new DeviceSessionPolicy)->view($user, $own), 'user cannot see their own session');
        $this->assertTrue((new DeviceSessionPolicy)->revoke($user, $own), 'user cannot revoke their own session');

        // The carve-out must not widen into an administrative permission.
        $this->assertFalse(
            (new DeviceSessionPolicy)->viewAny($user),
            'the self-service carve-out leaked into listing every device session'
        );
    }

    public function test_f4b_the_carve_out_does_not_reach_another_users_session(): void
    {
        $tenant = $this->makeTenant();

        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CUSTOMER]);

        $colleague = $this->makeUser();
        $colleagueMembership = $this->makeMembership($tenant, $colleague, [PermissionRegistry::ROLE_CUSTOMER]);
        $theirs = $this->makeDeviceSession($tenant, $colleagueMembership, $colleague);

        $this->bindContext($tenant, $membership);

        // Same tenant, so tenant scoping alone does not deny this. Only the
        // administrative permission check does — which is the point.
        $this->assertFalse(
            (new DeviceSessionPolicy)->view($user, $theirs),
            'a baseline user read a colleague session in the same tenant'
        );
        $this->assertFalse(
            (new DeviceSessionPolicy)->revoke($user, $theirs),
            'a baseline user revoked a colleague session in the same tenant'
        );
    }

    public function test_f4c_an_administrative_role_may_act_on_another_users_session(): void
    {
        $tenant = $this->makeTenant();

        $admin = $this->makeUser();
        $adminMembership = $this->makeMembership($tenant, $admin, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $staff = $this->makeUser();
        $staffMembership = $this->makeMembership($tenant, $staff, [PermissionRegistry::ROLE_CUSTOMER]);
        $theirs = $this->makeDeviceSession($tenant, $staffMembership, $staff);

        $this->bindContext($tenant, $adminMembership);

        $this->assertTrue(
            (new DeviceSessionPolicy)->view($admin, $theirs),
            'an owner holding device_session.view was denied'
        );
        $this->assertTrue(
            (new DeviceSessionPolicy)->revoke($admin, $theirs),
            'an owner holding device_session.revoke was denied'
        );
    }

    // =====================================================================
    // F5 — a NULL-tenant audit entry is never visible to a tenant member
    // =====================================================================

    public function test_f5_identity_scoped_audit_entries_are_invisible_to_tenant_members(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        // tenant_id NULL — identity/platform scope, e.g. a login before any
        // tenant was selected.
        $identityScoped = $this->makeAuditEntry(null, $user);

        $this->bindContext($tenant, $membership);

        $this->assertFalse(
            (new AuditEntryPolicy)->view($user, $identityScoped),
            'a platform-scope audit entry was readable by a tenant member'
        );
    }

    // =====================================================================
    // F6 — the permissions these policies depend on actually exist
    // =====================================================================

    public function test_f6_every_permission_these_policies_reference_exists_in_the_registry(): void
    {
        $catalogue = array_keys(PermissionRegistry::permissions());

        foreach ([
            PermissionRegistry::DEVICE_SESSION_VIEW,
            PermissionRegistry::DEVICE_SESSION_REVOKE,
            PermissionRegistry::SESSION_VIEW_SELF,
            PermissionRegistry::SESSION_REVOKE_SELF,
            PermissionRegistry::BRAND_VIEW,
            PermissionRegistry::BRAND_MANAGE,
            PermissionRegistry::AUDIT_VIEW,
        ] as $permission) {
            $this->assertContains(
                $permission,
                $catalogue,
                "policy references {$permission}, which is absent from the registry"
            );
        }
    }
}
