<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 6 — PRODUCTION OPERATIONS · FR-074 batch operations, residual closure.
 *
 * The batch TABLES (production_batches, production_batch_items) were created in
 * 2026_07_24_100000_create_production_tables.php as prepared infrastructure. This
 * migration adds the append-only ledger that turns that infrastructure into a
 * working batch workflow:
 *
 *   - SERVER-SIDE IDEMPOTENCY (Rule 07/20, FR-079-class contract for batches):
 *     the partial UNIQUE (tenant_id, client_reference) index makes a replayed
 *     batch command exactly-once. ProductionBatchService catches the replay here
 *     and returns the original effect; a replay with the SAME reference and a
 *     DIFFERENT payload fails closed.
 *
 *   - APPEND-ONLY MEMBERSHIP HISTORY (FR-074): production_batch_items holds the
 *     CURRENT membership; removing an item deletes its current-membership row,
 *     but the historical fact (added, then removed) is preserved here forever.
 *     The refuse-UPDATE / refuse-DELETE / refuse-TRUNCATE triggers make that
 *     guarantee structural, exactly like production_events (FR-084).
 *
 *   - THE BATCH TIMELINE: GET /production/batches/{batch}/timeline reads these
 *     rows in occurred_at order.
 *
 * The table is tenant-bound and composite-FK'd to production_batches so a batch
 * event can never reference a batch in another tenant (Rule 02/48). It records no
 * money and never touches an order's price snapshot (Rule 04, FR-036).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batch_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('batch_id');
            $table->string('type', 64);
            $table->uuid('actor_membership_id')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->uuid('client_reference')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'batch_id'], 'production_batch_events_tenant_batch_index');
        });

        DB::statement('ALTER TABLE production_batch_events ADD CONSTRAINT production_batch_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        // Composite FK: a batch event is bound to a batch in the SAME tenant.
        DB::statement('ALTER TABLE production_batch_events ADD CONSTRAINT production_batch_events_tenant_batch_foreign FOREIGN KEY (tenant_id, batch_id) REFERENCES production_batches (tenant_id, id) ON DELETE RESTRICT');
        // Server-side idempotency key: a replayed batch command with the same
        // reference is caught and returns the original effect (Rule 07/20).
        DB::statement('CREATE UNIQUE INDEX production_batch_events_tenant_client_ref_unique ON production_batch_events (tenant_id, client_reference) WHERE client_reference IS NOT NULL');

        // Append-only: batch history is never rewritten (FR-074, mirrors FR-084).
        $fn = 'production_batch_events_refuse_mutation';
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$fn}()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION 'production_batch_events is append-only: % is refused — batch membership history is never rewritten (FR-074).', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END; \$\$
        SQL);
        DB::statement("CREATE TRIGGER production_batch_events_refuse_update BEFORE UPDATE ON production_batch_events FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER production_batch_events_refuse_delete BEFORE DELETE ON production_batch_events FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER production_batch_events_refuse_truncate BEFORE TRUNCATE ON production_batch_events FOR EACH STATEMENT EXECUTE FUNCTION {$fn}()");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS production_batch_events_refuse_update ON production_batch_events');
        DB::statement('DROP TRIGGER IF EXISTS production_batch_events_refuse_delete ON production_batch_events');
        DB::statement('DROP TRIGGER IF EXISTS production_batch_events_refuse_truncate ON production_batch_events');
        DB::statement('DROP FUNCTION IF EXISTS production_batch_events_refuse_mutation()');

        Schema::dropIfExists('production_batch_events');
    }
};
