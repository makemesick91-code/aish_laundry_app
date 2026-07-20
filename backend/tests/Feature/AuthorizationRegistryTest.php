<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Authorization\EffectivePermissions;
use App\Modules\Authorization\Models\Permission;
use App\Modules\Authorization\Models\Role;
use App\Modules\Authorization\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * RBAC per DEC-0025.
 *
 * The registry is the single source of truth; the tables are its projection.
 * These tests assert that the projection is faithful and that the two role
 * CATEGORIES stay separate.
 */
final class AuthorizationRegistryTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    public function test_the_seeded_catalogue_matches_the_registry_exactly(): void
    {
        // Equal in both directions: no registry permission missing from the
        // database, and no database permission absent from the registry.
        $this->assertEqualsCanonicalizing(
            array_keys(PermissionRegistry::permissions()),
            Permission::query()->pluck('key')->all(),
            'Katalog izin di basis data harus persis merupakan proyeksi dari registry.'
        );

        $this->assertEqualsCanonicalizing(
            PermissionRegistry::roleKeys(),
            Role::query()->pluck('key')->all(),
        );
    }

    public function test_every_role_grant_matches_the_registry(): void
    {
        foreach (PermissionRegistry::roles() as $roleKey => $definition) {
            $role = Role::query()->where('key', $roleKey)->firstOrFail();

            $this->assertEqualsCanonicalizing(
                $definition['permissions'],
                $role->permissions()->pluck('key')->all(),
                "Hibah peran {$roleKey} menyimpang dari registry."
            );
        }
    }

    public function test_the_eleven_canonical_roles_are_present_in_their_categories(): void
    {
        $this->assertEqualsCanonicalizing([
            PermissionRegistry::ROLE_TENANT_OWNER,
            PermissionRegistry::ROLE_TENANT_ADMIN,
            PermissionRegistry::ROLE_OUTLET_MANAGER,
            PermissionRegistry::ROLE_CASHIER,
            PermissionRegistry::ROLE_PRODUCTION_OPERATOR,
            PermissionRegistry::ROLE_QUALITY_CONTROL,
            PermissionRegistry::ROLE_COURIER,
            PermissionRegistry::ROLE_FINANCE,
            PermissionRegistry::ROLE_CUSTOMER,
        ], PermissionRegistry::tenantRoleKeys());

        $this->assertEqualsCanonicalizing([
            PermissionRegistry::ROLE_PLATFORM_SUPER_ADMIN,
            PermissionRegistry::ROLE_PLATFORM_SUPPORT,
        ], PermissionRegistry::platformRoleKeys());
    }

    public function test_a_platform_role_is_not_assignable_to_a_membership(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // DEC-0025 §8: a platform role is never reachable through membership_role.
        PermissionRegistry::assertAssignableToMembership(
            PermissionRegistry::ROLE_PLATFORM_SUPER_ADMIN
        );
    }

    public function test_platform_support_holds_no_tenant_data_permission(): void
    {
        $support = PermissionRegistry::roles()[PermissionRegistry::ROLE_PLATFORM_SUPPORT];

        // DEC-0025 §9: Platform Support defaults to NO tenant-data access. A
        // platform role that could read tenant data by default would be the
        // silent back door Rule 02 exists to prevent.
        foreach ($support['permissions'] as $permission) {
            $this->assertStringStartsWith(
                'platform.',
                $permission,
                'platform_support tidak boleh memegang izin bercakupan tenant.'
            );
        }
    }

    public function test_no_tenant_role_carries_a_platform_permission(): void
    {
        foreach (PermissionRegistry::tenantRoleKeys() as $roleKey) {
            $permissions = PermissionRegistry::roles()[$roleKey]['permissions'];

            foreach ($permissions as $permission) {
                $this->assertStringStartsNotWith(
                    'platform.',
                    $permission,
                    "Peran tenant {$roleKey} tidak boleh memberi kemampuan platform."
                );
            }
        }
    }

    public function test_permissions_are_recomputed_when_a_role_is_removed(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $token = $this->loginToken($user);

        $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonFragment(['roles' => [PermissionRegistry::ROLE_TENANT_OWNER]]);

        // Remove the assignment outright.
        DB::table('membership_role')->where('membership_id', $membership->id)->delete();

        // DEC-0025 §7: role removal invalidates the authorization immediately.
        // Nothing waits for a token to expire, because nothing durable cached it.
        $after = $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $tenant->id))
            ->assertOk();

        $this->assertSame([], $after->json('data.roles'));
        $this->assertSame([], $after->json('data.permissions'));
    }

    public function test_permissions_are_empty_once_a_membership_is_revoked(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        // DEC-0025 §6: membership revocation immediately invalidates access.
        $membership->markRevoked();

        $this->assertSame(
            [],
            app(EffectivePermissions::class)->forMembership($membership->fresh())
        );

        $token = $this->loginToken($user);

        $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $tenant->id))
            ->assertStatus(403);
    }

    public function test_a_role_string_in_the_request_body_is_ignored(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_COURIER]);

        $token = $this->loginToken($user);

        $response = $this->postJson('/api/v1/context/tenant', [
            'tenant_id' => $tenant->id,
            // A privilege claim from the client. It must be inert.
            'roles' => [PermissionRegistry::ROLE_TENANT_OWNER],
            'permissions' => [PermissionRegistry::MEMBERSHIP_REVOKE],
            'status' => 'active',
        ], $this->bearer($token))->assertOk();

        $this->assertSame([PermissionRegistry::ROLE_COURIER], $response->json('data.roles'));
        $this->assertNotContains(
            PermissionRegistry::MEMBERSHIP_REVOKE,
            $response->json('data.permissions')
        );
    }

    public function test_the_permissions_endpoint_returns_the_callers_own_effective_set(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CASHIER]);

        $token = $this->loginToken($user);

        $response = $this->getJson('/api/v1/authorization/permissions', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonStructure(['data' => ['tenant_id', 'membership_id', 'roles', 'permissions', 'catalogue', 'notice']]);

        // Baseline self-service is always present for an active member.
        $this->assertContains(PermissionRegistry::PERMISSION_INSPECT, $response->json('data.permissions'));

        // The catalogue describes only what the caller actually holds.
        foreach (array_keys($response->json('data.catalogue')) as $described) {
            $this->assertContains($described, $response->json('data.permissions'));
        }
    }

    public function test_the_matrix_covers_every_role(): void
    {
        $matrix = PermissionRegistry::matrix();

        $this->assertEqualsCanonicalizing(PermissionRegistry::roleKeys(), array_keys($matrix));
    }
}
