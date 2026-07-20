<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Authorization\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * Happy paths for tenant and outlet context.
 *
 * THE CENTRAL PROPERTY UNDER TEST: a client-supplied tenant id is a REQUEST,
 * never authorization. The server resolves the authenticated user, looks up an
 * ACTIVE membership for (user, tenant), and fails closed when there is not one.
 *
 * The full isolation matrix is a separate exercise; what is covered here is that
 * the intended path works and that the obvious closed doors are closed.
 */
final class TenantContextTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_a_user_lists_only_the_tenants_they_belong_to(): void
    {
        $mine = $this->makeTenant('melati');
        $theirs = $this->makeTenant('kenanga');

        $user = $this->makeUser();
        $this->makeMembership($mine, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $tenants = $this->getJson('/api/v1/context/tenants', $this->bearer($token))
            ->assertOk()
            ->json('data.tenants');

        $this->assertCount(1, $tenants);
        $this->assertSame($mine->id, $tenants[0]['tenant']['id']);
        $this->assertTrue($tenants[0]['membership']['selectable']);

        // This endpoint is not a tenant directory and must never become one.
        $ids = array_column(array_column($tenants, 'tenant'), 'id');
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_selecting_a_tenant_returns_the_context_with_recomputed_permissions(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $response = $this->postJson('/api/v1/context/tenant', [
            'tenant_id' => $tenant->id,
            'device_identifier' => 'perangkat-uji-001',
            'device_name' => 'Tablet Kasir Uji',
            'platform' => 'android',
        ], $this->bearer($token))->assertOk();

        $response->assertJsonPath('data.context.tenant.id', $tenant->id)
            ->assertJsonPath('data.context.membership.status', 'active')
            ->assertJsonPath('data.roles', [PermissionRegistry::ROLE_TENANT_OWNER]);

        $this->assertContains(PermissionRegistry::OUTLET_VIEW, $response->json('data.permissions'));

        $this->assertDatabaseHas('audit_entries', [
            'action' => AuditAction::TENANT_CONTEXT_SWITCHED,
            'tenant_id' => $tenant->id,
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_selecting_a_tenant_the_user_does_not_belong_to_is_denied(): void
    {
        $mine = $this->makeTenant('melati');
        $theirs = $this->makeTenant('kenanga');

        $user = $this->makeUser();
        $this->makeMembership($mine, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        // The submitted id is a request. Absent an active membership it is
        // refused — the id being real changes nothing.
        $this->postJson('/api/v1/context/tenant', [
            'tenant_id' => $theirs->id,
        ], $this->bearer($token))->assertStatus(403);
    }

    public function test_a_suspended_membership_cannot_select_its_tenant(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $membership->markSuspended();

        $this->postJson('/api/v1/context/tenant', [
            'tenant_id' => $tenant->id,
        ], $this->bearer($token))->assertStatus(403);
    }

    public function test_outlets_of_the_active_tenant_are_listed(): void
    {
        $tenant = $this->makeTenant();
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand, 'Outlet Pusat Fiktif');

        // A second tenant with its own outlet, which must not appear.
        $otherTenant = $this->makeTenant('kenanga');
        $this->makeOutlet($otherTenant);

        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $outlets = $this->getJson('/api/v1/context/outlets', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->json('data.outlets');

        $this->assertCount(1, $outlets);
        $this->assertSame($outlet->id, $outlets[0]['id']);
    }

    public function test_an_outlet_of_the_active_tenant_is_selected(): void
    {
        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);

        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $this->postJson('/api/v1/context/outlet', [
            'outlet_id' => $outlet->id,
        ], $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonPath('data.context.outlet.id', $outlet->id)
            ->assertJsonPath('data.context.tenant.id', $tenant->id);

        $this->assertDatabaseHas('audit_entries', [
            'action' => AuditAction::OUTLET_CONTEXT_SWITCHED,
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
        ]);
    }

    public function test_an_outlet_belonging_to_another_tenant_cannot_be_selected(): void
    {
        $tenant = $this->makeTenant('melati');
        $otherTenant = $this->makeTenant('kenanga');
        $foreignOutlet = $this->makeOutlet($otherTenant);

        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        // The same identity legitimately belongs to the other tenant too — the
        // outlet must still be unreachable while acting in this one.
        $this->makeMembership($otherTenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        // Scoped by the resolved tenant, so the row is simply not reachable. The
        // response says the outlet is unavailable in the ACTIVE tenant and does
        // not confirm that it exists in the other one.
        $response = $this->postJson('/api/v1/context/outlet', [
            'outlet_id' => $foreignOutlet->id,
        ], $this->bearer($token, $tenant->id))->assertStatus(403);

        $response->assertJsonPath('error.code', 'OUTLET_ACCESS_DENIED');
        $this->assertStringNotContainsString($otherTenant->id, (string) $response->getContent());
    }

    public function test_a_tenant_scoped_endpoint_requires_a_tenant(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        // Authenticated, but no tenant selected and none supplied.
        $this->getJson('/api/v1/context/outlets', $this->bearer($token))
            ->assertStatus(403);
    }

    public function test_current_membership_reports_the_callers_own_context(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CASHIER]);

        $token = $this->loginToken($user);

        $this->getJson('/api/v1/memberships/current', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonPath('data.membership.id', $membership->id)
            ->assertJsonPath('data.membership.tenant_id', $tenant->id)
            ->assertJsonPath('data.membership.status', 'active')
            ->assertJsonPath('data.roles', [PermissionRegistry::ROLE_CASHIER]);
    }

    public function test_the_same_identity_gets_different_permissions_in_each_tenant(): void
    {
        $melati = $this->makeTenant('melati');
        $kenanga = $this->makeTenant('kenanga');

        $user = $this->makeUser();
        $this->makeMembership($melati, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($kenanga, $user, [PermissionRegistry::ROLE_COURIER]);

        $token = $this->loginToken($user);

        $inMelati = $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $melati->id))
            ->assertOk();
        $inKenanga = $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $kenanga->id))
            ->assertOk();

        $this->assertContains(PermissionRegistry::MEMBERSHIP_REVOKE, $inMelati->json('data.permissions'));

        // A courier holds no membership-administration capability. Authorization
        // is a property of the membership, never of the account.
        $this->assertNotContains(PermissionRegistry::MEMBERSHIP_REVOKE, $inKenanga->json('data.permissions'));

        $this->assertSame([PermissionRegistry::ROLE_TENANT_OWNER], $inMelati->json('data.roles'));
        $this->assertSame([PermissionRegistry::ROLE_COURIER], $inKenanga->json('data.roles'));
    }
}
