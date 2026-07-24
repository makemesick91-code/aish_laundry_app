<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionItem;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 — PRODUCTION OPERATIONS · UNIT B. The production state-machine service,
 * against live PostgreSQL (Rule 43). The server is authoritative: a client sends
 * a command, never a next-state; an unenumerated transition, a stale version, a
 * cross-tenant target, or a reused reference with a changed payload all fail
 * closed and change nothing. Every value is fictional (Rule 23).
 */
final class ProductionRegistryTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private ProductionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ProductionRegistry();
    }

    /** @return array{context: TenantContext, order: Order} */
    private function scenario(string $slug, string $status = Order::STATUS_RECEIVED): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);
        $user = $this->makeUser(email: $slug . '@contoh.fiktif');
        $membership = $this->makeMembership($tenant, $user);

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
        $orderRow = [
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id, 'customer_id' => $customerId,
            'order_number' => 'ALS-' . Str::upper(Str::random(8)), 'client_reference' => (string) Str::uuid(),
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ];
        if ($status === Order::STATUS_CANCELLED) {
            // orders_cancellation_consistent: a CANCELLED order carries its
            // cancellation facts (Rule 04 / FR-058).
            $orderRow['cancelled_at'] = now();
            $orderRow['cancellation_reason'] = 'Dibatalkan pelanggan (fiktif).';
            $orderRow['cancelled_by_membership_id'] = $membership->id;
        }
        DB::table('orders')->insert($orderRow);
        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'order_id' => $orderId, 'line_number' => 1,
            'service_id' => $serviceId, 'service_name' => 'Cuci Kiloan (fiktif)', 'unit' => 'kilogram',
            'quantity_milli' => 3000, 'unit_price_rupiah' => 8000, 'subtotal_rupiah' => 24000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'context' => new TenantContext($tenant, $membership, $outlet),
            'order' => Order::query()->forTenant($tenant->id)->findOrFail($orderId),
        ];
    }

    private function ref(): string
    {
        return (string) Str::uuid();
    }

    public function test_creates_job_and_items_from_a_received_order(): void
    {
        $s = $this->scenario('create-job');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());

        $this->assertSame(ProductionJob::STATE_CREATED, $job->state);
        $this->assertSame($s['order']->id, $job->order_id);
        $this->assertSame(1, ProductionItem::query()->where('job_id', $job->id)->count());
        $this->assertSame(ProductionItem::SERVICE_KILOAN, ProductionItem::query()->where('job_id', $job->id)->first()->service_type);
    }

    public function test_job_creation_is_idempotent_per_order(): void
    {
        $s = $this->scenario('idempotent-create');
        $first = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $second = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProductionJob::query()->where('order_id', $s['order']->id)->count());
    }

    public function test_a_cancelled_order_cannot_enter_production(): void
    {
        $s = $this->scenario('cancelled', Order::STATUS_CANCELLED);
        $this->expectException(ApiException::class);
        $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
    }

    public function test_cannot_create_job_for_another_tenants_order(): void
    {
        $a = $this->scenario('tenant-a');
        $b = $this->scenario('tenant-b');
        $this->expectException(ApiException::class); // NOT_FOUND — indistinguishable from absent
        $this->registry->createJobForOrder($a['context'], $b['order'], $this->ref());
    }

    public function test_start_stage_moves_created_to_in_progress_and_bumps_version(): void
    {
        $s = $this->scenario('start');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $v = (int) $job->version;

        $job = $this->registry->startStage($s['context'], $job->id, $v, $this->ref(), 'WASHING');

        $this->assertSame(ProductionJob::STATE_IN_PROGRESS, $job->state);
        $this->assertSame($v + 1, (int) $job->version);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $s = $this->scenario('invalid');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        // RESUME is not enumerated from CREATED.
        $this->expectException(ApiException::class);
        $this->registry->resume($s['context'], $job->id, (int) $job->version, $this->ref());
    }

    public function test_block_requires_a_reason_code(): void
    {
        $s = $this->scenario('block-reason');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $job = $this->registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'WASHING');

        $this->expectException(ApiException::class);
        $this->registry->applyCommand($s['context'], $job->id, ProductionRegistry::CMD_BLOCK, (int) $job->version, $this->ref(), []);
    }

    public function test_block_then_resume_round_trip(): void
    {
        $s = $this->scenario('block-resume');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $job = $this->registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'WASHING');
        $job = $this->registry->block($s['context'], $job->id, (int) $job->version, $this->ref(), 'MESIN_RUSAK', 'Mesin cuci mati (fiktif).');
        $this->assertSame(ProductionJob::STATE_BLOCKED, $job->state);
        $this->assertSame('MESIN_RUSAK', $job->block_reason_code);

        $job = $this->registry->resume($s['context'], $job->id, (int) $job->version, $this->ref());
        $this->assertSame(ProductionJob::STATE_IN_PROGRESS, $job->state);
        $this->assertNull($job->block_reason_code);
    }

    public function test_send_to_quality_control(): void
    {
        $s = $this->scenario('to-qc');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $job = $this->registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'FINISHING');
        $job = $this->registry->sendToQualityControl($s['context'], $job->id, (int) $job->version, $this->ref());
        $this->assertSame(ProductionJob::STATE_AWAITING_QC, $job->state);
    }

    public function test_replay_with_same_reference_is_exactly_once(): void
    {
        $s = $this->scenario('replay');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $ref = $this->ref();

        $job = $this->registry->applyCommand($s['context'], $job->id, ProductionRegistry::CMD_START_STAGE, (int) $job->version, $ref, ['stage' => 'WASHING']);
        $versionAfterFirst = (int) $job->version;
        $eventsAfterFirst = ProductionEvent::query()->where('job_id', $job->id)->count();

        // Replay: same reference, same payload -> original result, NO second effect.
        $again = $this->registry->applyCommand($s['context'], $job->id, ProductionRegistry::CMD_START_STAGE, null, $ref, ['stage' => 'WASHING']);

        $this->assertSame($versionAfterFirst, (int) $again->fresh()->version);
        $this->assertSame($eventsAfterFirst, ProductionEvent::query()->where('job_id', $job->id)->count());
    }

    public function test_same_reference_with_different_payload_fails_closed(): void
    {
        $s = $this->scenario('ref-conflict');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $ref = $this->ref();
        $this->registry->applyCommand($s['context'], $job->id, ProductionRegistry::CMD_START_STAGE, (int) $job->version, $ref, ['stage' => 'WASHING']);

        $this->expectException(ApiException::class); // reused_different_payload
        $this->registry->applyCommand($s['context'], $job->id, ProductionRegistry::CMD_START_STAGE, null, $ref, ['stage' => 'DRYING']);
    }

    public function test_stale_expected_version_is_rejected(): void
    {
        $s = $this->scenario('stale-version');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $this->registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'WASHING');

        // A second writer still holding the OLD version must be rejected.
        $this->expectException(ApiException::class); // version_conflict
        $this->registry->block($s['context'], $job->id, (int) $job->version, $this->ref(), 'X');
    }

    public function test_abandon_requires_a_cancelled_order(): void
    {
        $s = $this->scenario('abandon-guard');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        // Order is still RECEIVED, not CANCELLED -> abandon refused.
        $this->expectException(ApiException::class);
        $this->registry->abandon($s['context'], $job->id, (int) $job->version, $this->ref(), 'ORDER_CANCELLED');
    }

    public function test_every_command_appends_to_the_history(): void
    {
        $s = $this->scenario('history');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $this->registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'WASHING');

        // ProductionJobCreated + StartStage.
        $this->assertSame(2, ProductionEvent::query()->where('job_id', $job->id)->count());
    }
}
