<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEC-12 — the append-only boundary on `customer_consents` did not hold.
 *
 * WHAT WAS WRONG
 * --------------
 * `2026_07_21_010200_create_customer_consents_table.php` protected consent
 * history with two PostgreSQL RULEs:
 *
 *     ON UPDATE TO customer_consents DO INSTEAD NOTHING
 *     ON DELETE TO customer_consents DO INSTEAD NOTHING
 *
 * and its comment claimed that "even a migration, an import, or a direct `psql`
 * session cannot rewrite consent history." That claim was wrong in two distinct
 * ways, and an independent review found both.
 *
 * 1. RULES DO NOT APPLY TO `TRUNCATE`. The PostgreSQL rule system rewrites
 *    `SELECT`, `INSERT`, `UPDATE` and `DELETE` queries. `TRUNCATE` is not a
 *    query the rewriter sees; it is a table-level operation. So one statement —
 *    the single most destructive one available — went straight past a boundary
 *    documented as absolute, and every consent record for every tenant could be
 *    removed with no error, no audit trace, and nothing left to show it had ever
 *    existed. FR-028 says a recorded opt-out is never reset by an import, a bulk
 *    update, or a migration; an opt-out that can be truncated away is reset by
 *    all three.
 *
 * 2. `DO INSTEAD NOTHING` IS SILENT. An `UPDATE` against consent history
 *    reported success and affected zero rows. Nothing distinguished "the write
 *    was refused" from "the write matched nothing," so a bulk job rewriting
 *    consent would have logged a clean run. A boundary that cannot be observed
 *    being hit is a boundary nobody will notice is load-bearing.
 *
 * WHAT REPLACES IT
 * ----------------
 * Statement- and row-level triggers that RAISE. Triggers fire for `TRUNCATE`
 * (`BEFORE TRUNCATE ... FOR EACH STATEMENT`), and they fire loudly: the
 * offending transaction aborts with a deterministic SQLSTATE rather than
 * quietly doing nothing.
 *
 * THIS MIGRATION WAS NOT SUFFICIENT ON ITS OWN. `CREATE TRIGGER` produces an
 * `ENABLE ORIGIN` trigger, which does not fire when a session sets
 * `session_replication_role = 'replica'` — so all three refusals could be
 * removed with one extra `SET`, needing no privilege escalation and no schema
 * change. An independent review proved it. Migration
 * `2026_07_22_000300_enable_always_customer_consent_triggers.php` moves them to
 * `ENABLE ALWAYS`. The claims below are corrected in place rather than deleted,
 * because a correction with its error removed reads as something that was
 * always right (Rule 01).
 *
 * WHY NOT PRIVILEGE REVOCATION INSTEAD
 * ------------------------------------
 * `REVOKE TRUNCATE ON customer_consents FROM <app role>` is worth having, but it
 * cannot be the mechanism this rests on: a table's OWNER holds its privileges
 * implicitly and cannot be revoked out of them, and the development application
 * role owns this table because it ran the migration. A privilege check would
 * therefore pass in a deployment where the app role is not the owner and be
 * inert in the one we actually run.
 *
 * CORRECTION. This paragraph continued: "A trigger fires for the owner and for
 * a superuser alike, so the guarantee does not depend on how roles happen to be
 * arranged." THAT WAS FALSE in the state this migration left the triggers in.
 * It becomes true only once they are `ENABLE ALWAYS`, which `2026_07_22_000300`
 * does. Revocation remains a defence-in-depth measure for a future deployment
 * topology (deployment remains ABSENT), not today's control.
 *
 * WHAT THIS STILL DOES NOT STOP, STATED PLAINLY
 * ---------------------------------------------
 * `DROP TABLE`, `ALTER TABLE ... DISABLE TRIGGER`, and dropping the trigger
 * itself remain available to a role with ownership or superuser rights.
 *
 * CORRECTION. That enumeration was INCOMPLETE, and its incompleteness was the
 * defect rather than a documentation slip: it omitted `session_replication_role`,
 * which needs no schema change and no privilege at all. A list headed "stated
 * plainly" that misses the cheapest bypass is worse than no list, because it
 * invites the reader to stop looking. `2026_07_22_000300` carries the corrected
 * enumeration.
 *
 * The claim made here — "no ordinary `UPDATE`, `DELETE`, or `TRUNCATE` against
 * this table succeeds, from any client, including `psql`" — was therefore FALSE
 * as written. All three succeeded from the application's own connection with one
 * extra `SET`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Raising, rather than silently swallowing. `restrict_violation`
        // (23001) is a determinate SQLSTATE a caller can branch on without
        // string-matching an error message.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION customer_consents_refuse_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION
                    'customer_consents is append-only: % is refused (FR-028).',
                    TG_OP
                    USING ERRCODE = 'restrict_violation';
            END;
            $$
        SQL);

        // The rules go, because leaving them alongside the triggers would mean
        // an UPDATE is rewritten to nothing BEFORE any trigger could fire, and
        // the silent behaviour would survive under a boundary that reads as
        // loud.
        DB::statement('DROP RULE IF EXISTS customer_consents_no_update ON customer_consents');
        DB::statement('DROP RULE IF EXISTS customer_consents_no_delete ON customer_consents');

        DB::statement(<<<'SQL'
            CREATE TRIGGER customer_consents_refuse_update
            BEFORE UPDATE ON customer_consents
            FOR EACH ROW
            EXECUTE FUNCTION customer_consents_refuse_mutation()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER customer_consents_refuse_delete
            BEFORE DELETE ON customer_consents
            FOR EACH ROW
            EXECUTE FUNCTION customer_consents_refuse_mutation()
        SQL);

        // The finding. `FOR EACH STATEMENT` is not a style choice: TRUNCATE
        // touches no rows the way a DELETE does, so a row-level trigger would
        // never fire and the hole would remain open under a trigger that looked
        // like it had closed it.
        DB::statement(<<<'SQL'
            CREATE TRIGGER customer_consents_refuse_truncate
            BEFORE TRUNCATE ON customer_consents
            FOR EACH STATEMENT
            EXECUTE FUNCTION customer_consents_refuse_mutation()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS customer_consents_refuse_truncate ON customer_consents');
        DB::statement('DROP TRIGGER IF EXISTS customer_consents_refuse_delete ON customer_consents');
        DB::statement('DROP TRIGGER IF EXISTS customer_consents_refuse_update ON customer_consents');
        DB::statement('DROP FUNCTION IF EXISTS customer_consents_refuse_mutation()');

        // Restore the weaker predecessor rather than leaving the table
        // unprotected mid-rollback: a rollback that removes a safety boundary
        // and puts nothing back is a worse state than either migration.
        DB::statement(<<<'SQL'
            CREATE RULE customer_consents_no_update AS
            ON UPDATE TO customer_consents DO INSTEAD NOTHING
        SQL);

        DB::statement(<<<'SQL'
            CREATE RULE customer_consents_no_delete AS
            ON DELETE TO customer_consents DO INSTEAD NOTHING
        SQL);
    }
};
