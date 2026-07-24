<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT A (persistence).
 *
 * Runtime scope opened by DEC-0037. This migration introduces the production
 * aggregate tables and enforces, AT THE DATABASE BOUNDARY, the invariants Step 6
 * cannot leave to the application alone:
 *
 *   - tenant isolation via COMPOSITE foreign keys (tenant_id, x_id) -> parent
 *     (tenant_id, id); a row in tenant A cannot reference a row in tenant B
 *     (Rule 02, Rule 48);
 *   - the FIRST READY_FOR_PICKUP fact is recorded exactly ONCE and is IMMUTABLE
 *     (production_ready_events: UNIQUE (tenant_id, order_id) + append-only
 *     triggers) — the aging anchor Step 9 will read, which must never restart
 *     (FR-076, FR-077, Rule 10, invariant 17);
 *   - production history and QC verdicts are APPEND-ONLY (Rule 04-shaped
 *     immutability for canonical facts; FR-084);
 *   - a CLOSED batch is immutable and cannot gain members (FR-074);
 *   - server-side idempotency: UNIQUE (tenant_id, client_reference) on
 *     production_events makes a replayed command exactly-once (FR-079, Rule 07).
 *
 * No money column is introduced here; the historical integer-Rupiah price
 * snapshot on the order is never written by any production statement (Rule 04,
 * FR-036). Every table carries tenant_id from this, its introducing migration.
 */
return new class extends Migration
{
    /** @var list<string> Canonical production job states (PRODUCTION_STATE_MACHINE.md §1). */
    private array $jobStates = [
        'CREATED', 'IN_PROGRESS', 'BLOCKED', 'AWAITING_QC',
        'REWORK_IN_PROGRESS', 'CLOSED', 'ABANDONED',
    ];

    /** @var list<string> Canonical QC verdicts (QUALITY_CONTROL_STATE_MACHINE.md). */
    private array $qcVerdicts = [
        'PENDING', 'PASSED', 'FAILED_REWORK_REQUIRED', 'WAIVED_WITH_AUTHORIZATION',
    ];

    public function up(): void
    {
        $this->createProductionJobs();
        $this->createProductionBatches();
        $this->createProductionItems();
        $this->createProductionBatchItems();
        $this->createOperatorAssignments();
        $this->createQualityControlInspections();
        $this->createReworkCycles();
        $this->createProductionEvents();
        $this->createProductionReadyEvents();
        $this->installImmutabilityTriggers();
    }

    private function createProductionJobs(): void
    {
        Schema::create('production_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');
            $table->uuid('order_id');
            $table->string('state', 32)->default('CREATED');
            $table->integer('version')->default(1);
            $table->string('block_reason_code', 64)->nullable();
            $table->text('block_reason')->nullable();
            $table->uuid('created_by_membership_id')->nullable();
            $table->uuid('updated_by_membership_id')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id', 'production_jobs_tenant_id_index');
            $table->index(['tenant_id', 'outlet_id'], 'production_jobs_tenant_outlet_index');
            $table->index(['tenant_id', 'state'], 'production_jobs_tenant_state_index');
        });

        $states = $this->sqlList($this->jobStates);
        DB::statement("ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_tenant_id_id_unique UNIQUE (tenant_id, id)");
        DB::statement("ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_state_check CHECK (state IN ({$states}))");
        DB::statement("ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_tenant_outlet_foreign FOREIGN KEY (tenant_id, outlet_id) REFERENCES outlets (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_tenant_order_foreign FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT");
        // At most ONE non-terminal (open) production job per order.
        DB::statement("CREATE UNIQUE INDEX production_jobs_one_open_per_order ON production_jobs (order_id) WHERE state NOT IN ('CLOSED', 'ABANDONED')");
    }

    private function createProductionBatches(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');
            $table->string('code', 64);
            $table->string('stage', 32);
            $table->string('status', 16)->default('open');
            $table->integer('version')->default(1);
            $table->uuid('created_by_membership_id')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code'], 'production_batches_tenant_code_unique');
            $table->index(['tenant_id', 'outlet_id'], 'production_batches_tenant_outlet_index');
        });

        DB::statement("ALTER TABLE production_batches ADD CONSTRAINT production_batches_tenant_id_id_unique UNIQUE (tenant_id, id)");
        DB::statement("ALTER TABLE production_batches ADD CONSTRAINT production_batches_status_check CHECK (status IN ('open', 'closed'))");
        DB::statement("ALTER TABLE production_batches ADD CONSTRAINT production_batches_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_batches ADD CONSTRAINT production_batches_tenant_outlet_foreign FOREIGN KEY (tenant_id, outlet_id) REFERENCES outlets (tenant_id, id) ON DELETE RESTRICT");
    }

    private function createProductionItems(): void
    {
        Schema::create('production_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->uuid('order_line_id');
            $table->string('service_type', 16); // 'kiloan' | 'satuan'
            $table->string('stage', 32)->default('SORTING');
            $table->decimal('quantity_done', 12, 3)->default(0); // kiloan progress (kg); exact, never float
            $table->integer('units_total')->default(1);          // satuan piece count
            $table->integer('units_done')->default(0);
            $table->jsonb('flags')->default('{}');               // discrete satuan per-unit flags (FR-075)
            $table->integer('version')->default(1);
            $table->timestampsTz();

            $table->index(['tenant_id', 'job_id'], 'production_items_tenant_job_index');
        });

        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_tenant_id_id_unique UNIQUE (tenant_id, id)");
        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_service_type_check CHECK (service_type IN ('kiloan', 'satuan'))");
        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_qty_nonneg_check CHECK (quantity_done >= 0 AND units_done >= 0 AND units_done <= units_total)");
        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
        // order_line_id: regular FK (order_lines has no (tenant_id,id) composite);
        // tenant integrity is guaranteed by the composite job FK above.
        DB::statement("ALTER TABLE production_items ADD CONSTRAINT production_items_order_line_foreign FOREIGN KEY (order_line_id) REFERENCES order_lines (id) ON DELETE RESTRICT");
    }

    private function createProductionBatchItems(): void
    {
        Schema::create('production_batch_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('batch_id');
            $table->uuid('production_item_id');
            $table->timestampsTz();

            $table->unique(['batch_id', 'production_item_id'], 'production_batch_items_membership_unique');
            $table->index(['tenant_id', 'batch_id'], 'production_batch_items_tenant_batch_index');
        });

        DB::statement("ALTER TABLE production_batch_items ADD CONSTRAINT production_batch_items_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        // Batch and item BOTH bound to the SAME tenant via composite FKs.
        DB::statement("ALTER TABLE production_batch_items ADD CONSTRAINT production_batch_items_tenant_batch_foreign FOREIGN KEY (tenant_id, batch_id) REFERENCES production_batches (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_batch_items ADD CONSTRAINT production_batch_items_tenant_item_foreign FOREIGN KEY (tenant_id, production_item_id) REFERENCES production_items (tenant_id, id) ON DELETE RESTRICT");
    }

    private function createOperatorAssignments(): void
    {
        Schema::create('production_operator_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->uuid('membership_id');
            $table->string('role', 32);
            $table->timestampTz('assigned_at');
            $table->uuid('assigned_by_membership_id')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'job_id'], 'prod_assignments_tenant_job_index');
        });

        DB::statement("ALTER TABLE production_operator_assignments ADD CONSTRAINT prod_assignments_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_operator_assignments ADD CONSTRAINT prod_assignments_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_operator_assignments ADD CONSTRAINT prod_assignments_tenant_membership_foreign FOREIGN KEY (tenant_id, membership_id) REFERENCES memberships (tenant_id, id) ON DELETE RESTRICT");
    }

    private function createQualityControlInspections(): void
    {
        Schema::create('quality_control_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->string('verdict', 32);
            $table->string('defect_reason_code', 64)->nullable();
            $table->text('defect_reason')->nullable();
            $table->uuid('inspector_membership_id');
            $table->string('evidence_path')->nullable(); // private object-storage key; signed-URL only (FR-083)
            $table->timestampTz('inspected_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'job_id'], 'qc_inspections_tenant_job_index');
        });

        $verdicts = $this->sqlList($this->qcVerdicts);
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_tenant_id_id_unique UNIQUE (tenant_id, id)");
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_verdict_check CHECK (verdict IN ({$verdicts}))");
        // A FAILED verdict must carry a defect reason (FR-082).
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_fail_reason_check CHECK (verdict <> 'FAILED_REWORK_REQUIRED' OR defect_reason_code IS NOT NULL)");
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE quality_control_inspections ADD CONSTRAINT qc_inspections_tenant_inspector_foreign FOREIGN KEY (tenant_id, inspector_membership_id) REFERENCES memberships (tenant_id, id) ON DELETE RESTRICT");
    }

    private function createReworkCycles(): void
    {
        Schema::create('rework_cycles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->uuid('source_inspection_id');
            $table->integer('cycle_no');
            $table->string('reason_code', 64);
            $table->text('reason')->nullable();
            $table->string('stage', 32)->nullable();
            $table->uuid('started_by_membership_id')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['job_id', 'cycle_no'], 'rework_cycles_job_cycle_unique');
            $table->index(['tenant_id', 'job_id'], 'rework_cycles_tenant_job_index');
        });

        DB::statement("ALTER TABLE rework_cycles ADD CONSTRAINT rework_cycles_cycle_positive_check CHECK (cycle_no >= 1)");
        DB::statement("ALTER TABLE rework_cycles ADD CONSTRAINT rework_cycles_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE rework_cycles ADD CONSTRAINT rework_cycles_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE rework_cycles ADD CONSTRAINT rework_cycles_tenant_inspection_foreign FOREIGN KEY (tenant_id, source_inspection_id) REFERENCES quality_control_inspections (tenant_id, id) ON DELETE RESTRICT");
    }

    private function createProductionEvents(): void
    {
        Schema::create('production_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('job_id');
            $table->string('type', 64);
            $table->uuid('actor_membership_id')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->uuid('client_reference')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'job_id'], 'production_events_tenant_job_index');
        });

        DB::statement("ALTER TABLE production_events ADD CONSTRAINT production_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_events ADD CONSTRAINT production_events_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
        // Server-side idempotency key: a replayed command with the same reference
        // is caught here and returns the original effect (FR-079, Rule 07/20).
        DB::statement("CREATE UNIQUE INDEX production_events_tenant_client_ref_unique ON production_events (tenant_id, client_reference) WHERE client_reference IS NOT NULL");
    }

    private function createProductionReadyEvents(): void
    {
        // The IMMUTABLE first-ready anchor (FR-076, FR-077). One row per order,
        // written once, never updated or deleted. Step 9 reads occurred_at; it is
        // NOT computed here (no aging, no ladder — that is Step 9).
        Schema::create('production_ready_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->uuid('job_id');
            $table->uuid('recorded_by_membership_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
        });

        // UNIQUE (tenant_id, order_id): the first-ready fact exists at most once.
        DB::statement("ALTER TABLE production_ready_events ADD CONSTRAINT production_ready_events_tenant_order_unique UNIQUE (tenant_id, order_id)");
        DB::statement("ALTER TABLE production_ready_events ADD CONSTRAINT production_ready_events_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_ready_events ADD CONSTRAINT production_ready_events_tenant_order_foreign FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT");
        DB::statement("ALTER TABLE production_ready_events ADD CONSTRAINT production_ready_events_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT");
    }

    private function installImmutabilityTriggers(): void
    {
        // production_events: append-only canonical history (FR-084).
        $this->appendOnly('production_events', 'production history is append-only (FR-084)');
        // production_ready_events: the first-ready anchor is immutable (FR-076/077).
        $this->appendOnly('production_ready_events', 'the first READY_FOR_PICKUP fact is immutable (FR-076, FR-077)');
        // quality_control_inspections: a recorded verdict is never rewritten
        // (a re-inspection is a NEW row). Refuse UPDATE and DELETE (FR-082/084).
        $this->appendOnly('quality_control_inspections', 'a recorded QC verdict is append-only; re-inspection is a new row (FR-082, FR-084)');

        // A CLOSED batch is immutable, and cannot gain members (FR-074).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION production_batches_refuse_closed_mutation()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'closed' THEN
                    RAISE EXCEPTION 'production_batches: a closed batch is immutable (FR-074).'
                        USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END; $$
        SQL);
        DB::statement("CREATE TRIGGER production_batches_refuse_closed_update BEFORE UPDATE ON production_batches FOR EACH ROW WHEN (OLD.status = 'closed') EXECUTE FUNCTION production_batches_refuse_closed_mutation()");

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION production_batch_items_refuse_closed_batch()
            RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE batch_status text;
            BEGIN
                SELECT status INTO batch_status FROM production_batches WHERE id = NEW.batch_id;
                IF batch_status = 'closed' THEN
                    RAISE EXCEPTION 'production_batch_items: cannot add to a closed batch (FR-074).'
                        USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END; $$
        SQL);
        DB::statement("CREATE TRIGGER production_batch_items_refuse_closed_batch_insert BEFORE INSERT ON production_batch_items FOR EACH ROW EXECUTE FUNCTION production_batch_items_refuse_closed_batch()");
    }

    /** Install refuse-UPDATE / refuse-DELETE / refuse-TRUNCATE triggers on a table. */
    private function appendOnly(string $table, string $why): void
    {
        $fn = "{$table}_refuse_mutation";
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$fn}()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION '{$table} is append-only: % is refused — {$why}.', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END; \$\$
        SQL);
        DB::statement("CREATE TRIGGER {$table}_refuse_update BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER {$table}_refuse_delete BEFORE DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER {$table}_refuse_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION {$fn}()");
    }

    /** Turn a PHP list into a quoted SQL IN-list. */
    private function sqlList(array $values): string
    {
        return implode(', ', array_map(static fn (string $v): string => "'{$v}'", $values));
    }

    public function down(): void
    {
        foreach ([
            'production_events', 'production_ready_events', 'quality_control_inspections',
        ] as $t) {
            DB::statement("DROP TRIGGER IF EXISTS {$t}_refuse_update ON {$t}");
            DB::statement("DROP TRIGGER IF EXISTS {$t}_refuse_delete ON {$t}");
            DB::statement("DROP TRIGGER IF EXISTS {$t}_refuse_truncate ON {$t}");
            DB::statement("DROP FUNCTION IF EXISTS {$t}_refuse_mutation()");
        }
        DB::statement("DROP TRIGGER IF EXISTS production_batches_refuse_closed_update ON production_batches");
        DB::statement("DROP FUNCTION IF EXISTS production_batches_refuse_closed_mutation()");
        DB::statement("DROP TRIGGER IF EXISTS production_batch_items_refuse_closed_batch_insert ON production_batch_items");
        DB::statement("DROP FUNCTION IF EXISTS production_batch_items_refuse_closed_batch()");

        Schema::dropIfExists('production_ready_events');
        Schema::dropIfExists('production_events');
        Schema::dropIfExists('rework_cycles');
        Schema::dropIfExists('quality_control_inspections');
        Schema::dropIfExists('production_operator_assignments');
        Schema::dropIfExists('production_batch_items');
        Schema::dropIfExists('production_items');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('production_jobs');
    }
};
