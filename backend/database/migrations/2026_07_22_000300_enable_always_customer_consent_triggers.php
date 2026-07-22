<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SEC-12, REOPENED — the append-only triggers were bypassable after all.
 *
 * WHAT THE INDEPENDENT REVIEW PROVED
 * ----------------------------------
 * `2026_07_22_000100_harden_customer_consents_append_only.php` replaced two
 * silent RULEs with three raising triggers, and its comment claimed:
 *
 *     "A trigger fires for the owner and for a superuser alike, so the
 *      guarantee does not depend on how roles happen to be arranged."
 *
 * That sentence was FALSE. `CREATE TRIGGER` produces a trigger in `ENABLE
 * ORIGIN` state (`pg_trigger.tgenabled = 'O'`), and an origin-enabled trigger
 * DOES NOT FIRE when the session sets `session_replication_role = 'replica'`.
 * One extra `SET` on the application's own connection, and all three refusals
 * disappeared: an opt-out recorded as `withdrawn` was flipped back to
 * `granted`, rows were deleted, and the table was emptied.
 *
 * The bound the earlier record claimed was not a bound at all. It said the
 * protection held for the application role and carved out superusers and schema
 * owners — but `aish_dev` IS the superuser and IS this table's owner, so the
 * protected class and the exception were the same principal.
 *
 * AND FR-028 NAMES THE EXACT ATTACK PATH. It says a recorded opt-out is never
 * reset by "a data import". `pg_restore --disable-triggers` and logical
 * replication apply set precisely this GUC. The one mechanism the requirement
 * names by name was the one that walked through.
 *
 * THE FIX
 * -------
 * `ENABLE ALWAYS` (`tgenabled = 'A'`) fires regardless of
 * `session_replication_role`. Verified empirically: the same UPDATE that
 * succeeded in replica mode is refused with SQLSTATE 23001 afterwards.
 *
 * WHAT IS STILL NOT STOPPED — a corrected enumeration, since the previous one
 * was incomplete and that incompleteness is the whole reason this file exists:
 *
 *   * `DROP TABLE`, `DROP TRIGGER`, and `ALTER TABLE ... DISABLE TRIGGER`;
 *   * `ALTER TABLE ... ENABLE REPLICA/ORIGIN TRIGGER`, which puts the trigger
 *     back into the bypassable state this migration moved it out of;
 *   * anything performed by a role that can execute the above — which, in the
 *     development environment, is the application role itself.
 *
 * That last point is the honest one. In development the application connects as
 * a superuser that owns this table, so nothing enforced INSIDE the database can
 * be a boundary against it. This migration removes the bypass that needed no
 * privilege escalation and no DDL — a single `SET` any session could issue. It
 * does not, and cannot, defend against a principal that may rewrite the schema.
 * A deployment that wants a real boundary must run the application as a
 * non-owner, non-superuser role; deployment remains ABSENT, so that is recorded
 * as a requirement rather than claimed as a control.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TRIGGERS = [
        'customer_consents_refuse_update',
        'customer_consents_refuse_delete',
        'customer_consents_refuse_truncate',
    ];

    public function up(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::statement(
                "ALTER TABLE customer_consents ENABLE ALWAYS TRIGGER {$trigger}"
            );
        }
    }

    public function down(): void
    {
        // Back to ORIGIN, which is what `CREATE TRIGGER` produced. Rolling back
        // restores the PREVIOUS state rather than removing the trigger — a
        // rollback that left the table unprotected would be worse than either
        // migration, and a rollback that silently kept the hardening would make
        // the migration untestable in the down direction.
        foreach (self::TRIGGERS as $trigger) {
            DB::statement(
                "ALTER TABLE customer_consents ENABLE TRIGGER {$trigger}"
            );
        }
    }
};
