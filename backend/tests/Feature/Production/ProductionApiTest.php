<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\ProductionJob;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * STEP 6 · UNIT E — the production HTTP surface. Server-side authorization on
 * every path (a cashier holds no production permission), tenant scoping (a
 * foreign job 404s like an absent one — Rule 48), idempotency over HTTP, and
 * validation. Runs against PostgreSQL (Rule 43); every value is fictional.
 */
final class ProductionApiTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    protected function setUp(): void
    {
        parent::setUp();
        // Seeds the platform role/permission catalogue from PermissionRegistry —
        // now including the Step 6 production permissions.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

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

    /** Headers for a freshly-made member holding $role in $tenant. */
    private function asRole(Tenant $tenant, string $role): array
    {
        $user = $this->makeUser(self::PASSWORD, Str::lower(Str::random(8)) . '@contoh.fiktif');
        $this->makeMembership($tenant, $user, [$role]);

        return $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);
    }

    /** A CREATED production job, seeded via the registry under an operator context. */
    private function createdJob(array $t): ProductionJob
    {
        $user = $this->makeUser(self::PASSWORD, 'setup-' . Str::lower(Str::random(6)) . '@contoh.fiktif');
        $membership = $this->makeMembership($t['tenant'], $user, [PermissionRegistry::ROLE_PRODUCTION_OPERATOR]);
        $context = new TenantContext($t['tenant'], $membership, $t['outlet']);
        $order = Order::query()->forTenant($t['tenant']->id)->findOrFail($t['order_id']);

        return (new ProductionRegistry())->createJobForOrder($context, $order, (string) Str::uuid());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $t = $this->tenant('api-unauth');
        $job = $this->createdJob($t);
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", [
            'stage' => 'WASHING', 'client_reference' => (string) Str::uuid(),
        ])->assertStatus(401);
    }

    public function test_cashier_cannot_operate_production(): void
    {
        $t = $this->tenant('api-cashier');
        $job = $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_CASHIER);
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", [
            'stage' => 'WASHING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_production_operator_can_advance_a_stage(): void
    {
        $t = $this->tenant('api-operate');
        $job = $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", [
            'stage' => 'WASHING', 'expected_version' => (int) $job->version, 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(200)->assertJsonPath('data.job.state', ProductionJob::STATE_IN_PROGRESS);
    }

    public function test_quality_control_role_cannot_operate(): void
    {
        $t = $this->tenant('api-qc-noop');
        $job = $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_QUALITY_CONTROL);
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", [
            'stage' => 'WASHING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(403);
    }

    public function test_foreign_tenant_job_is_not_found(): void
    {
        $a = $this->tenant('api-a');
        $b = $this->tenant('api-b');
        $jobB = $this->createdJob($b);
        $headers = $this->asRole($a['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);
        // Operator of tenant A cannot even see tenant B's job: 404, not 403.
        $this->postJson("/api/v1/production/jobs/{$jobB->id}/advance", [
            'stage' => 'WASHING', 'client_reference' => (string) Str::uuid(),
        ], $headers)->assertStatus(404);
    }

    public function test_advance_is_idempotent_over_http(): void
    {
        $t = $this->tenant('api-idem');
        $job = $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);
        $ref = (string) Str::uuid();
        $body = ['stage' => 'WASHING', 'expected_version' => (int) $job->version, 'client_reference' => $ref];

        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", $body, $headers)->assertStatus(200);
        $versionAfterFirst = (int) ProductionJob::query()->whereKey($job->id)->value('version');

        // Replay same reference (no expected_version): exactly-once, no second bump.
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", [
            'stage' => 'WASHING', 'client_reference' => $ref,
        ], $headers)->assertStatus(200);

        $this->assertSame($versionAfterFirst, (int) ProductionJob::query()->whereKey($job->id)->value('version'));
    }

    public function test_missing_client_reference_is_a_validation_error(): void
    {
        $t = $this->tenant('api-validate');
        $job = $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);
        $this->postJson("/api/v1/production/jobs/{$job->id}/advance", ['stage' => 'WASHING'], $headers)
            ->assertStatus(422);
    }

    public function test_queue_lists_jobs_for_the_operator(): void
    {
        $t = $this->tenant('api-queue');
        $this->createdJob($t);
        $headers = $this->asRole($t['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);
        $this->getJson('/api/v1/production/queue', $headers)
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['jobs'], 'meta' => ['total']]);
    }
}
