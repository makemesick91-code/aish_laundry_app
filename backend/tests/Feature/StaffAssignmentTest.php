<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Organization\Models\MembershipOutlet;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STAFF AND ROLE ASSIGNMENT — ROADMAP Step 4 scope, FR-018, DEC-0031 A.
 *
 * The two acts this suite keeps apart throughout:
 *   - OUTLET assignment says WHERE somebody works and confers no capability;
 *   - ROLE assignment confers capability and passes the escalation guard.
 *
 * Runs against PostgreSQL, the only engine whose constraint and isolation
 * results count as evidence (Rule 43).
 *
 * Every value is fictional (Rule 23, Rule 45).
 */
final class StaffAssignmentTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /**
     * @param  list<string>  $roles
     * @return array{tenant: Tenant, outlet: Outlet, membership: Membership, token: string}
     */
    private function scenario(array $roles = [PermissionRegistry::ROLE_TENANT_OWNER]): array
    {
        $this->seedCatalogue();

        $tenant = $this->makeTenant();
        $outlet = $this->makeOutlet($tenant);
        $user = $this->makeUser(self::PASSWORD);
        $membership = $this->makeMembership($tenant, $user, $roles);

        return [
            'tenant' => $tenant,
            'outlet' => $outlet,
            'membership' => $membership,
            'token' => $this->loginToken($user, self::PASSWORD),
        ];
    }

    /** A second member of the same tenant, to be rostered by the actor. */
    private function staffMember(Tenant $tenant, array $roles = []): Membership
    {
        return $this->makeMembership($tenant, $this->makeUser(self::PASSWORD), $roles);
    }

    // ==================================================================
    // Outlet assignment
    // ==================================================================

    public function test_a_membership_is_assigned_to_an_outlet(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant, [PermissionRegistry::ROLE_CASHIER]);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(201)
            ->assertJsonPath('data.assignment.outlet_id', $outlet->id)
            ->assertJsonPath('data.assignment.is_active', true);
    }

    public function test_an_outlet_assignment_confers_no_permission(): void
    {
        // THE INVARIANT THIS WHOLE SEPARATION EXISTS FOR. Being rostered to a
        // counter must not make somebody able to do anything they could not do
        // before, or the roster screen is a privilege-escalation path wearing an
        // innocent name (DEC-0031 A2, threat T-14).
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();

        $user = $this->makeUser(self::PASSWORD);
        $staff = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_PRODUCTION_OPERATOR]);
        $staffToken = $this->loginToken($user, self::PASSWORD);

        $before = $this->withHeaders($this->bearer($staffToken, $tenant->id))
            ->getJson('/api/v1/authorization/permissions')
            ->json('data.permissions');

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(201);

        $after = $this->withHeaders($this->bearer($staffToken, $tenant->id))
            ->getJson('/api/v1/authorization/permissions')
            ->json('data.permissions');

        sort($before);
        sort($after);

        $this->assertSame($before, $after, 'An outlet assignment must confer no capability.');
    }

    public function test_the_same_membership_cannot_hold_two_live_assignments_to_one_outlet(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(422)
            ->assertJsonPath('error.details.assigned_outlet_id.0', 'duplicate');
    }

    public function test_revocation_records_history_rather_than_deleting_it(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token, 'membership' => $actor] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        $assignmentId = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->json('data.assignment.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets/{$assignmentId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.assignment.is_active', false);

        // The row survives, carrying WHO revoked it and WHEN. "Who could work
        // this outlet in March" must stay answerable (DEC-0025 §6's discipline).
        $record = MembershipOutlet::query()->whereKey($assignmentId)->first();

        $this->assertNotNull($record);
        $this->assertNotNull($record->revoked_at);
        $this->assertSame($actor->id, $record->revoked_by_membership_id);
    }

    public function test_a_revoked_assignment_may_be_reissued_without_destroying_history(): void
    {
        // The unique index is PARTIAL on `revoked_at IS NULL` precisely so this
        // works. A plain unique index would force the March record to be deleted
        // before a June reassignment could be made.
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        $first = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->json('data.assignment.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets/{$first}/revoke")
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(201);

        $this->assertSame(
            2,
            MembershipOutlet::query()->forTenant($tenant->id)->where('membership_id', $staff->id)->count()
        );
    }

    public function test_revoking_an_already_revoked_assignment_is_refused(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        $id = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->json('data.assignment.id');

        $this->withHeaders($headers)->postJson("/api/v1/staff/{$staff->id}/outlets/{$id}/revoke")->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets/{$id}/revoke")
            ->assertStatus(422)
            ->assertJsonPath('error.details.assignment.0', 'already_revoked');
    }

    public function test_a_revoked_membership_cannot_be_rostered(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);
        $staff->markRevoked();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(422)
            ->assertJsonPath('error.details.membership.0', 'revoked');
    }

    // ==================================================================
    // Cross-tenant assignment (invariant A1, threat T-13)
    // ==================================================================

    public function test_a_membership_cannot_be_assigned_to_another_tenants_outlet(): void
    {
        ['tenant' => $tenantA, 'token' => $token] = $this->scenario();

        $tenantB = $this->makeTenant('tenant-b');
        $outletB = $this->makeOutlet($tenantB);

        $staff = $this->staffMember($tenantA);

        // Indistinguishable from "no such outlet" — a caller must not learn that
        // another tenant holds it (Rule 48 hard rule 5).
        $this->withHeaders($this->bearer($token, $tenantA->id))
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outletB->id])
            ->assertStatus(404);
    }

    public function test_another_tenants_membership_is_not_addressable(): void
    {
        ['tenant' => $tenantA, 'outlet' => $outletA, 'token' => $token] = $this->scenario();

        $tenantB = $this->makeTenant('tenant-b');
        $foreign = $this->staffMember($tenantB);

        foreach ([
            "/api/v1/staff/{$foreign->id}",
        ] as $path) {
            $this->withHeaders($this->bearer($token, $tenantA->id))
                ->getJson($path)
                ->assertStatus(404);
        }

        $this->withHeaders($this->bearer($token, $tenantA->id))
            ->postJson("/api/v1/staff/{$foreign->id}/outlets", ['assigned_outlet_id' => $outletA->id])
            ->assertStatus(404);
    }

    public function test_the_database_refuses_a_cross_tenant_assignment_row(): void
    {
        // The structural guarantee under the application one (invariant A1).
        // Even a writer that never loads the registry cannot create this row.
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $membershipA = $this->makeMembership($tenantA, $this->makeUser());
        $outletB = $this->makeOutlet($tenantB);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('membership_outlet')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'membership_id' => $membershipA->id,
            // Belongs to tenant B. The composite foreign key refuses it.
            'outlet_id' => $outletB->id,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_half_written_revocation_is_refused_by_the_database(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet, 'membership' => $actor] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('membership_outlet')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'membership_id' => $staff->id,
            'outlet_id' => $outlet->id,
            'assigned_at' => now(),
            // A timestamp with no actor. A half-written audit fact is worse than
            // none, so the CHECK constraint refuses it.
            'revoked_at' => now(),
            'revoked_by_membership_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ==================================================================
    // Role assignment and the escalation guard (invariant A2, threat T-14)
    // ==================================================================

    public function test_an_owner_may_grant_a_role_they_hold(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_CASHIER])
            ->assertOk();

        $this->assertContains(
            PermissionRegistry::ROLE_CASHIER,
            $staff->fresh()->roles->pluck('key')->all()
        );
    }

    public function test_an_admin_cannot_grant_a_role_carrying_a_permission_they_lack(): void
    {
        // THE ESCALATION GUARD. `tenant_admin` deliberately lacks BRAND_MANAGE,
        // MEMBERSHIP_REVOKE and PRICE_OVERRIDE, all of which `tenant_owner`
        // holds. Without this guard an admin could grant `tenant_owner` to an
        // account they control and take the tenant.
        ['tenant' => $tenant] = $this->scenario();

        $adminUser = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $adminUser, [PermissionRegistry::ROLE_TENANT_ADMIN]);
        $adminToken = $this->loginToken($adminUser, self::PASSWORD);

        $staff = $this->staffMember($tenant);

        $response = $this->withHeaders($this->bearer($adminToken, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_TENANT_OWNER]);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        // The refusal names WHICH permissions were beyond the caller, so an
        // operator can ask for the right thing. It discloses nothing they could
        // not already read from the permission matrix.
        $this->assertContains(
            PermissionRegistry::BRAND_MANAGE,
            $response->json('error.details.role')
        );

        $this->assertNotContains(
            PermissionRegistry::ROLE_TENANT_OWNER,
            $staff->fresh()->roles->pluck('key')->all()
        );
    }

    public function test_an_admin_may_grant_a_role_strictly_within_their_own_permissions(): void
    {
        // The guard must not be a blanket denial: delegating what you hold is
        // exactly what the permission is for.
        ['tenant' => $tenant] = $this->scenario();

        $adminUser = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $adminUser, [PermissionRegistry::ROLE_TENANT_ADMIN]);
        $adminToken = $this->loginToken($adminUser, self::PASSWORD);

        $staff = $this->staffMember($tenant);

        $this->withHeaders($this->bearer($adminToken, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_CASHIER])
            ->assertOk();
    }

    public function test_a_platform_role_is_never_assignable_through_a_membership(): void
    {
        // DEC-0025 §8, invariant A3. Step 3's guard, called rather than
        // reimplemented.
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        foreach (PermissionRegistry::platformRoleKeys() as $platformRole) {
            $this->withHeaders($this->bearer($token, $tenant->id))
                ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => $platformRole])
                ->assertStatus(422)
                ->assertJsonPath('error.details.role.0', 'not_assignable');
        }

        $this->assertSame([], $staff->fresh()->roles->pluck('key')->all());
    }

    public function test_an_unknown_role_key_is_refused(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        // Tenant-defined custom roles are DEFERRED and NOT IMPLEMENTED
        // (DEC-0025 §10); a key outside the catalogue does not exist.
        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => 'super_kasir'])
            ->assertStatus(422);
    }

    public function test_granting_a_role_twice_is_idempotent(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        foreach ([1, 2] as $_) {
            $this->withHeaders($headers)
                ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_CASHIER])
                ->assertOk();
        }

        // An operator clicking twice has not done anything wrong.
        $this->assertSame(
            1,
            DB::table('membership_role')->where('membership_id', $staff->id)->count()
        );
    }

    public function test_removing_a_role_takes_effect_on_the_very_next_request(): void
    {
        // Rule 40 hard rule 3, invariant A4. Permissions are recomputed from
        // live state; nothing waits for a token to expire.
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();

        $staffUser = $this->makeUser(self::PASSWORD);
        $staff = $this->makeMembership($tenant, $staffUser, [PermissionRegistry::ROLE_CASHIER]);
        $staffToken = $this->loginToken($staffUser, self::PASSWORD);

        // The cashier can read customers.
        $this->withHeaders($this->bearer($staffToken, $tenant->id))
            ->getJson('/api/v1/customers')
            ->assertOk();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->deleteJson("/api/v1/staff/{$staff->id}/roles/".PermissionRegistry::ROLE_CASHIER)
            ->assertOk();

        // Same token, next request, no re-authentication.
        $this->withHeaders($this->bearer($staffToken, $tenant->id))
            ->getJson('/api/v1/customers')
            ->assertStatus(403);
    }

    public function test_role_removal_is_not_escalation_guarded(): void
    {
        // Taking a capability away never grants the actor anything. Guarding it
        // would let a tenant end up with a role nobody present can revoke.
        ['tenant' => $tenant] = $this->scenario();

        $ownerVictim = $this->staffMember($tenant, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $adminUser = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $adminUser, [PermissionRegistry::ROLE_TENANT_ADMIN]);
        $adminToken = $this->loginToken($adminUser, self::PASSWORD);

        $this->withHeaders($this->bearer($adminToken, $tenant->id))
            ->deleteJson("/api/v1/staff/{$ownerVictim->id}/roles/".PermissionRegistry::ROLE_TENANT_OWNER)
            ->assertOk();

        $this->assertNotContains(
            PermissionRegistry::ROLE_TENANT_OWNER,
            $ownerVictim->fresh()->roles->pluck('key')->all()
        );
    }

    // ==================================================================
    // Authorization of the surface itself
    // ==================================================================

    public function test_a_member_without_the_staff_permission_cannot_roster(): void
    {
        ['tenant' => $tenant, 'outlet' => $outlet] = $this->scenario();

        // A cashier holds no STAFF_ASSIGNMENT_MANAGE.
        $cashierUser = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $cashierUser, [PermissionRegistry::ROLE_CASHIER]);
        $cashierToken = $this->loginToken($cashierUser, self::PASSWORD);

        $staff = $this->staffMember($tenant);

        $this->withHeaders($this->bearer($cashierToken, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->assertStatus(403);
    }

    public function test_an_outlet_manager_cannot_assign_roles(): void
    {
        // An outlet manager runs an outlet's master data; handing out authority
        // is a tenant-wide act reserved to owner and admin (Rule 03 hard rule 1).
        ['tenant' => $tenant] = $this->scenario();

        $managerUser = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $managerUser, [PermissionRegistry::ROLE_OUTLET_MANAGER]);
        $managerToken = $this->loginToken($managerUser, self::PASSWORD);

        $staff = $this->staffMember($tenant);

        $this->withHeaders($this->bearer($managerToken, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_CASHIER])
            ->assertStatus(403);
    }

    public function test_the_staff_list_is_tenant_scoped(): void
    {
        ['tenant' => $tenantA, 'token' => $token] = $this->scenario();

        $tenantB = $this->makeTenant('tenant-b');
        $this->staffMember($tenantB);
        $this->staffMember($tenantB);

        $body = $this->withHeaders($this->bearer($token, $tenantA->id))
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->json('data');

        // Only tenant A's own single membership (the actor).
        $this->assertSame(1, $body['pagination']['total']);
    }

    public function test_the_staff_projection_does_not_expose_a_phone_number(): void
    {
        // Rule 32 hard rule 4 — masked by default. A roster screen has no
        // operational need for a staff member's phone, so the narrowest
        // projection that does the job is the one that cannot leak the rest.
        ['tenant' => $tenant, 'token' => $token] = $this->scenario();

        $body = $this->withHeaders($this->bearer($token, $tenant->id))
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->json('data.staff.0');

        $this->assertArrayNotHasKey('phone', $body['user']);

        // ASSERT A PHONE SHAPE, NOT THE SUBSTRING "08".
        //
        // The previous assertion was `assertStringNotContainsString('08', ...)`
        // and it was FLAKY: the projection carries a generated UUID and a
        // generated email, and a UUID such as
        // `019f841a-0849-7241-...` contains "08" by chance. The test failed
        // intermittently for a reason that had nothing to do with a phone
        // number, which is worse than no test — an intermittently red gate
        // teaches a reader to re-run rather than to look.
        //
        // What the rule actually forbids is a PHONE NUMBER reaching the roster
        // projection, so that is what is matched: an Indonesian subscriber
        // number in any of the forms `PhoneNumber::normalize` accepts.
        $encoded = json_encode($body['user']) ?: '';

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![0-9])(?:\+?62|0)8[0-9]{7,11}(?![0-9])/',
            $encoded,
            'The staff projection carries something shaped like an Indonesian '
            .'phone number. A roster screen has no operational need for one '
            .'(Rule 32 hard rule 4).'
        );
    }

    public function test_the_phone_shape_assertion_actually_catches_a_phone_number(): void
    {
        // The guard above is only worth having if it fires. A regular
        // expression that matched nothing would let the real assertion pass
        // silently for ever, so its positive case is pinned here.
        $pattern = '/(?<![0-9])(?:\+?62|0)8[0-9]{7,11}(?![0-9])/';

        foreach (['081200000001', '+6281200000001', '6281200000001'] as $phone) {
            $this->assertMatchesRegularExpression(
                $pattern,
                json_encode(['phone' => $phone]) ?: '',
                "The phone-shape guard failed to match {$phone}."
            );
        }

        // And it must NOT fire on the values that made the old assertion flaky.
        foreach ([
            '019f841a-0849-7241-ba0a-ed94168b3d48',
            'uji.lhkkgidpb3@contoh.invalid',
            'Pengguna Uji Fiktif',
        ] as $benign) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                json_encode(['value' => $benign]) ?: '',
                "The phone-shape guard fired on the benign value {$benign}."
            );
        }
    }

    // ==================================================================
    // Audit
    // ==================================================================

    public function test_assignment_and_revocation_are_audited_with_tenant_and_actor(): void
    {
        // Rule 46 hard rule 1 — every audit record states which tenant and which
        // actor. An entry with neither is not useful evidence.
        ['tenant' => $tenant, 'outlet' => $outlet, 'token' => $token, 'membership' => $actor] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $headers = $this->bearer($token, $tenant->id);

        $id = $this->withHeaders($headers)
            ->postJson("/api/v1/staff/{$staff->id}/outlets", ['assigned_outlet_id' => $outlet->id])
            ->json('data.assignment.id');

        $this->withHeaders($headers)->postJson("/api/v1/staff/{$staff->id}/outlets/{$id}/revoke")->assertOk();

        foreach ([AuditAction::STAFF_OUTLET_ASSIGNED, AuditAction::STAFF_OUTLET_REVOKED] as $action) {
            $entry = AuditEntry::query()
                ->forTenant($tenant->id)
                ->where('action', $action)
                ->first();

            $this->assertNotNull($entry, "No audit entry for {$action}.");
            $this->assertSame($tenant->id, $entry->tenant_id);
            $this->assertSame($actor->id, $entry->actor_membership_id);
            $this->assertSame($outlet->id, $entry->outlet_id);
        }
    }

    public function test_role_assignment_is_audited(): void
    {
        ['tenant' => $tenant, 'token' => $token, 'membership' => $actor] = $this->scenario();
        $staff = $this->staffMember($tenant);

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson("/api/v1/staff/{$staff->id}/roles", ['role' => PermissionRegistry::ROLE_CASHIER])
            ->assertOk();

        $entry = AuditEntry::query()
            ->forTenant($tenant->id)
            ->where('action', AuditAction::MEMBERSHIP_ROLE_ASSIGNED)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($actor->id, $entry->actor_membership_id);
        $this->assertSame(PermissionRegistry::ROLE_CASHIER, $entry->metadata['role']);
    }

    // ==================================================================
    // Structural facts
    // ==================================================================

    public function test_membership_outlet_is_bound_to_both_sides_by_composite_key(): void
    {
        foreach ([
            'membership_outlet_tenant_membership_foreign',
            'membership_outlet_tenant_outlet_foreign',
        ] as $constraint) {
            $this->assertNotNull(
                DB::selectOne('select conname from pg_constraint where conname = ?', [$constraint]),
                "Missing composite foreign key {$constraint} (invariant A1)."
            );
        }
    }

    public function test_step_4_introduces_no_new_role_or_permission_model(): void
    {
        // DEC-0031 A2 — Step 4 consumes the Step 3 authorization source of truth
        // rather than creating a parallel one. If a second role table ever
        // appears, this fails.
        $roleTables = DB::select(
            "select table_name from information_schema.tables
             where table_schema = 'public' and (table_name like '%role%' or table_name like '%permission%')
             order by table_name"
        );

        $names = array_map(static fn (object $r): string => $r->table_name, $roleTables);

        sort($names);

        $this->assertSame(
            ['membership_role', 'permissions', 'role_permission', 'roles'],
            $names,
            'Step 4 must add no new role or permission table (DEC-0031 A2).'
        );
    }
}
