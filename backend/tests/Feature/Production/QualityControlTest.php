<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionEvent;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Models\ReworkCycle;
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
 * STEP 6 · UNIT C — quality control and rework, against live PostgreSQL. A
 * recorded verdict drives the job transition and is append-only: a FAIL is
 * never rewritten into a PASS; a re-inspection is a new row (FR-082, FR-084).
 * Every value is fictional (Rule 23).
 */
final class QualityControlTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private ProductionRegistry $registry;
    private QualityControlService $qc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ProductionRegistry();
        $this->qc = new QualityControlService();
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

    /** Drive a job to AWAITING_QC. */
    private function jobAwaitingQc(TenantContext $c, Order $order): ProductionJob
    {
        $job = $this->registry->createJobForOrder($c, $order, $this->ref());
        $job = $this->registry->startStage($c, $job->id, (int) $job->version, $this->ref(), 'FINISHING');

        return $this->registry->sendToQualityControl($c, $job->id, (int) $job->version, $this->ref());
    }

    public function test_pass_verdict_closes_the_job(): void
    {
        $s = $this->scenario('qc-pass');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $inspection = $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $this->ref());

        $this->assertSame(QualityControlInspection::VERDICT_PASSED, $inspection->verdict);
        $this->assertSame(ProductionJob::STATE_CLOSED, $job->fresh()->state);
    }

    public function test_waived_verdict_closes_the_job(): void
    {
        $s = $this->scenario('qc-waived');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_WAIVED, (int) $job->version, $this->ref());
        $this->assertSame(ProductionJob::STATE_CLOSED, $job->fresh()->state);
    }

    public function test_fail_verdict_opens_rework(): void
    {
        $s = $this->scenario('qc-fail');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $inspection = $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_FAILED, (int) $job->version, $this->ref(), 'NODA', 'Masih ada noda (fiktif).');

        $this->assertSame(ProductionJob::STATE_REWORK_IN_PROGRESS, $job->fresh()->state);
        $cycle = ReworkCycle::query()->where('job_id', $job->id)->first();
        $this->assertNotNull($cycle);
        $this->assertSame(1, (int) $cycle->cycle_no);
        $this->assertSame($inspection->id, $cycle->source_inspection_id); // immutable linkage
    }

    public function test_fail_without_defect_reason_is_rejected(): void
    {
        $s = $this->scenario('qc-noreason');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $this->expectException(ApiException::class);
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_FAILED, (int) $job->version, $this->ref());
    }

    public function test_qc_only_when_awaiting_qc(): void
    {
        $s = $this->scenario('qc-wrongstate');
        $job = $this->registry->createJobForOrder($s['context'], $s['order'], $this->ref()); // CREATED, not AWAITING_QC
        $this->expectException(ApiException::class);
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $this->ref());
    }

    public function test_complete_rework_returns_to_awaiting_qc(): void
    {
        $s = $this->scenario('rework-return');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_FAILED, (int) $job->version, $this->ref(), 'NODA', 'noda');
        $job = $job->fresh();
        $job = $this->qc->completeRework($s['context'], $job->id, (int) $job->version, $this->ref(), 'DICUCI_ULANG');
        $this->assertSame(ProductionJob::STATE_AWAITING_QC, $job->state);
        $this->assertNotNull(ReworkCycle::query()->where('job_id', $job->id)->first()->completed_at);
    }

    public function test_repeated_rework_increments_cycle_number(): void
    {
        $s = $this->scenario('rework-repeat');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);

        // Cycle 1: fail -> rework -> complete -> awaiting again.
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_FAILED, (int) $job->fresh()->version, $this->ref(), 'NODA', 'noda 1');
        $this->qc->completeRework($s['context'], $job->id, (int) $job->fresh()->version, $this->ref(), 'ULANG1');
        // Cycle 2: fail again.
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_FAILED, (int) $job->fresh()->version, $this->ref(), 'ROBEK', 'sobek');

        $cycles = ReworkCycle::query()->where('job_id', $job->id)->orderBy('cycle_no')->pluck('cycle_no')->all();
        $this->assertSame([1, 2], array_map('intval', $cycles));
    }

    public function test_cross_tenant_qc_is_not_found(): void
    {
        $a = $this->scenario('qc-a');
        $b = $this->scenario('qc-b');
        $jobB = $this->jobAwaitingQc($b['context'], $b['order']);
        $this->expectException(ApiException::class); // NOT_FOUND under tenant A's context
        $this->qc->recordInspection($a['context'], $jobB->id, QualityControlInspection::VERDICT_PASSED, null, $this->ref());
    }

    public function test_qc_replay_is_exactly_once(): void
    {
        $s = $this->scenario('qc-replay');
        $job = $this->jobAwaitingQc($s['context'], $s['order']);
        $ref = $this->ref();
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $ref);
        $inspections = QualityControlInspection::query()->where('job_id', $job->id)->count();

        // Replay with the same reference: no second inspection, job stays CLOSED.
        $this->qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, null, $ref);
        $this->assertSame($inspections, QualityControlInspection::query()->where('job_id', $job->id)->count());
        $this->assertSame(1, ProductionEvent::query()->where('job_id', $job->id)->where('type', 'QualityControlRecorded')->count());
    }
}
