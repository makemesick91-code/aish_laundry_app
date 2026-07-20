<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * IDENTITY-SCOPE AUDIT ENTRIES.
 *
 * THE PROBLEM THIS SOLVES
 * -----------------------
 * The most security-relevant events in Step 3 happen BEFORE any tenant exists in
 * the request: a login attempt, a login failure, a logout, a password-reset
 * request, a password-reset completion. At that moment the actor has not chosen
 * a tenant, and a failed login may not correspond to any account at all.
 *
 * With `tenant_id` NOT NULL, those events could only be recorded by inventing a
 * tenant for them. Two bad options follow: attach them to an arbitrary tenant
 * (which is a false statement in the audit trail, and pollutes that tenant's
 * audit view with events that are not theirs), or do not record them at all
 * (which is worse — authentication events are precisely what an audit trail
 * exists for).
 *
 * So `tenant_id` becomes NULLABLE, and NULL carries one specific, documented
 * meaning: **this event happened at identity/platform scope and belongs to no
 * tenant**.
 *
 * WHY THIS DOES NOT WEAKEN TENANT ISOLATION
 * -----------------------------------------
 * The CHECK constraint below makes the tenant dimension mandatory the moment the
 * entry references anything tenant-owned. An entry may only have a NULL tenant
 * if it also has NO outlet and NO membership — that is, only if it genuinely has
 * no tenant-scoped subject. A tenant-scoped event therefore still cannot be
 * written without its tenant, and the guarantee is structural rather than
 * conventional.
 *
 * Tenant-scoped audit READS remain filtered by `tenant_id` and never return
 * NULL-tenant rows, so a tenant member cannot see platform-scope events either
 * (Rule 02, Rule 22).
 *
 * PostgreSQL composite foreign keys use MATCH SIMPLE: when any referencing
 * column is NULL the constraint is not checked, so the existing composite keys
 * remain correct and remain enforced whenever the tenant is present.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audit_entries ALTER COLUMN tenant_id DROP NOT NULL');

        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries
            ADD CONSTRAINT audit_entries_tenant_scope_check
            CHECK (
                tenant_id IS NOT NULL
                OR (outlet_id IS NULL AND actor_membership_id IS NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audit_entries DROP CONSTRAINT IF EXISTS audit_entries_tenant_scope_check');
        DB::statement('DELETE FROM audit_entries WHERE tenant_id IS NULL');
        DB::statement('ALTER TABLE audit_entries ALTER COLUMN tenant_id SET NOT NULL');
    }
};
