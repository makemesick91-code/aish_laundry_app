<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * MATRIX A — PostgreSQL STRUCTURAL tenant isolation.
 *
 * This matrix does not test application code. It tests the DATABASE. The claim
 * under examination is that a cross-tenant relation is not merely rejected by a
 * query scope somebody remembered to write, but is IMPOSSIBLE TO STORE — that
 * PostgreSQL itself refuses the row.
 *
 * WHY THIS MUST RUN ON POSTGRESQL AND NEVER ON SQLITE
 * ---------------------------------------------------
 * Every guarantee asserted here rests on a COMPOSITE foreign key of the shape
 * `(tenant_id, child_id) REFERENCES parent (tenant_id, id)`. SQLite does not
 * enforce these the way PostgreSQL does, so an identical suite passing on SQLite
 * would prove nothing at all. `assertRunningOnPostgres()` therefore runs before
 * every case: an isolation proof that silently degraded to another driver is
 * worse than no proof, because it reads like one.
 *
 * THE CONTROL / VIOLATION DISCIPLINE (governing principle)
 * --------------------------------------------------------
 * A rejection is evidence ONLY when the expected write was actually reached and
 * the EXPECTED constraint caused the rejection. Every violation case below is
 * therefore paired with a control case that inserts a row of the IDENTICAL
 * SHAPE — same table, same column set, same non-null values — varying ONLY the
 * tenant binding. The control proves the row is otherwise valid; the violation
 * then isolates the tenant binding as the sole cause.
 *
 * And the violation assertion is specific, never merely "an exception happened":
 *
 *   - SQLSTATE must be exactly 23503 (foreign_key_violation). A 23502
 *     (not_null_violation) or 42703 (undefined_column) passing as "isolation
 *     proof" is the exact false result this discipline exists to prevent.
 *   - The named constraint must appear in the driver message, so the rejection
 *     is attributed to the SPECIFIC composite key intended, not to some other
 *     key that happened to fire first.
 *
 * Every value here is fictional (Rule 23).
 */
final class StructuralIsolationMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    // =====================================================================
    // A1 / A2 — outlets -> laundry_brands
    // =====================================================================

    public function test_a1_control_an_outlet_may_reference_a_brand_of_its_own_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $brandA = $this->makeBrand($tenantA);

        // Control: identical row shape to A2, tenant binding CONSISTENT.
        $inserted = DB::table('outlets')->insert(
            $this->outletRow($tenantA->id, $brandA->id)
        );

        $this->assertTrue($inserted, 'Control case must succeed: the row is valid apart from tenant binding.');
    }

    public function test_a2_violation_an_outlet_may_not_reference_a_brand_of_another_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $brandB = $this->makeBrand($tenantB);

        // Violation: same shape as A1; ONLY the brand's tenant differs.
        $this->assertForeignKeyViolation(
            'outlets',
            $this->outletRow($tenantA->id, $brandB->id),
            'outlets_tenant_brand_foreign'
        );
    }

    // =====================================================================
    // A3 / A4 — membership_role -> memberships
    // =====================================================================

    public function test_a3_control_a_role_may_be_attached_to_a_membership_of_its_own_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $membershipA = $this->makeMembership($tenantA, $this->makeUser());
        $roleId = $this->roleId('cashier');

        $inserted = DB::table('membership_role')->insert(
            $this->membershipRoleRow($tenantA->id, $membershipA->id, $roleId)
        );

        $this->assertTrue($inserted, 'Control case must succeed: the role assignment is valid within one tenant.');
    }

    public function test_a4_violation_a_role_may_not_be_attached_across_a_tenant_boundary(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $membershipB = $this->makeMembership($tenantB, $this->makeUser());
        $roleId = $this->roleId('cashier');

        // Tenant A claimed, membership belongs to tenant B. The composite key
        // is what makes "grant a role in a tenant you do not administer"
        // unstorable rather than merely discouraged.
        $this->assertForeignKeyViolation(
            'membership_role',
            $this->membershipRoleRow($tenantA->id, $membershipB->id, $roleId),
            'membership_role_tenant_membership_foreign'
        );
    }

    // =====================================================================
    // A5 / A6 — device_sessions -> memberships
    // =====================================================================

    public function test_a5_control_a_device_session_may_bind_to_a_membership_of_its_own_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $userA = $this->makeUser();
        $membershipA = $this->makeMembership($tenantA, $userA);

        $inserted = DB::table('device_sessions')->insert(
            $this->deviceSessionRow($tenantA->id, $membershipA->id, $userA->id)
        );

        $this->assertTrue($inserted, 'Control case must succeed: the device session is valid within one tenant.');
    }

    public function test_a6_violation_a_device_session_may_not_bind_across_a_tenant_boundary(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userB = $this->makeUser();
        $membershipB = $this->makeMembership($tenantB, $userB);

        $this->assertForeignKeyViolation(
            'device_sessions',
            $this->deviceSessionRow($tenantA->id, $membershipB->id, $userB->id),
            'device_sessions_tenant_membership_foreign'
        );
    }

    // =====================================================================
    // A7 / A8 — audit_entries -> memberships
    // =====================================================================

    public function test_a7_control_an_audit_entry_may_name_an_actor_membership_of_its_own_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $userA = $this->makeUser();
        $membershipA = $this->makeMembership($tenantA, $userA);

        $inserted = DB::table('audit_entries')->insert(
            $this->auditRow($tenantA->id, $membershipA->id, $userA->id)
        );

        $this->assertTrue($inserted, 'Control case must succeed: the audit entry is valid within one tenant.');
    }

    public function test_a8_violation_an_audit_entry_may_not_name_an_actor_membership_of_another_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userB = $this->makeUser();
        $membershipB = $this->makeMembership($tenantB, $userB);

        // An audit trail that can be written against another tenant's actor is
        // a forged audit trail. The composite key forecloses it.
        $this->assertForeignKeyViolation(
            'audit_entries',
            $this->auditRow($tenantA->id, $membershipB->id, $userB->id),
            'audit_entries_tenant_membership_foreign'
        );
    }

    // =====================================================================
    // A8b — audit_entries -> outlets (the second composite key on this table)
    // =====================================================================

    public function test_a8b_violation_an_audit_entry_may_not_name_an_outlet_of_another_tenant(): void
    {
        $this->assertRunningOnPostgres();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userA = $this->makeUser();
        $membershipA = $this->makeMembership($tenantA, $userA);
        $outletB = $this->makeOutlet($tenantB);

        $row = $this->auditRow($tenantA->id, $membershipA->id, $userA->id);
        $row['outlet_id'] = $outletB->id;

        $this->assertForeignKeyViolation('audit_entries', $row, 'audit_entries_tenant_outlet_foreign');
    }

    // =====================================================================
    // Row builders — one shape per table, used by BOTH control and violation.
    //
    // Sharing the builder is deliberate: it makes it structurally impossible
    // for the violation case to differ from the control by a missing column, a
    // null in a NOT NULL slot, or a typo. The ONLY thing a caller may vary is
    // the tenant binding, which is exactly the variable under test.
    // =====================================================================

    /** @return array<string, mixed> */
    private function outletRow(string $tenantId, string $brandId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'laundry_brand_id' => $brandId,
            'name' => 'Outlet Uji Fiktif',
            'code' => 'UJI-'.Str::upper(Str::random(5)),
            'timezone' => 'Asia/Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function membershipRoleRow(string $tenantId, string $membershipId, string $roleId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'membership_id' => $membershipId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function deviceSessionRow(string $tenantId, string $membershipId, string $userId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'membership_id' => $membershipId,
            'user_id' => $userId,
            'device_identifier' => 'perangkat-uji-'.Str::lower(Str::random(8)),
            'device_name' => 'Perangkat Uji Fiktif',
            'platform' => 'android',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function auditRow(string $tenantId, string $membershipId, string $actorUserId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'outlet_id' => null,
            'actor_user_id' => $actorUserId,
            'actor_membership_id' => $membershipId,
            'action' => 'uji.matriks.isolasi',
            'subject_type' => 'tenancy.membership',
            'subject_id' => $membershipId,
            'created_at' => now(),
        ];
    }

    private function roleId(string $key): string
    {
        $id = DB::table('roles')->where('key', $key)->value('id');

        // If the catalogue were missing, every "violation" below would fail on a
        // NULL role_id instead of on the tenant binding — an invalid proof.
        $this->assertNotNull($id, sprintf('Role catalogue must contain "%s" before this matrix can prove anything.', $key));

        return (string) $id;
    }

    // =====================================================================
    // Assertion helpers
    // =====================================================================

    /**
     * Assert that inserting $row into $table is rejected by PostgreSQL with
     * SQLSTATE 23503 raised by the SPECIFICALLY NAMED constraint.
     *
     * The insert runs inside a nested transaction (a SAVEPOINT, because
     * RefreshDatabase already holds an outer transaction) so that the failed
     * statement does not poison the surrounding test transaction.
     *
     * @param  array<string, mixed>  $row
     */
    private function assertForeignKeyViolation(string $table, array $row, string $constraint): void
    {
        try {
            DB::transaction(function () use ($table, $row): void {
                DB::table($table)->insert($row);
            });

            $this->fail(sprintf(
                'CROSS-TENANT ROW WAS ACCEPTED. Inserting into "%s" with a mismatched tenant binding '
                .'succeeded, so constraint "%s" is not enforcing tenant isolation. This is an automatic '
                .'NO-GO under Rule 02.',
                $table,
                $constraint
            ));
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

            $this->assertSame(
                '23503',
                $sqlState,
                sprintf(
                    'Expected SQLSTATE 23503 (foreign_key_violation) from "%s". Got "%s". A rejection for any '
                    ."other reason — 23502 not-null, 42703 undefined-column, 23505 unique — is NOT evidence of \n"
                    ."tenant isolation and must not be recorded as one.\nDriver message: %s",
                    $table,
                    $sqlState,
                    $exception->getMessage()
                )
            );

            $this->assertStringContainsString(
                $constraint,
                $exception->getMessage(),
                sprintf(
                    'Expected the rejection to be attributed to constraint "%s". Another constraint firing '
                    ."first would mean the intended composite key is untested.\nDriver message: %s",
                    $constraint,
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * Fail loudly rather than silently proving nothing on the wrong driver.
     */
    private function assertRunningOnPostgres(): void
    {
        $driver = DB::connection()->getDriverName();

        $this->assertSame(
            'pgsql',
            $driver,
            'Structural tenant-isolation evidence is only valid on PostgreSQL. Driver in use: '.$driver.'. '
            .'SQLite does not enforce the composite foreign keys this matrix depends on, so a pass here '
            .'would be a false result (phpunit.xml, Rule 01).'
        );
    }
}
