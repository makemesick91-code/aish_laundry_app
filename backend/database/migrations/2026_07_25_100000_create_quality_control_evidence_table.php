<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * STEP 6 — PRODUCTION OPERATIONS · FR-083 QC defect-photo evidence, residual
 * closure.
 *
 * A metadata row per stored defect photo. The BYTES live in a PRIVATE
 * S3-compatible object store (MinIO in development) under a random, non-guessable
 * key; this row records everything needed to authorise, audit, and retrieve them
 * WITHOUT ever exposing a permanent public URL (Rule 03 hard rules 13-14, Rule 06
 * hard rules 16-17, owner constraints).
 *
 *   - tenant/outlet isolation: composite FKs bind the row to a job in the SAME
 *     tenant, so evidence can never reference another tenant's job (Rule 02/48);
 *   - APPEND-ONLY audit: the refuse-UPDATE/DELETE/TRUNCATE triggers make the
 *     evidence trail immutable (Rule 46, FR-084-shaped);
 *   - IDEMPOTENCY: the partial UNIQUE (tenant_id, client_reference) makes a
 *     retried upload exactly-once — a replay returns the original row and stores
 *     no second object (Rule 07/20);
 *   - INTEGRITY: checksum_sha256 pins the exact bytes stored; content_type is the
 *     SERVER-DETECTED type, never the client's declared one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A composite (tenant_id, id) key on the inspection so evidence can bind
        // to it with a tenant-safe composite FK (the inspection table did not
        // carry one; jobs/items/batches already do).
        DB::statement('ALTER TABLE quality_control_inspections ADD CONSTRAINT quality_control_inspections_tenant_id_id_unique UNIQUE (tenant_id, id)');

        Schema::create('quality_control_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('outlet_id');
            $table->uuid('job_id');
            $table->uuid('inspection_id');
            $table->uuid('uploaded_by_membership_id')->nullable();
            $table->string('content_type', 64);      // SERVER-DETECTED, not client-declared
            $table->bigInteger('byte_size');
            $table->char('checksum_sha256', 64);
            $table->string('storage_key', 512);       // random, non-guessable
            $table->string('status', 16)->default('stored');
            $table->uuid('client_reference')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['tenant_id', 'inspection_id'], 'qc_evidence_tenant_inspection_index');
        });

        DB::statement("ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_size_positive_check CHECK (byte_size > 0)");
        DB::statement("ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_status_check CHECK (status IN ('stored'))");
        DB::statement('ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT');
        // Both the job and the inspection are bound to the SAME tenant.
        DB::statement('ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_tenant_job_foreign FOREIGN KEY (tenant_id, job_id) REFERENCES production_jobs (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_tenant_inspection_foreign FOREIGN KEY (tenant_id, inspection_id) REFERENCES quality_control_inspections (tenant_id, id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE quality_control_evidence ADD CONSTRAINT qc_evidence_tenant_outlet_foreign FOREIGN KEY (tenant_id, outlet_id) REFERENCES outlets (tenant_id, id) ON DELETE RESTRICT');
        // Server-side idempotency key: a replayed upload returns the original.
        DB::statement('CREATE UNIQUE INDEX qc_evidence_tenant_client_ref_unique ON quality_control_evidence (tenant_id, client_reference) WHERE client_reference IS NOT NULL');

        // Append-only: the evidence audit trail is never rewritten.
        $fn = 'quality_control_evidence_refuse_mutation';
        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$fn}()
            RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                RAISE EXCEPTION 'quality_control_evidence is append-only: % is refused (FR-083, Rule 46).', TG_OP
                    USING ERRCODE = 'restrict_violation';
            END; \$\$
        SQL);
        DB::statement("CREATE TRIGGER quality_control_evidence_refuse_update BEFORE UPDATE ON quality_control_evidence FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER quality_control_evidence_refuse_delete BEFORE DELETE ON quality_control_evidence FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        DB::statement("CREATE TRIGGER quality_control_evidence_refuse_truncate BEFORE TRUNCATE ON quality_control_evidence FOR EACH STATEMENT EXECUTE FUNCTION {$fn}()");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS quality_control_evidence_refuse_update ON quality_control_evidence');
        DB::statement('DROP TRIGGER IF EXISTS quality_control_evidence_refuse_delete ON quality_control_evidence');
        DB::statement('DROP TRIGGER IF EXISTS quality_control_evidence_refuse_truncate ON quality_control_evidence');
        DB::statement('DROP FUNCTION IF EXISTS quality_control_evidence_refuse_mutation()');

        Schema::dropIfExists('quality_control_evidence');

        DB::statement('ALTER TABLE quality_control_inspections DROP CONSTRAINT IF EXISTS quality_control_inspections_tenant_id_id_unique');
    }
};
