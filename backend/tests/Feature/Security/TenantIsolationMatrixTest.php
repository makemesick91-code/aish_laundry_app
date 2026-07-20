<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Identity\Models\AccessToken;
use App\Modules\SharedKernel\Http\ApiResponse;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * MATRIX B — Tenant isolation, exercised over the real HTTP surface.
 *
 * Matrix A proved the DATABASE refuses a cross-tenant row. This matrix proves
 * the APPLICATION refuses a cross-tenant REQUEST — a different claim, because a
 * read path never writes a row and therefore never meets a foreign key. A
 * cross-tenant READ is exactly as fatal as a cross-tenant write (Rule 02,
 * hard rule 12) and it is structurally invisible to Matrix A.
 *
 * CONTROL / VIOLATION DISCIPLINE
 * ------------------------------
 * Every denial below is paired with the positive case built from the SAME
 * fixture: the same user, the same token, the same endpoint, the same payload
 * shape — varying only the tenant being reached for. Without that pairing a 403
 * proves nothing, because a 403 caused by a typo'd route, a missing role, or an
 * unrelated validation failure looks identical to a 403 caused by tenant
 * isolation. Each denial also asserts the SPECIFIC error code, never merely a
 * status class.
 *
 * Every value here is fictional (Rule 23).
 */
final class TenantIsolationMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    // =====================================================================
    // B1 / B2 / B3 — the base isolation triangle
    // =====================================================================

    public function test_b1_control_user_a_reaches_tenant_a(): void
    {
        $s = $this->scenario();

        $response = $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantA']->id));

        $response->assertOk();
        $this->assertSame($s['tenantA']->id, $response->json('data.membership.tenant_id'));
    }

    public function test_b2_user_a_cannot_reach_tenant_b(): void
    {
        $s = $this->scenario();

        // Identical request to B1. Only the tenant reached for differs.
        $response = $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantB']->id));

        $response->assertStatus(403)->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    public function test_b3_user_b_cannot_reach_tenant_a(): void
    {
        $s = $this->scenario();

        // The mirror of B2. Isolation that only holds in one direction is not
        // isolation; it is an accident of which fixture was built first.
        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenB'], $s['tenantA']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');

        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenB'], $s['tenantB']->id))
            ->assertOk();
    }

    // =====================================================================
    // B4 — an owner of two tenants reaches each EXPLICITLY, never both at once
    // =====================================================================

    public function test_b4_owner_of_both_tenants_reaches_each_one_explicitly(): void
    {
        $s = $this->scenario();
        $headersFor = fn (string $tenantId): array => $this->bearer($s['tokenOwnerAB'], $tenantId);

        $inA = $this->getJson('/api/v1/memberships/current', $headersFor($s['tenantA']->id))->assertOk();
        $inB = $this->getJson('/api/v1/memberships/current', $headersFor($s['tenantB']->id))->assertOk();

        $this->assertSame($s['tenantA']->id, $inA->json('data.membership.tenant_id'));
        $this->assertSame($s['tenantB']->id, $inB->json('data.membership.tenant_id'));

        // Legitimately belonging to two tenants must not produce a merged view.
        // The membership ids must be distinct records, not one record reused.
        $this->assertNotSame(
            $inA->json('data.membership.id'),
            $inB->json('data.membership.id'),
            'Two tenants must resolve to two distinct memberships. A shared membership id would mean the '
            .'tenant boundary was crossed to satisfy a portfolio view (Rule 02, hard rule 13).'
        );
    }

    // =====================================================================
    // B5 — a tenant switch changes CONTEXT, never IDENTITY
    // =====================================================================

    public function test_b5_tenant_switch_changes_context_not_identity(): void
    {
        $s = $this->scenario();

        $a = $this->postJson('/api/v1/context/tenant', ['tenant_id' => $s['tenantA']->id], $this->bearer($s['tokenOwnerAB']))->assertOk();
        $b = $this->postJson('/api/v1/context/tenant', ['tenant_id' => $s['tenantB']->id], $this->bearer($s['tokenOwnerAB']))->assertOk();

        // Identity is constant across the switch...
        $me = $this->getJson('/api/v1/auth/me', $this->bearer($s['tokenOwnerAB']))->assertOk();
        $this->assertSame($s['ownerAB']->id, $me->json('data.user.id'));

        // ...while tenant, membership and permissions are all recomputed.
        $this->assertNotSame($a->json('data.context.tenant.id'), $b->json('data.context.tenant.id'));
        $this->assertNotSame($a->json('data.context.membership.id'), $b->json('data.context.membership.id'));
        $this->assertNotEquals(
            $a->json('data.permissions'),
            $b->json('data.permissions'),
            'Permissions must be recomputed per tenant. Carrying tenant A permissions into tenant B is a '
            .'privilege-escalation path across the isolation boundary.'
        );
    }

    // =====================================================================
    // B6 / B7 — membership lifecycle blocks access
    // =====================================================================

    public function test_b6_revoked_membership_blocks_with_membership_revoked(): void
    {
        $s = $this->scenario();

        // Control first: the SAME user, SAME token, SAME endpoint succeeds
        // while the membership is active.
        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantA']->id))->assertOk();

        $s['membershipA']->markRevoked();

        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantA']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEMBERSHIP_REVOKED');
    }

    public function test_b7_suspended_membership_blocks_with_membership_suspended(): void
    {
        $s = $this->scenario();

        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantA']->id))->assertOk();

        $s['membershipA']->markSuspended();

        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantA']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEMBERSHIP_SUSPENDED');
    }

    // =====================================================================
    // B8 / B9 — cross-tenant outlet and brand
    // =====================================================================

    public function test_b8_cross_tenant_outlet_is_rejected(): void
    {
        $s = $this->scenario();

        // Control: an outlet of the ACTIVE tenant is selectable.
        $this->postJson('/api/v1/context/outlet', ['outlet_id' => $s['outletA']->id], $this->bearer($s['tokenA'], $s['tenantA']->id))
            ->assertOk();

        // Violation: same call, an outlet belonging to tenant B.
        $this->postJson('/api/v1/context/outlet', ['outlet_id' => $s['outletB']->id], $this->bearer($s['tokenA'], $s['tenantA']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'OUTLET_ACCESS_DENIED');
    }

    public function test_b9_cross_tenant_brand_never_appears_in_the_active_tenants_outlet_listing(): void
    {
        $s = $this->scenario();

        $response = $this->getJson('/api/v1/context/outlets', $this->bearer($s['tokenA'], $s['tenantA']->id))->assertOk();

        $brandIds = array_column($response->json('data.outlets'), 'laundry_brand_id');
        $outletIds = array_column($response->json('data.outlets'), 'id');

        // Control: tenant A's own outlet IS present, proving the listing works.
        $this->assertContains($s['outletA']->id, $outletIds, 'Control: the active tenant must see its own outlet.');

        // Violation: nothing belonging to tenant B leaks in.
        $this->assertNotContains($s['outletB']->id, $outletIds);
        $this->assertNotContains($s['brandB']->id, $brandIds, 'A brand of another tenant reached the listing — cross-tenant exposure.');
    }

    // =====================================================================
    // B10 / B11 — the tenant identifier is an UNTRUSTED HINT on every channel
    // =====================================================================

    public function test_b10_forged_tenant_header_is_rejected(): void
    {
        $s = $this->scenario();

        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], $s['tenantB']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');

        // A tenant id that exists nowhere is denied identically — the header is
        // never a lookup key that reveals whether a tenant exists.
        $this->getJson('/api/v1/memberships/current', $this->bearer($s['tokenA'], (string) Str::uuid()))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    public function test_b11_forged_tenant_route_parameter_is_rejected(): void
    {
        $s = $this->scenario();

        // Step 3 registers no route carrying a {tenant} segment, but
        // ResolveTenantContext DOES read `$request->route('tenant')`. That
        // channel is therefore live and must be adversarially tested before a
        // later Step registers such a route and inherits an untested path.
        Route::middleware(['auth.api', 'tenant.context'])
            ->get('/api/v1/_uji/tenant-route-param/{tenant}', fn (): mixed => ApiResponse::success([
                'reached' => true,
                'tenant_id' => app(\App\Modules\Tenancy\Context\TenantContext::class)->tenantId(),
            ]));

        // Control: the route param resolves for a tenant the caller belongs to.
        $control = $this->getJson('/api/v1/_uji/tenant-route-param/'.$s['tenantA']->id, $this->bearer($s['tokenA']));
        $control->assertOk();
        $this->assertSame($s['tenantA']->id, $control->json('data.tenant_id'), 'Control must actually reach the handler.');

        // Violation: the same channel, another tenant's id.
        $this->getJson('/api/v1/_uji/tenant-route-param/'.$s['tenantB']->id, $this->bearer($s['tokenA']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    public function test_b11b_forged_tenant_id_in_the_request_body_is_rejected(): void
    {
        $s = $this->scenario();

        // The body is the third channel ResolveTenantContext reads.
        $this->postJson('/api/v1/context/outlet', [
            'outlet_id' => $s['outletB']->id,
            'tenant_id' => $s['tenantB']->id,
        ], $this->bearer($s['tokenA']))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    // =====================================================================
    // B12 — IDOR: a cross-tenant id must not be distinguishable from a
    //       non-existent one
    // =====================================================================

    public function test_b12_cross_tenant_resource_id_is_rejected_without_an_existence_oracle(): void
    {
        $s = $this->scenario();
        $headers = $this->bearer($s['tokenA'], $s['tenantA']->id);

        $crossTenant = $this->postJson('/api/v1/context/outlet', ['outlet_id' => $s['outletB']->id], $headers);
        $nonExistent = $this->postJson('/api/v1/context/outlet', ['outlet_id' => (string) Str::uuid()], $headers);

        $crossTenant->assertStatus(403)->assertJsonPath('error.code', 'OUTLET_ACCESS_DENIED');
        $nonExistent->assertStatus(403)->assertJsonPath('error.code', 'OUTLET_ACCESS_DENIED');

        // A real record in another tenant and a record that never existed must
        // be INDISTINGUISHABLE. Any difference is an enumeration oracle telling
        // a competitor which outlet ids are real (Rule 32, hard rule 2).
        $this->assertSame(
            $this->comparableBody($crossTenant->json()),
            $this->comparableBody($nonExistent->json()),
            'Denial and absence differ across the tenant boundary — this is a cross-tenant existence oracle.'
        );
    }

    // =====================================================================
    // B13 — cross-tenant role assignment
    // =====================================================================

    public function test_b13_cross_tenant_role_assignment_is_rejected(): void
    {
        $s = $this->scenario();

        // Control: granting a tenant role WITHIN the tenant succeeds.
        $this->grantRole($s['membershipA'], PermissionRegistry::ROLE_CASHIER);
        $this->assertDatabaseHas('membership_role', [
            'tenant_id' => $s['tenantA']->id,
            'membership_id' => $s['membershipA']->id,
        ]);

        // Violation 1 — the STRUCTURAL boundary (proved in full by Matrix A4):
        // a role row claiming tenant A but pointing at a tenant B membership
        // cannot be stored at all.
        $roleId = DB::table('roles')->where('key', PermissionRegistry::ROLE_CASHIER)->value('id');
        $this->assertNotNull($roleId, 'Control: the role catalogue must be seeded.');

        $threw = false;
        try {
            DB::transaction(fn () => DB::table('membership_role')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $s['tenantA']->id,
                'membership_id' => $s['membershipB']->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Illuminate\Database\QueryException $e) {
            $threw = true;
            $this->assertSame('23503', (string) ($e->errorInfo[0] ?? $e->getCode()));
            $this->assertStringContainsString('membership_role_tenant_membership_foreign', $e->getMessage());
        }
        $this->assertTrue($threw, 'A cross-tenant role assignment was stored. Automatic NO-GO under Rule 02.');

        // Violation 2 — the CATALOGUE boundary: a PLATFORM role is not
        // reachable through a membership at all (DEC-0025 §8).
        $this->expectException(InvalidArgumentException::class);
        $this->grantRole($s['membershipA'], PermissionRegistry::ROLE_PLATFORM_SUPER_ADMIN);
    }

    // =====================================================================
    // B14 — cross-tenant / cross-user session access
    // =====================================================================

    public function test_b14_a_user_cannot_reach_another_users_session(): void
    {
        $s = $this->scenario();

        $sessionA = $this->loginSession($s['userA']);
        $tokenB = $s['tokenB'];

        // Control: user A can see their OWN session, and it is marked current.
        // Located by id rather than by list position: user A holds more than
        // one session here, and an index-based assertion would be asserting the
        // ordering rather than the ownership.
        $own = collect($this->getJson('/api/v1/sessions', $this->bearer($sessionA['token']))->assertOk()->json('data.sessions'))
            ->firstWhere('id', $sessionA['id']);

        $this->assertNotNull($own, 'Control: user A must see their own session in their own listing.');
        $this->assertTrue($own['is_current'], 'Control: the session the request authenticated with must be marked current.');

        // Violation: user B, holding a valid token of their own, targets user
        // A's session id directly.
        $this->deleteJson('/api/v1/sessions/'.$sessionA['id'], [], $this->bearer($tokenB))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // And the attempt changed nothing: A's session is still usable.
        $this->getJson('/api/v1/auth/me', $this->bearer($sessionA['token']))->assertOk();
        $this->assertNull(
            AccessToken::query()->whereKey($sessionA['id'])->value('revoked_at'),
            'Another user revoked a session they do not own.'
        );
    }

    // =====================================================================
    // B15 — audit entries are tenant-bound
    // =====================================================================

    public function test_b15_audit_entries_written_by_tenant_a_activity_never_carry_tenant_b(): void
    {
        $s = $this->scenario();

        DB::table('audit_entries')->delete();

        $this->postJson('/api/v1/context/tenant', ['tenant_id' => $s['tenantA']->id], $this->bearer($s['tokenA']))->assertOk();

        $tenantIds = DB::table('audit_entries')->whereNotNull('tenant_id')->distinct()->pluck('tenant_id')->all();

        // Control: activity in tenant A produced at least one tenant-scoped
        // audit entry — otherwise this assertion would pass vacuously.
        $this->assertNotEmpty($tenantIds, 'No tenant-scoped audit entry was written; this matrix would prove nothing.');
        $this->assertSame([$s['tenantA']->id], $tenantIds);

        // A tenant-scoped read of tenant B's audit trail returns nothing.
        $this->assertSame(
            0,
            DB::table('audit_entries')->where('tenant_id', $s['tenantB']->id)->count()
        );
    }

    // =====================================================================
    // B16 — a stale tenant context is re-verified on the NEXT request
    // =====================================================================

    public function test_b16_stale_tenant_context_is_rejected_on_the_next_request(): void
    {
        $s = $this->scenario();
        $headers = $this->bearer($s['tokenA'], $s['tenantA']->id);

        $this->getJson('/api/v1/authorization/permissions', $headers)->assertOk();

        // The membership is revoked while the token remains valid and unused.
        Membership::query()->whereKey($s['membershipA']->id)
            ->update(['status' => Membership::STATUS_REVOKED]);

        // No re-login, no restart, no cache flush: the very next request must
        // already be denied. Authorization is recomputed per request or it is
        // not authorization.
        $this->getJson('/api/v1/authorization/permissions', $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MEMBERSHIP_REVOKED');
    }

    // =====================================================================
    // B17 — platform_support has NO tenant data access by default
    // =====================================================================

    public function test_b17_platform_support_gets_no_tenant_data_by_default(): void
    {
        $s = $this->scenario();

        // 1. It cannot be granted through a membership at all (DEC-0025 §9).
        $threw = false;
        try {
            $this->grantRole($s['membershipA'], PermissionRegistry::ROLE_PLATFORM_SUPPORT);
        } catch (InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'platform_support was assignable through a membership — silent tenant access by design.');

        // 2. Its catalogue entry carries no tenant-category permission.
        $supportPermissions = PermissionRegistry::matrix()[PermissionRegistry::ROLE_PLATFORM_SUPPORT];
        foreach ($supportPermissions as $permission) {
            $this->assertStringStartsWith(
                'platform.',
                $permission,
                sprintf('platform_support holds non-platform permission "%s" — that is tenant data access.', $permission)
            );
        }

        // 3. Even routed through the tenant permission calculator, a platform
        //    role yields nothing.
        $this->assertSame(
            [],
            PermissionRegistry::permissionsForTenantRoles([PermissionRegistry::ROLE_PLATFORM_SUPPORT])
        );

        // 4. End to end: a membership holding no tenant role reaches no tenant
        //    data, while the SAME endpoint serves a properly-roled member.
        $bare = $this->makeMembership($s['tenantA'], $noRoleUser = $this->makeUser());
        $this->assertSame(Membership::STATUS_ACTIVE, $bare->status, 'Control: the membership must be active.');

        $this->getJson('/api/v1/context/outlets', $this->bearer($this->loginToken($noRoleUser), $s['tenantA']->id))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->getJson('/api/v1/context/outlets', $this->bearer($s['tokenA'], $s['tenantA']->id))->assertOk();
    }

    // =====================================================================
    // Fixture
    // =====================================================================

    /** @return array<string, mixed> */
    private function scenario(): array
    {
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $brandA = $this->makeBrand($tenantA);
        $brandB = $this->makeBrand($tenantB);

        $outletA = $this->makeOutlet($tenantA, $brandA);
        $outletB = $this->makeOutlet($tenantB, $brandB);

        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $ownerAB = $this->makeUser();

        $membershipA = $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $membershipB = $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $this->makeMembership($tenantA, $ownerAB, [PermissionRegistry::ROLE_TENANT_OWNER]);
        // Deliberately a DIFFERENT role in tenant B: the same human is not the
        // same principal in two tenants, and B5 depends on that being true.
        $this->makeMembership($tenantB, $ownerAB, [PermissionRegistry::ROLE_CASHIER]);

        return [
            'tenantA' => $tenantA, 'tenantB' => $tenantB,
            'brandA' => $brandA, 'brandB' => $brandB,
            'outletA' => $outletA, 'outletB' => $outletB,
            'userA' => $userA, 'userB' => $userB, 'ownerAB' => $ownerAB,
            'membershipA' => $membershipA, 'membershipB' => $membershipB,
            'tokenA' => $this->loginToken($userA),
            'tokenB' => $this->loginToken($userB),
            'tokenOwnerAB' => $this->loginToken($ownerAB),
        ];
    }

    /**
     * Strip the per-request correlation id, which legitimately differs between
     * any two responses and would mask a real difference in the payload.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function comparableBody(?array $body): array
    {
        $body ??= [];
        unset($body['meta']);

        return $body;
    }
}
