<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STAFF ASSIGNED TO AN OUTLET (ROADMAP Step 4 scope, FR-018, DEC-0031 A).
 *
 * ONE TABLE. NO SECOND AUTHORIZATION SYSTEM.
 * ------------------------------------------
 * Step 4's staff-and-role-assignment scope is not new authorization machinery.
 * Roles and permissions were delivered in Step 3 and continue to come from
 * `PermissionRegistry` via `membership_role`; the PRD places role scoping at
 * FR-018, which Step 3 already satisfied.
 *
 * What was genuinely missing is the binding between an existing membership and
 * the outlet master data Step 4 creates: which counter does this kasir work at.
 * That is this table, and nothing else (Rule 40 — Step 4 introduces no second
 * role model, DEC-0031 A2).
 *
 * BOTH SIDES CARRY THE TENANT, AND BOTH COMPOSITE KEYS INCLUDE IT (invariant A1).
 * ------------------------------------------------------------------------------
 * `(tenant_id, membership_id)` -> `memberships(tenant_id, id)` and
 * `(tenant_id, outlet_id)` -> `outlets(tenant_id, id)`. A row can therefore only
 * exist when the membership and the outlet are in the SAME tenant — PostgreSQL
 * rejects any other combination, whatever the application does (threat T-13).
 *
 * A single-column FK to each side would let a membership in tenant A be assigned
 * to an outlet in tenant B, with nothing but a code review standing in the way.
 *
 * REVOCATION IS RECORDED, NOT DELETED.
 * ------------------------------------
 * `revoked_at` and `revoked_by_membership_id` mean an assignment's history
 * survives its removal, matching how `memberships` records revocation rather
 * than deleting the row (DEC-0025 §6). "Who could work this outlet in March" is
 * a question an audit must be able to answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_outlet', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('membership_id');
            $table->uuid('outlet_id');

            // Who made the assignment. Evidence, therefore server-side.
            $table->uuid('assigned_by_membership_id')->nullable();
            $table->timestampTz('assigned_at');

            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('revoked_by_membership_id')->nullable();

            // Optimistic-concurrency counter — see outlets.version.
            $table->unsignedBigInteger('version')->default(1);

            $table->timestamps();

            $table->index('tenant_id', 'membership_outlet_tenant_id_index');
            $table->index(['tenant_id', 'membership_id'], 'membership_outlet_tenant_membership_index');
            $table->index(['tenant_id', 'outlet_id'], 'membership_outlet_tenant_outlet_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE membership_outlet
            ADD CONSTRAINT membership_outlet_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // INVARIANT A1, half one: the membership must be in this tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE membership_outlet
            ADD CONSTRAINT membership_outlet_tenant_membership_foreign
            FOREIGN KEY (tenant_id, membership_id)
            REFERENCES memberships (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // INVARIANT A1, half two: the outlet must be in the SAME tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE membership_outlet
            ADD CONSTRAINT membership_outlet_tenant_outlet_foreign
            FOREIGN KEY (tenant_id, outlet_id)
            REFERENCES outlets (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // ONE ACTIVE ASSIGNMENT PER (membership, outlet).
        //
        // Partial on `revoked_at IS NULL`, so a membership revoked from an outlet
        // in March and reassigned in June produces two rows and a readable
        // history — while two SIMULTANEOUS live assignments to the same outlet
        // remain impossible.
        //
        // A plain unique index would instead make the June reassignment collide
        // with the March record and force the history to be destroyed to allow it.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX membership_outlet_one_active_assignment
            ON membership_outlet (tenant_id, membership_id, outlet_id)
            WHERE revoked_at IS NULL
        SQL);

        // A revocation is either fully recorded or not recorded at all. A row
        // with a timestamp and no actor, or an actor and no timestamp, is a
        // half-written audit fact and is worse than none.
        DB::statement(<<<'SQL'
            ALTER TABLE membership_outlet
            ADD CONSTRAINT membership_outlet_revocation_complete_check
            CHECK (
                (revoked_at IS NULL AND revoked_by_membership_id IS NULL)
                OR (revoked_at IS NOT NULL AND revoked_by_membership_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_outlet');
    }
};
