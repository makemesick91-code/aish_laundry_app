<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AUDIT ENTRIES — the append-only record of what happened.
 *
 * Deliberately has `created_at` and NO `updated_at` and NO `deleted_at`. The
 * absence is the point: an audit entry is never edited and never hard-deleted.
 * A correction is a NEW entry, exactly as a financial correction is a reversal
 * rather than a rewrite (Rule 04, hard rule 8).
 *
 * NEVER WRITE A SECRET HERE. No password, OTP, session token, tracking token,
 * API credential, or private key may reach this table — not in `metadata`, not
 * in `reason`, not in `changes` (Rule 03, hard rule 20; Rule 21, hard rule 18).
 * An audit record is classified CONFIDENTIAL or RESTRICTED by its contents.
 *
 * `actor_user_id` is NULLABLE because a legitimate entry may have no human
 * actor — a scheduled job or a system action. It is never null merely because
 * the actor was inconvenient to determine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');

            // Outlet context where the action had one. Not every audited action
            // happens at an outlet.
            $table->uuid('outlet_id')->nullable();

            $table->uuid('actor_user_id')->nullable();
            $table->uuid('actor_membership_id')->nullable();

            // Set when the action was performed during a support impersonation
            // session. Platform support has NO silent tenant access: an
            // impersonated action is distinguishable from a genuine one
            // (Rule 03, hard rules 18 and 19).
            $table->uuid('impersonator_user_id')->nullable();

            // Technical identifier from a closed vocabulary, e.g.
            // `membership.role.assigned`. Never free text.
            $table->string('action');

            // Polymorphic subject of the action.
            $table->string('subject_type');
            $table->uuid('subject_id');

            // Mandatory where a rule requires a reason. Whitespace-only input is
            // rejected at the application boundary (Rule 32, hard rule 16).
            $table->text('reason')->nullable();

            // Before/after values. Redacted at source — never a raw credential.
            $table->jsonb('changes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Server time is authoritative. A client clock is skewed and is
            // untrusted metadata (Rule 20, hard rule 11).
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id', 'audit_entries_tenant_id_index');
            $table->index(['tenant_id', 'created_at'], 'audit_entries_tenant_created_index');
            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'audit_entries_tenant_subject_index');
            $table->index(['tenant_id', 'actor_user_id'], 'audit_entries_tenant_actor_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_tenant_id_foreign
            FOREIGN KEY (tenant_id) REFERENCES tenants (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        // THE STRUCTURAL TENANT GUARANTEE.
        // An entry may only reference an outlet, a membership, and an outlet's
        // tenant that all agree with the entry's own tenant.
        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_tenant_outlet_foreign
            FOREIGN KEY (tenant_id, outlet_id)
            REFERENCES outlets (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_tenant_membership_foreign
            FOREIGN KEY (tenant_id, actor_membership_id)
            REFERENCES memberships (tenant_id, id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_actor_user_id_foreign
            FOREIGN KEY (actor_user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_impersonator_user_id_foreign
            FOREIGN KEY (impersonator_user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE RESTRICT
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
