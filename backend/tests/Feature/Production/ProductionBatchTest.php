<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionBatch;
use App\Modules\Production\Models\ProductionBatchItem;
use App\Modules\Production\Models\ProductionItem;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 · FR-074 — production BATCH operations, residual closure. Runs against
 * PostgreSQL (Rule 43); every value is fictional (Rule 23).
 *
 * Covers: lifecycle (create/add/remove/close), server-side RBAC (only
 * production.operate mutates; a cashier/courier/customer/QC cannot), full-path
 * tenant isolation (a foreign batch or item 404s like an absent one — Rule 48),
 * outlet isolation, stage compatibility, eligibility, no duplicate membership,
 * client_reference idempotency (same payload replays, different payload fails
 * closed), optimistic concurrency, closed-batch immutability, and the append-only
 * membership timeline — the DB triggers backing the last two proven directly.
 */
final class ProductionBatchTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    /** @return array{tenant: Tenant, outlet: \App\Modules\Organization\Models\Outlet, order_id: string} */
    private function tenant(string $slug): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id, 'code' => 'CUST-' . Str::upper(Str::random(8)),
            'name' => 'Pelanggan Uji Fiktif', 'phone' => '081200000000',
            'phone_normalized' => '6281200000000', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId, 'tenant_id' => $tenant->id, 'code' => 'SVC-' . Str::upper(Str::random(8)),
            'name' => 'Cuci Kiloan (fiktif)', 'unit_kind' => 'kiloan', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'customer_id' => $customerId,
            'order_number' => 'ALS-' . Str::upper(Str::random(8)), 'client_reference' => (string) Str::uuid(),
            'status' => Order::STATUS_RECEIVED, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'order_id' => $orderId, 'line_number' => 1,
            'service_id' => $serviceId, 'service_name' => 'Cuci Kiloan (fiktif)', 'unit' => 'kilogram',
            'quantity_milli' => 3000, 'unit_price_rupiah' => 8000, 'subtotal_rupiah' => 24000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['tenant' => $tenant, 'outlet' => $outlet, 'order_id' => $orderId];
    }

    private function asRole(Tenant $tenant, string $role): array
    {
        $user = $this->makeUser(self::PASSWORD, Str::lower(Str::random(8)) . '@contoh.fiktif');
        $this->makeMembership($tenant, $user, [$role]);

        return $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);
    }

    /** Headers for an operator WITH an active outlet — the context batch creation needs. */
    private function asOperator(array $t): array
    {
        return array_merge(
            $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR),
            ['X-Outlet-Id' => $t['outlet']->id],
        );
    }

    /** A CREATED job (with SORTING-stage items) for an eligible order. */
    private function createdJob(array $t): ProductionJob
    {
        $user = $this->makeUser(self::PASSWORD, 'setup-' . Str::lower(Str::random(6)) . '@contoh.fiktif');
        $membership = $this->makeMembership($t['tenant'], $user, [PermissionRegistry::ROLE_PRODUCTION_OPERATOR]);
        $context = new TenantContext($t['tenant'], $membership, $t['outlet']);
        $order = Order::query()->forTenant($t['tenant']->id)->findOrFail($t['order_id']);

        return (new ProductionRegistry())->createJobForOrder($context, $order, (string) Str::uuid());
    }

    private function firstItem(ProductionJob $job): ProductionItem
    {
        return ProductionItem::query()->forTenant($job->tenant_id)->where('job_id', $job->id)->firstOrFail();
    }

    /** Create an OPEN batch over HTTP at $stage and return its id. */
    private function makeBatch(array $t, array $headers, string $stage = 'SORTING'): string
    {
        return $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-' . Str::upper(Str::random(8)), 'stage' => $stage, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201)->json('data.batch.id');
    }

    // ---- creation + RBAC -------------------------------------------------

    public function test_operator_creates_an_open_batch(): void
    {
        $t = $this->tenant('batch-create');
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-A1', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $this->asOperator($t))
            ->assertStatus(201)
            ->assertJsonPath('data.batch.status', ProductionBatch::STATUS_OPEN)
            ->assertJsonPath('data.batch.code', 'BATCH-A1');
    }

    public function test_create_requires_an_active_outlet(): void
    {
        $t = $this->tenant('batch-no-outlet');
        // Operator WITHOUT X-Outlet-Id: no active outlet -> 422.
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-NO', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR))
            ->assertStatus(422);
    }

    public function test_cashier_cannot_create_a_batch(): void
    {
        $t = $this->tenant('batch-cashier');
        $headers = array_merge($this->asRole($t['tenant'], PermissionRegistry::ROLE_CASHIER), ['X-Outlet-Id' => $t['outlet']->id]);
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-C', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_courier_cannot_create_a_batch(): void
    {
        $t = $this->tenant('batch-courier');
        $headers = array_merge($this->asRole($t['tenant'], PermissionRegistry::ROLE_COURIER), ['X-Outlet-Id' => $t['outlet']->id]);
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-K', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_quality_control_cannot_operate_but_can_view_batches(): void
    {
        $t = $this->tenant('batch-qc');
        $operator = $this->asOperator($t);
        $this->makeBatch($t, $operator);

        $qc = array_merge($this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL), ['X-Outlet-Id' => $t['outlet']->id]);
        // QC holds production.view but NOT production.operate.
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-QC', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $qc)->assertStatus(403);
        $this->getJson('/api/v1/production/batches', $qc)->assertStatus(200);
    }

    public function test_unauthenticated_batch_request_is_rejected(): void
    {
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-U', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ])->assertStatus(401);
    }

    // ---- tenant / BOLA isolation ----------------------------------------

    public function test_foreign_tenant_batch_is_not_found(): void
    {
        $a = $this->tenant('batch-iso-a');
        $b = $this->tenant('batch-iso-b');
        $batchB = $this->makeBatch($b, $this->asOperator($b));

        // Operator of tenant A cannot see tenant B's batch: 404, not 403 (Rule 48).
        $this->getJson("/api/v1/production/batches/{$batchB}", $this->asOperator($a))->assertStatus(404);
        $this->postJson("/api/v1/production/batches/{$batchB}/close", [
            'client_reference' => (string) Str::uuid(),
        ], $this->asOperator($a))->assertStatus(404);
    }

    public function test_cross_tenant_item_cannot_be_added(): void
    {
        $a = $this->tenant('batch-xt-a');
        $b = $this->tenant('batch-xt-b');
        $batchA = $this->makeBatch($a, $this->asOperator($a));
        $itemB = $this->firstItem($this->createdJob($b));

        // Item belongs to tenant B; from tenant A it does not exist: 404 (Rule 48).
        $this->postJson("/api/v1/production/batches/{$batchA}/items", [
            'production_item_id' => $itemB->id, 'client_reference' => (string) Str::uuid(),
        ], $this->asOperator($a))->assertStatus(404);
    }

    // ---- idempotency -----------------------------------------------------

    public function test_create_is_idempotent_on_client_reference(): void
    {
        $t = $this->tenant('batch-idem-create');
        $headers = $this->asOperator($t);
        $ref = (string) Str::uuid();
        $body = ['code' => 'BATCH-IDEM', 'stage' => 'SORTING', 'client_reference' => $ref];

        $first = $this->postJson('/api/v1/production/batches', $body, $headers)->assertStatus(201)->json('data.batch.id');
        $second = $this->postJson('/api/v1/production/batches', $body, $headers)->assertStatus(201)->json('data.batch.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, ProductionBatch::query()->where('code', 'BATCH-IDEM')->count());
    }

    public function test_create_with_same_reference_and_different_payload_fails_closed(): void
    {
        $t = $this->tenant('batch-idem-diff');
        $headers = $this->asOperator($t);
        $ref = (string) Str::uuid();

        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-ONE', 'stage' => 'SORTING', 'client_reference' => $ref,
        ], $headers)->assertStatus(201);

        // Same reference, different code: reused-different-payload -> 409.
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-TWO', 'stage' => 'SORTING', 'client_reference' => $ref,
        ], $headers)->assertStatus(409)
            ->assertJsonPath('error.details.client_reference.0', 'reused_different_payload');
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $t = $this->tenant('batch-dupe-code');
        $headers = $this->asOperator($t);
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-SAME', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);
        $this->postJson('/api/v1/production/batches', [
            'code' => 'BATCH-SAME', 'stage' => 'SORTING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.code.0', 'already_exists');
    }

    public function test_add_item_is_idempotent(): void
    {
        $t = $this->tenant('batch-add-idem');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $item = $this->firstItem($this->createdJob($t));
        $ref = (string) Str::uuid();
        $body = ['production_item_id' => $item->id, 'client_reference' => $ref];

        $this->postJson("/api/v1/production/batches/{$batch}/items", $body, $headers)->assertStatus(201);
        $this->postJson("/api/v1/production/batches/{$batch}/items", $body, $headers)->assertStatus(201);

        $this->assertSame(1, ProductionBatchItem::query()->where('batch_id', $batch)->count());
    }

    // ---- membership eligibility -----------------------------------------

    public function test_add_eligible_item_succeeds(): void
    {
        $t = $this->tenant('batch-add-ok');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers, 'SORTING');
        $item = $this->firstItem($this->createdJob($t));

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201)->assertJsonPath('data.batch.item_count', 1);
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $t = $this->tenant('batch-dupe-member');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $item = $this->firstItem($this->createdJob($t));

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);
        // Second add with a DIFFERENT reference: already a member -> 409.
        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.production_item_id.0', 'already_member');
    }

    public function test_item_from_a_different_outlet_is_rejected(): void
    {
        $t = $this->tenant('batch-outlet');
        // A second outlet in the same tenant; the batch lives on it.
        $otherOutlet = $this->makeOutlet($t['tenant']);
        $headers = array_merge(
            $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR),
            ['X-Outlet-Id' => $otherOutlet->id],
        );
        $batch = $this->makeBatch($t, $headers, 'SORTING');
        // Item's job is on the DEFAULT outlet, not the batch's outlet.
        $item = $this->firstItem($this->createdJob($t));

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422)->assertJsonPath('error.details.production_item_id.0', 'different_outlet');
    }

    public function test_item_at_a_different_stage_is_rejected(): void
    {
        $t = $this->tenant('batch-stage');
        $headers = $this->asOperator($t);
        // Batch at WASHING; the item is at SORTING.
        $batch = $this->makeBatch($t, $headers, 'WASHING');
        $item = $this->firstItem($this->createdJob($t));

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(422)->assertJsonPath('error.details.production_item_id.0', 'stage_mismatch');
    }

    public function test_item_of_a_terminal_job_is_not_eligible(): void
    {
        $t = $this->tenant('batch-terminal');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers, 'SORTING');
        $job = $this->createdJob($t);
        $item = $this->firstItem($job);
        // Force the job terminal (setup only, not via the registry).
        ProductionJob::query()->whereKey($job->id)->update(['state' => ProductionJob::STATE_ABANDONED]);

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.production_item_id.0', 'not_eligible');
    }

    // ---- removal + append-only history ----------------------------------

    public function test_remove_deletes_current_membership_but_keeps_history(): void
    {
        $t = $this->tenant('batch-remove');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $item = $this->firstItem($this->createdJob($t));

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);
        $this->deleteJson("/api/v1/production/batches/{$batch}/items/{$item->id}", [
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(200)->assertJsonPath('data.batch.item_count', 0);

        // Current membership is gone...
        $this->assertSame(0, ProductionBatchItem::query()->where('batch_id', $batch)->count());
        // ...but the timeline preserves both the add and the remove (append-only).
        $timeline = $this->getJson("/api/v1/production/batches/{$batch}/timeline", $headers)->json('data.timeline');
        $types = array_column($timeline, 'type');
        $this->assertContains('BatchItemAdded', $types);
        $this->assertContains('BatchItemRemoved', $types);
    }

    public function test_removing_a_non_member_is_not_found(): void
    {
        $t = $this->tenant('batch-remove-none');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);

        $this->deleteJson("/api/v1/production/batches/{$batch}/items/" . Str::uuid(), [
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(404);
    }

    // ---- closing + immutability -----------------------------------------

    public function test_close_marks_the_batch_closed(): void
    {
        $t = $this->tenant('batch-close');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);

        $this->postJson("/api/v1/production/batches/{$batch}/close", [
            'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(200)->assertJsonPath('data.batch.status', ProductionBatch::STATUS_CLOSED);
    }

    public function test_cannot_add_to_a_closed_batch(): void
    {
        $t = $this->tenant('batch-closed-add');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $item = $this->firstItem($this->createdJob($t));
        $this->postJson("/api/v1/production/batches/{$batch}/close", ['client_reference' => (string) Str::uuid()], $headers)->assertStatus(200);

        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.status.0', 'batch_closed');
    }

    public function test_cannot_close_an_already_closed_batch_twice_but_replay_is_idempotent(): void
    {
        $t = $this->tenant('batch-close-twice');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $ref = (string) Str::uuid();

        $this->postJson("/api/v1/production/batches/{$batch}/close", ['client_reference' => $ref], $headers)->assertStatus(200);
        // Same reference: exactly-once replay -> still 200, one BatchClosed event.
        $this->postJson("/api/v1/production/batches/{$batch}/close", ['client_reference' => $ref], $headers)->assertStatus(200);
        // A DIFFERENT reference against a closed batch is a state conflict -> 409.
        $this->postJson("/api/v1/production/batches/{$batch}/close", ['client_reference' => (string) Str::uuid()], $headers)
            ->assertStatus(409)->assertJsonPath('error.details.status.0', 'batch_closed');
    }

    public function test_stale_expected_version_on_close_conflicts(): void
    {
        $t = $this->tenant('batch-stale');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);

        $this->postJson("/api/v1/production/batches/{$batch}/close", [
            'expected_version' => 999, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(409)->assertJsonPath('error.details.version.0', 'version_conflict');
    }

    // ---- listing / detail ------------------------------------------------

    public function test_list_is_scoped_to_the_active_outlet(): void
    {
        $t = $this->tenant('batch-list');
        $headers = $this->asOperator($t);
        $this->makeBatch($t, $headers);
        $this->makeBatch($t, $headers);

        $this->getJson('/api/v1/production/batches', $headers)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['batches'], 'meta' => ['total']])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_show_returns_members_and_timeline(): void
    {
        $t = $this->tenant('batch-show');
        $headers = $this->asOperator($t);
        $batch = $this->makeBatch($t, $headers);
        $item = $this->firstItem($this->createdJob($t));
        $this->postJson("/api/v1/production/batches/{$batch}/items", [
            'production_item_id' => $item->id, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(201);

        $this->getJson("/api/v1/production/batches/{$batch}", $headers)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['batch' => ['items'], 'timeline']])
            ->assertJsonPath('data.batch.item_count', 1);
    }

    // ---- DB-level invariants (the engine, not the app) ------------------

    public function test_batch_events_table_is_append_only_on_update(): void
    {
        $t = $this->tenant('batch-db-append');
        $batch = $this->makeBatch($t, $this->asOperator($t));

        $this->expectException(QueryException::class); // refuse UPDATE
        DB::table('production_batch_events')->where('batch_id', $batch)->update(['type' => 'Tampered']);
    }

    public function test_batch_events_table_is_append_only_on_delete(): void
    {
        $t = $this->tenant('batch-db-delete');
        $batch = $this->makeBatch($t, $this->asOperator($t));

        $this->expectException(QueryException::class); // refuse DELETE
        DB::table('production_batch_events')->where('batch_id', $batch)->delete();
    }

    public function test_batch_command_client_reference_is_unique_at_the_engine(): void
    {
        $t = $this->tenant('batch-db-idem');
        $batch = $this->makeBatch($t, $this->asOperator($t));
        $ref = (string) Str::uuid();
        $row = fn () => [
            'id' => (string) Str::uuid(), 'tenant_id' => $t['tenant']->id, 'batch_id' => $batch,
            'type' => 'BatchItemAdded', 'payload' => '{}', 'client_reference' => $ref,
            'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('production_batch_events')->insert($row());

        $this->expectException(QueryException::class); // UNIQUE (tenant_id, client_reference)
        DB::table('production_batch_events')->insert($row());
    }
}
