<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * MATRIX C — Role-Based Access Control, GENERATED from PermissionRegistry.
 *
 * WHY GENERATED AND NOT HAND-WRITTEN
 * ----------------------------------
 * A hand-copied expectation table is a second source of truth. It drifts
 * silently the moment somebody widens a role, and — worse — it drifts in the
 * PERMISSIVE direction, because a test updated to match a broadened role passes
 * and nobody looks again. Every expectation below is therefore derived at
 * runtime from `PermissionRegistry::matrix()`, which is the same table the
 * application authorizes against.
 *
 * That raises an obvious objection: a test that reads its expectation from the
 * implementation cannot detect a wrong mapping. Correct. So this matrix is
 * built in two layers, and only the second layer is generated:
 *
 *   LAYER 1 — INVARIANTS (hand-written, deliberately independent).
 *             Named, non-negotiable properties that must hold no matter how the
 *             catalogue is edited: a tenant role never holds a platform
 *             permission, a cashier never holds role management, a customer
 *             holds nothing but baseline. These would FAIL if somebody widened
 *             a role, which is exactly the drift a generated test cannot see.
 *
 *   LAYER 2 — ENFORCEMENT (generated). For every role in the catalogue, the
 *             permissions the SERVER actually computes and serves must equal
 *             the catalogue exactly, and the real HTTP endpoints must allow or
 *             deny in agreement with it. This is what catches an enforcement
 *             path that ignores the table it claims to implement.
 *
 * Set equality is used throughout rather than "contains": it asserts the
 * ALLOWED and the DENIED half in a single assertion, because any permission not
 * in the expected set is thereby asserted absent.
 *
 * Every value here is fictional (Rule 23).
 */
final class RbacMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    // =====================================================================
    // LAYER 2 (generated) — the server computes exactly the catalogue
    // =====================================================================

    /**
     * For every TENANT role: the effective permission set served over HTTP must
     * equal the catalogue entry exactly — no extra permission (privilege
     * escalation), no missing permission (broken feature).
     */
    #[DataProvider('tenantRoleProvider')]
    public function test_c1_effective_permissions_equal_the_catalogue_for_each_tenant_role(string $roleKey): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [$roleKey]);

        $response = $this->getJson(
            '/api/v1/authorization/permissions',
            $this->bearer($this->loginToken($user), $tenant->id)
        )->assertOk();

        $expected = PermissionRegistry::matrix()[$roleKey];
        $actual = $response->json('data.permissions');

        sort($expected);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            sprintf(
                'Role "%s": the permissions the server serves differ from the catalogue it authorizes against. '
                .'Extra permissions are privilege escalation; missing ones are a broken role.',
                $roleKey
            )
        );

        $this->assertSame([$roleKey], $response->json('data.roles'));
    }

    /**
     * Endpoint enforcement must AGREE with the catalogue. The expectation is
     * computed from the registry, so this cannot be satisfied by editing a
     * hard-coded list.
     */
    #[DataProvider('tenantRoleProvider')]
    public function test_c2_outlet_endpoints_allow_or_deny_in_agreement_with_the_catalogue(string $roleKey): void
    {
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [$roleKey]);
        $headers = $this->bearer($this->loginToken($user), $tenant->id);

        $granted = PermissionRegistry::matrix()[$roleKey];

        // --- outlet.view -> GET /context/outlets ---
        $mayView = in_array(PermissionRegistry::OUTLET_VIEW, $granted, true);
        $viewResponse = $this->getJson('/api/v1/context/outlets', $headers);

        if ($mayView) {
            $viewResponse->assertOk();
        } else {
            $viewResponse->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        }

        // --- outlet.switch -> POST /context/outlet ---
        $maySwitch = in_array(PermissionRegistry::OUTLET_SWITCH, $granted, true);
        $switchResponse = $this->postJson('/api/v1/context/outlet', ['outlet_id' => $outlet->id], $headers);

        if ($maySwitch) {
            $switchResponse->assertOk();
        } else {
            $switchResponse->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    /**
     * Baseline self-service must be reachable by EVERY tenant role, including
     * the most restricted. A member who cannot inspect their own permissions or
     * see their own membership cannot sign themselves out either.
     */
    #[DataProvider('tenantRoleProvider')]
    public function test_c3_every_tenant_role_retains_baseline_self_service(string $roleKey): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [$roleKey]);
        $headers = $this->bearer($this->loginToken($user), $tenant->id);

        $this->getJson('/api/v1/memberships/current', $headers)->assertOk();
        $this->getJson('/api/v1/authorization/permissions', $headers)->assertOk();

        foreach (PermissionRegistry::baselineTenantPermissions() as $baseline) {
            $this->assertContains($baseline, PermissionRegistry::matrix()[$roleKey], sprintf(
                'Role "%s" lost baseline permission "%s".', $roleKey, $baseline
            ));
        }
    }

    /** @return list<array{string}> */
    public static function tenantRoleProvider(): array
    {
        return array_map(
            static fn (string $key): array => [$key],
            PermissionRegistry::tenantRoleKeys()
        );
    }

    // =====================================================================
    // LAYER 1 (independent invariants) — these catch catalogue drift
    // =====================================================================

    public function test_c4_no_tenant_role_holds_any_platform_permission(): void
    {
        foreach (PermissionRegistry::tenantRoleKeys() as $roleKey) {
            foreach (PermissionRegistry::matrix()[$roleKey] as $permission) {
                $this->assertStringStartsNotWith(
                    'platform.',
                    $permission,
                    sprintf(
                        'Tenant role "%s" holds platform permission "%s". Platform and tenant categories are '
                        .'never interchangeable (DEC-0025 §8).',
                        $roleKey,
                        $permission
                    )
                );
            }
        }
    }

    public function test_c5_tenant_owner_cannot_grant_platform_super_admin(): void
    {
        $tenant = $this->makeTenant();
        $owner = $this->makeUser();
        $ownerMembership = $this->makeMembership($tenant, $owner, [PermissionRegistry::ROLE_TENANT_OWNER]);

        // Control: the owner CAN grant a tenant role. Without this the failure
        // below could be caused by anything, including a broken fixture.
        $this->grantRole($ownerMembership, PermissionRegistry::ROLE_CASHIER);
        $this->assertContains(
            PermissionRegistry::ROLE_CASHIER,
            app(EffectivePermissions::class)->roleKeysForMembership($ownerMembership->fresh())
        );

        // Violation: the platform role is not assignable through a membership,
        // no matter who is asking. A tenant owner minting a platform super
        // admin would be a full platform takeover from inside one tenant.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PLATFORM role/');
        $this->grantRole($ownerMembership, PermissionRegistry::ROLE_PLATFORM_SUPER_ADMIN);
    }

    public function test_c6_tenant_admin_cannot_reach_another_tenant(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $this->makeOutlet($tenantA);
        $this->makeOutlet($tenantB);

        $admin = $this->makeUser();
        $this->makeMembership($tenantA, $admin, [PermissionRegistry::ROLE_TENANT_ADMIN]);
        $token = $this->loginToken($admin);

        // Control: full admin capability inside their own tenant.
        $this->getJson('/api/v1/context/outlets', $this->bearer($token, $tenantA->id))->assertOk();

        // Violation: a high tenant privilege is still bounded by the tenant.
        $this->getJson('/api/v1/context/outlets', $this->bearer($token, $tenantB->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    public function test_c7_outlet_manager_cannot_select_an_outlet_in_another_tenant(): void
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $outletA = $this->makeOutlet($tenantA);
        $outletB = $this->makeOutlet($tenantB);

        $manager = $this->makeUser();
        $this->makeMembership($tenantA, $manager, [PermissionRegistry::ROLE_OUTLET_MANAGER]);
        $headers = $this->bearer($this->loginToken($manager), $tenantA->id);

        // Control: holding outlet.switch, they can select their OWN outlet.
        $this->assertContains(
            PermissionRegistry::OUTLET_SWITCH,
            PermissionRegistry::matrix()[PermissionRegistry::ROLE_OUTLET_MANAGER]
        );
        $this->postJson('/api/v1/context/outlet', ['outlet_id' => $outletA->id], $headers)->assertOk();

        // Violation: holding the permission is not holding it everywhere.
        $this->postJson('/api/v1/context/outlet', ['outlet_id' => $outletB->id], $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'OUTLET_ACCESS_DENIED');
    }

    public function test_c8_cashier_has_no_role_management_permission(): void
    {
        $cashier = PermissionRegistry::matrix()[PermissionRegistry::ROLE_CASHIER];

        // Control: the cashier is a real, non-empty role.
        $this->assertNotEmpty($cashier);

        foreach ([
            PermissionRegistry::MEMBERSHIP_ROLE_ASSIGN,
            PermissionRegistry::MEMBERSHIP_ROLE_REMOVE,
            PermissionRegistry::MEMBERSHIP_INVITE,
            PermissionRegistry::MEMBERSHIP_SUSPEND,
            PermissionRegistry::MEMBERSHIP_REVOKE,
        ] as $permission) {
            $this->assertNotContains($permission, $cashier, sprintf(
                'A cashier holding "%s" could promote themselves at the counter.', $permission
            ));
        }
    }

    public function test_c9_production_operator_has_no_session_administration_permission(): void
    {
        $operator = PermissionRegistry::matrix()[PermissionRegistry::ROLE_PRODUCTION_OPERATOR];

        $this->assertNotEmpty($operator);

        foreach ([PermissionRegistry::DEVICE_SESSION_VIEW, PermissionRegistry::DEVICE_SESSION_REVOKE] as $permission) {
            $this->assertNotContains($permission, $operator, sprintf(
                'A production operator holding "%s" can see or terminate other staff members\' devices.', $permission
            ));
        }

        // Self-service session control is retained — the distinction that makes
        // this a scoping rule rather than a lockout.
        $this->assertContains(PermissionRegistry::SESSION_REVOKE_SELF, $operator);
    }

    public function test_c10_courier_has_no_broad_tenant_administration(): void
    {
        $courier = PermissionRegistry::matrix()[PermissionRegistry::ROLE_COURIER];

        $this->assertNotEmpty($courier);

        foreach ([
            PermissionRegistry::MEMBERSHIP_VIEW,
            PermissionRegistry::MEMBERSHIP_INVITE,
            PermissionRegistry::MEMBERSHIP_ROLE_ASSIGN,
            PermissionRegistry::BRAND_MANAGE,
            PermissionRegistry::OUTLET_MANAGE,
            PermissionRegistry::AUDIT_VIEW,
            PermissionRegistry::DEVICE_SESSION_VIEW,
        ] as $permission) {
            $this->assertNotContains($permission, $courier, sprintf(
                'The courier surface is deliberately minimal; "%s" widens it into tenant administration.', $permission
            ));
        }
    }

    public function test_c11_finance_has_no_platform_permission(): void
    {
        $finance = PermissionRegistry::matrix()[PermissionRegistry::ROLE_FINANCE];

        $this->assertNotEmpty($finance);

        foreach ($finance as $permission) {
            $this->assertStringStartsNotWith('platform.', $permission, sprintf(
                'Finance holds platform permission "%s" — a tenant role reaching platform scope.', $permission
            ));
        }
    }

    public function test_c12_customer_has_no_staff_permission(): void
    {
        $customer = PermissionRegistry::matrix()[PermissionRegistry::ROLE_CUSTOMER];

        // A customer holds baseline self-service and NOTHING else.
        $expected = PermissionRegistry::baselineTenantPermissions();
        sort($expected);
        $actual = $customer;
        sort($actual);

        $this->assertSame($expected, $actual, 'The customer role must carry baseline self-service only.');

        foreach ([
            PermissionRegistry::OUTLET_VIEW,
            PermissionRegistry::OUTLET_MANAGE,
            PermissionRegistry::BRAND_VIEW,
            PermissionRegistry::MEMBERSHIP_VIEW,
            PermissionRegistry::AUDIT_VIEW,
            PermissionRegistry::DEVICE_SESSION_VIEW,
        ] as $staffPermission) {
            $this->assertNotContains($staffPermission, $customer);
        }
    }

    public function test_c13_platform_support_has_no_silent_tenant_data_access(): void
    {
        $support = PermissionRegistry::matrix()[PermissionRegistry::ROLE_PLATFORM_SUPPORT];

        $this->assertNotEmpty($support, 'Control: platform_support is a real catalogue entry.');

        foreach ($support as $permission) {
            $this->assertStringStartsWith('platform.', $permission);
        }

        // It is not reachable through a membership, so it cannot be used to
        // acquire tenant scope by the back door.
        $this->assertFalse(PermissionRegistry::isTenantRole(PermissionRegistry::ROLE_PLATFORM_SUPPORT));
        $this->assertSame([], PermissionRegistry::permissionsForTenantRoles([PermissionRegistry::ROLE_PLATFORM_SUPPORT]));

        $this->expectException(InvalidArgumentException::class);
        PermissionRegistry::assertAssignableToMembership(PermissionRegistry::ROLE_PLATFORM_SUPPORT);
    }

    // =====================================================================
    // C14 / C15 — revocation takes effect immediately
    // =====================================================================

    public function test_c14_a_removed_role_takes_effect_on_the_next_request(): void
    {
        $tenant = $this->makeTenant();
        $this->makeOutlet($tenant);
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_OUTLET_MANAGER]);

        // ONE token, issued once, reused throughout. No re-login anywhere below.
        $headers = $this->bearer($this->loginToken($user), $tenant->id);

        $this->getJson('/api/v1/context/outlets', $headers)->assertOk();

        $removed = DB::table('membership_role')
            ->where('tenant_id', $tenant->id)
            ->where('membership_id', $membership->id)
            ->delete();

        $this->assertSame(1, $removed, 'Control: exactly one role row must have been removed.');

        // The very next request — same process, same token, no restart, no
        // re-login, no cache flush — must already be denied.
        $this->getJson('/api/v1/context/outlets', $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame(
            [],
            $this->getJson('/api/v1/authorization/permissions', $headers)->assertOk()->json('data.roles')
        );
    }

    public function test_c15_a_revoked_membership_yields_zero_effective_tenant_permissions(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $effective = app(EffectivePermissions::class);

        // Control: the owner holds a large permission set while active.
        $this->assertNotEmpty($effective->forMembership($membership));

        $membership->markRevoked();
        $membership = $membership->fresh();

        $this->assertSame(Membership::STATUS_REVOKED, $membership->status);
        $this->assertSame([], $effective->forMembership($membership), 'A revoked membership must hold ZERO permissions.');
        $this->assertSame([], $effective->roleKeysForMembership($membership));

        // Even though the role ROW still exists — proving the zeroing is a
        // status decision, not a side effect of the row being deleted.
        $this->assertDatabaseHas('membership_role', ['membership_id' => $membership->id]);

        $this->getJson('/api/v1/authorization/permissions', $this->bearer($this->loginToken($user), $tenant->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEMBERSHIP_REVOKED');
    }

    // =====================================================================
    // C16 — the catalogue in the DATABASE matches the catalogue in CODE
    // =====================================================================

    public function test_c16_the_seeded_role_catalogue_matches_the_registry(): void
    {
        $seeded = DB::table('roles')->pluck('key')->all();
        $declared = PermissionRegistry::roleKeys();

        sort($seeded);
        sort($declared);

        $this->assertSame(
            $declared,
            $seeded,
            'The seeded role catalogue drifted from PermissionRegistry. Authorization would then depend on '
            .'which of the two a given code path happened to read.'
        );
    }
}
