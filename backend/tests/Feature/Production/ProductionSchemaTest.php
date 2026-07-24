<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT A (persistence), tested against the LIVE
 * PostgreSQL database (Rule 43). Every invariant here is enforced by a database
 * constraint or trigger, not by application validation, so a code path that
 * forgets to validate still cannot write a row the production guarantees depend
 * on being impossible.
 *
 * These are the negative tests that make "the production schema is correct" a
 * checkable claim: each asserts the ENGINE rejects a bad row. Every value is
 * fictional (Rule 23).
 */
final class ProductionSchemaTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    /** @return array{tenant_id:string,outlet_id:string,order_id:string,order_line_id:string,job_id:string,membership_id:string} */
    private function scenario(string $slug): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);
        $user = $this->makeUser(email: $slug . '@contoh.fiktif');
        $membership = $this->makeMembership($tenant, $user);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id,
            'code' => 'CUST-' . Str::upper(Str::random(8)),
            'name' => 'Pelanggan Uji Fiktif', 'phone' => '081200000000',
            'phone_normalized' => '6281200000000', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id,
            'customer_id' => $customerId, 'order_number' => 'ALS-' . Str::upper(Str::random(8)),
            'client_reference' => (string) Str::uuid(), 'status' => 'RECEIVED',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId, 'tenant_id' => $tenant->id,
            'code' => 'SVC-' . Str::upper(Str::random(8)),
            'name' => 'Cuci Kiloan Reguler (fiktif)', 'unit_kind' => 'kiloan',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderLineId = (string) Str::uuid();
        DB::table('order_lines')->insert([
            'id' => $orderLineId, 'tenant_id' => $tenant->id, 'order_id' => $orderId,
            'line_number' => 1, 'service_id' => $serviceId,
            'service_name' => 'Cuci Kiloan (fiktif)', 'unit' => 'kilogram',
            'quantity_milli' => 3000, 'unit_price_rupiah' => 8000, 'subtotal_rupiah' => 24000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $jobId = $this->insertJob($tenant->id, $outlet->id, $orderId);

        return [
            'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'order_id' => $orderId,
            'order_line_id' => $orderLineId, 'job_id' => $jobId, 'membership_id' => $membership->id,
        ];
    }

    private function insertJob(string $tenantId, string $outletId, string $orderId, string $state = 'CREATED'): string
    {
        $id = (string) Str::uuid();
        DB::table('production_jobs')->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'outlet_id' => $outletId,
            'order_id' => $orderId, 'state' => $state, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_all_production_tables_exist(): void
    {
        foreach ([
            'production_jobs', 'production_items', 'production_batches', 'production_batch_items',
            'production_operator_assignments', 'quality_control_inspections', 'rework_cycles',
            'production_events', 'production_ready_events',
        ] as $table) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($table), "missing table {$table}");
        }
    }

    public function test_only_one_open_job_per_order(): void
    {
        $s = $this->scenario('one-open-job');
        $this->expectException(QueryException::class);
        // A second OPEN job for the same order violates the partial unique index.
        $this->insertJob($s['tenant_id'], $s['outlet_id'], $s['order_id']);
    }

    public function test_production_job_cannot_reference_another_tenants_order(): void
    {
        $a = $this->scenario('tenant-a');
        $b = $this->scenario('tenant-b');
        $this->expectException(QueryException::class);
        // Job in tenant A referencing tenant B's order: composite FK refuses.
        DB::table('production_jobs')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $a['tenant_id'], 'outlet_id' => $a['outlet_id'],
            'order_id' => $b['order_id'], 'state' => 'CREATED', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertReady(array $s): void
    {
        DB::table('production_ready_events')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $s['tenant_id'], 'order_id' => $s['order_id'],
            'job_id' => $s['job_id'], 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_first_ready_event_is_recorded_at_most_once(): void
    {
        $s = $this->scenario('ready-once');
        $this->insertReady($s);
        $this->expectException(QueryException::class); // UNIQUE (tenant_id, order_id)
        $this->insertReady($s);
    }

    public function test_first_ready_event_is_immutable_update(): void
    {
        $s = $this->scenario('ready-immutable');
        $this->insertReady($s);
        $this->expectException(QueryException::class); // append-only trigger refuses UPDATE
        DB::table('production_ready_events')->where('order_id', $s['order_id'])
            ->update(['occurred_at' => now()->addDay()]);
    }

    public function test_first_ready_event_cannot_be_deleted(): void
    {
        $s = $this->scenario('ready-nodelete');
        $this->insertReady($s);
        $this->expectException(QueryException::class); // append-only trigger refuses DELETE
        DB::table('production_ready_events')->where('order_id', $s['order_id'])->delete();
    }

    private function insertEvent(array $s, ?string $clientRef, string $type = 'ProductionStageStarted'): void
    {
        DB::table('production_events')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $s['tenant_id'], 'job_id' => $s['job_id'],
            'type' => $type, 'payload' => '{}', 'client_reference' => $clientRef,
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_production_event_is_append_only(): void
    {
        $s = $this->scenario('events-append');
        $this->insertEvent($s, null);
        $this->expectException(QueryException::class); // refuse UPDATE
        DB::table('production_events')->where('job_id', $s['job_id'])->update(['type' => 'Tampered']);
    }

    public function test_duplicate_client_reference_is_refused(): void
    {
        $s = $this->scenario('idempotency');
        $ref = (string) Str::uuid();
        $this->insertEvent($s, $ref);
        $this->expectException(QueryException::class); // UNIQUE (tenant_id, client_reference)
        $this->insertEvent($s, $ref);
    }

    public function test_closed_batch_refuses_new_members(): void
    {
        $s = $this->scenario('closed-batch');

        $itemId = (string) Str::uuid();
        DB::table('production_items')->insert([
            'id' => $itemId, 'tenant_id' => $s['tenant_id'], 'job_id' => $s['job_id'],
            'order_line_id' => $s['order_line_id'], 'service_type' => 'kiloan',
            'stage' => 'SORTING', 'quantity_done' => 0, 'units_total' => 1, 'units_done' => 0,
            'flags' => '{}', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $batchId = (string) Str::uuid();
        DB::table('production_batches')->insert([
            'id' => $batchId, 'tenant_id' => $s['tenant_id'], 'outlet_id' => $s['outlet_id'],
            'code' => 'BATCH-' . Str::upper(Str::random(6)), 'stage' => 'WASHING',
            'status' => 'closed', 'version' => 1, 'closed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class); // closed-batch trigger refuses INSERT
        DB::table('production_batch_items')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $s['tenant_id'], 'batch_id' => $batchId,
            'production_item_id' => $itemId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_qc_verdict_is_append_only(): void
    {
        $s = $this->scenario('qc-append');
        $qcId = (string) Str::uuid();
        DB::table('quality_control_inspections')->insert([
            'id' => $qcId, 'tenant_id' => $s['tenant_id'], 'job_id' => $s['job_id'],
            'verdict' => 'FAILED_REWORK_REQUIRED', 'defect_reason_code' => 'NODA',
            'defect_reason' => 'Masih ada noda (fiktif).', 'inspector_membership_id' => $s['membership_id'],
            'inspected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class); // cannot rewrite a recorded verdict fail -> pass
        DB::table('quality_control_inspections')->where('id', $qcId)->update(['verdict' => 'PASSED']);
    }

    public function test_failed_qc_requires_a_defect_reason(): void
    {
        $s = $this->scenario('qc-reason');
        $this->expectException(QueryException::class); // CHECK: FAILED verdict needs defect_reason_code
        DB::table('quality_control_inspections')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $s['tenant_id'], 'job_id' => $s['job_id'],
            'verdict' => 'FAILED_REWORK_REQUIRED', 'defect_reason_code' => null, 'defect_reason' => null,
            'inspector_membership_id' => $s['membership_id'], 'inspected_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
