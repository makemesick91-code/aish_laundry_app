<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Services\ProductionReadyService;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Production\Services\QualityControlService;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 · UNIT D — the immutable first READY_FOR_PICKUP anchor (FR-076, FR-077),
 * against live PostgreSQL. The first-ready fact is written once, is server-set,
 * survives retry/replay unchanged, and is immutable at the database boundary. It
 * is the aging anchor Step 9 will read; aging itself is NOT implemented here.
 * Every value is fictional (Rule 23).
 */
final class ProductionReadyTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private ProductionRegistry $registry;
    private QualityControlService $qc;
    private ProductionReadyService $ready;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ProductionRegistry();
        $this->qc = new QualityControlService();
        $this->ready = app(ProductionReadyService::class); // resolves OrderRegistry deps
    }

    /** @return array{context: TenantContext, order: Order} */
    private function scenario(string $slug): array
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

        return [
            'context' => new TenantContext($tenant, $membership, $outlet),
            'order' => Order::query()->forTenant($tenant->id)->findOrFail($orderId),
        ];
    }

    private function ref(): string
    {
        return (string) Str::uuid();
    }

    /** Drive a job to CLOSED via a passing QC verdict. */
    private function closedJob(TenantContext $c, Order $order): ProductionJob
    {
        $job = $this->registry->createJobForOrder($c, $order, $this->ref());
        $job = $this->registry->startStage($c, $job->id, (int) $job->version, $this->ref(), 'FINISHING');
        $job = $this->registry->sendToQualityControl($c, $job->id, (int) $job->version, $this->ref());
        $this->qc->recordInspection($c, $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $this->ref());

        return $job->fresh();
    }

    private function readyRowCount(string $orderId): int
    {
        return DB::table('production_ready_events')->where('order_id', $orderId)->count();
    }

    public function test_mark_ready_transitions_order_and_writes_one_anchor(): void
    {
        $s = $this->scenario('ready-basic');
        $job = $this->closedJob($s['context'], $s['order']);
        $order = $this->ready->markReady($s['context'], $job->id, $this->ref());

        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $order->status);
        $this->assertSame(1, $this->readyRowCount($s['order']->id));
    }

    public function test_first_ready_timestamp_is_server_set_and_recorded_once(): void
    {
        $s = $this->scenario('ready-once');
        $job = $this->closedJob($s['context'], $s['order']);
        $this->ready->markReady($s['context'], $job->id, $this->ref());
        $firstAt = DB::table('production_ready_events')->where('order_id', $s['order']->id)->value('occurred_at');

        // A retry with a NEW reference (simulating leave/re-enter or a duplicate
        // command) must not create a second anchor or change the timestamp.
        $this->ready->markReady($s['context'], $job->id, $this->ref());

        $this->assertSame(1, $this->readyRowCount($s['order']->id));
        $this->assertSame(
            $firstAt,
            DB::table('production_ready_events')->where('order_id', $s['order']->id)->value('occurred_at'),
        );
    }

    public function test_replay_with_same_reference_is_exactly_once(): void
    {
        $s = $this->scenario('ready-replay');
        $job = $this->closedJob($s['context'], $s['order']);
        $ref = $this->ref();
        $this->ready->markReady($s['context'], $job->id, $ref);
        $firstAt = DB::table('production_ready_events')->where('order_id', $s['order']->id)->value('occurred_at');

        $order = $this->ready->markReady($s['context'], $job->id, $ref); // replay
        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $order->status);
        $this->assertSame(1, $this->readyRowCount($s['order']->id));
        $this->assertSame($firstAt, DB::table('production_ready_events')->where('order_id', $s['order']->id)->value('occurred_at'));
    }

    public function test_cannot_mark_ready_before_the_job_is_closed(): void
    {
        $s = $this->scenario('ready-early');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref()); // CREATED
        $this->expectException(ApiException::class);
        $this->ready->markReady($s['context'], $job->id, $this->ref());
    }

    public function test_marking_ready_is_indistinguishable_across_tenants(): void
    {
        $a = $this->scenario('ready-a');
        $b = $this->scenario('ready-b');
        $jobB = $this->closedJob($b['context'], $b['order']);
        $this->expectException(ApiException::class); // NOT_FOUND under tenant A
        $this->ready->markReady($a['context'], $jobB->id, $this->ref());
    }

    public function test_ready_anchor_cannot_be_mutated_at_the_database(): void
    {
        $s = $this->scenario('ready-immutable');
        $job = $this->closedJob($s['context'], $s['order']);
        $this->ready->markReady($s['context'], $job->id, $this->ref());

        $this->expectException(\Illuminate\Database\QueryException::class); // append-only trigger
        DB::table('production_ready_events')->where('order_id', $s['order']->id)
            ->update(['occurred_at' => now()->addDay()]);
    }
}
