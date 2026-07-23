<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * LOGICAL UNIT E — the order and payment HTTP surfaces (FR-048 … FR-070).
 *
 * The persistence and domain guarantees are covered by the schema and registry
 * suites. This suite covers the APPLICATION SURFACE those sit behind:
 * server-side authorization on every path, tenant scoping (a foreign id 404s
 * like an absent one), idempotency over HTTP, server-authoritative totals, and
 * the deliberate ABSENCE of any delete route (Rule 40, Rule 48, Rule 04).
 *
 * Runs against PostgreSQL (Rule 43). Every value is fictional (Rule 23).
 */
final class OrderPaymentSurfaceTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /** @return array{tenant: Tenant, outlet_id: string, customer_id: string, service_id: string} */
    private function tenantWithCatalogue(string $slug, int $price = 8000): array
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id, 'code' => 'CUST-' . Str::upper(Str::random(6)),
            'name' => 'Pelanggan Fiktif', 'phone' => '081200000000', 'phone_normalized' => '6281200000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId, 'tenant_id' => $tenant->id, 'code' => 'SVC-' . Str::upper(Str::random(6)),
            'name' => 'Cuci Kiloan', 'unit_kind' => 'kiloan', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $plId = (string) Str::uuid();
        DB::table('price_lists')->insert([
            'id' => $plId, 'tenant_id' => $tenant->id, 'laundry_brand_id' => $brand->id,
            'code' => 'PL-' . Str::upper(Str::random(6)), 'name' => 'Harga', 'currency' => 'IDR',
            'status' => 'active', 'effective_from' => now()->toDateString(), 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('price_list_items')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'price_list_id' => $plId,
            'service_id' => $serviceId, 'amount_rupiah' => $price, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['tenant' => $tenant, 'outlet_id' => $outlet->id, 'customer_id' => $customerId, 'service_id' => $serviceId];
    }

    /** @return array<string, string> */
    private function asRole(Tenant $tenant, string $role): array
    {
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [$role]);

        return $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);
    }

    private function orderBody(array $s, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $s['customer_id'],
            'outlet_id' => $s['outlet_id'],
            'client_reference' => (string) Str::uuid(),
            'lines' => [['target_type' => 'service', 'target_id' => $s['service_id'], 'quantity_milli' => 2500]],
        ], $overrides);
    }

    public function test_a_cashier_creates_an_order_with_server_computed_totals(): void
    {
        $s = $this->tenantWithCatalogue('e-create', 8000);
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);

        // A client-supplied total must be ignored (FR-051): the server computes
        // 8000 × 2.5 kg = 20000 regardless of what the body claims.
        $response = $this->postJson('/api/v1/orders', $this->orderBody($s, ['total_rupiah' => 1]), $headers);

        $response->assertStatus(201)
            ->assertJsonPath('data.order.status', 'DRAFT')
            ->assertJsonPath('data.order.total_rupiah', 20000);
    }

    public function test_order_creation_is_idempotent_over_http(): void
    {
        $s = $this->tenantWithCatalogue('e-idem');
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);
        $ref = (string) Str::uuid();

        $first = $this->postJson('/api/v1/orders', $this->orderBody($s, ['client_reference' => $ref]), $headers)->assertStatus(201);
        $second = $this->postJson('/api/v1/orders', $this->orderBody($s, ['client_reference' => $ref]), $headers)->assertStatus(201);

        $this->assertSame($first->json('data.order.id'), $second->json('data.order.id'));
    }

    public function test_a_production_operator_cannot_create_an_order(): void
    {
        $s = $this->tenantWithCatalogue('e-rbac');
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_PRODUCTION_OPERATOR);

        $this->postJson('/api/v1/orders', $this->orderBody($s), $headers)->assertStatus(403);
    }

    public function test_a_cashier_records_a_payment_but_cannot_reverse_it(): void
    {
        $s = $this->tenantWithCatalogue('e-pay');
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);

        $orderId = $this->postJson('/api/v1/orders', $this->orderBody($s), $headers)->json('data.order.id');

        $payment = $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'cash', 'amount_rupiah' => 20000, 'client_reference' => (string) Str::uuid(),
        ], $headers);
        $payment->assertStatus(201)->assertJsonPath('data.payment.status', 'succeeded');

        // A cashier holds PAYMENT_RECORD but NOT PAYMENT_REFUND (FR-065).
        $paymentId = $payment->json('data.payment.id');
        $this->postJson("/api/v1/payments/{$paymentId}/reverse", [
            'amount_rupiah' => 5000, 'reason' => 'coba',
        ], $headers)->assertStatus(403);
    }

    public function test_finance_may_reverse_a_payment(): void
    {
        $s = $this->tenantWithCatalogue('e-refund');
        $cashier = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);
        $finance = $this->asRole($s['tenant'], PermissionRegistry::ROLE_FINANCE);

        $orderId = $this->postJson('/api/v1/orders', $this->orderBody($s), $cashier)->json('data.order.id');
        $paymentId = $this->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'cash', 'amount_rupiah' => 20000, 'client_reference' => (string) Str::uuid(),
        ], $cashier)->json('data.payment.id');

        $this->postJson("/api/v1/payments/{$paymentId}/reverse", [
            'amount_rupiah' => 5000, 'reason' => 'Kompensasi',
        ], $finance)->assertStatus(201)->assertJsonPath('data.payment.kind', 'reversal');
    }

    public function test_an_order_of_another_tenant_is_not_found(): void
    {
        $a = $this->tenantWithCatalogue('e-iso-a');
        $b = $this->tenantWithCatalogue('e-iso-b');
        $cashierA = $this->asRole($a['tenant'], PermissionRegistry::ROLE_CASHIER);
        $cashierB = $this->asRole($b['tenant'], PermissionRegistry::ROLE_CASHIER);

        $orderId = $this->postJson('/api/v1/orders', $this->orderBody($a), $cashierA)->json('data.order.id');

        // Tenant B must not see tenant A's order — 404, indistinguishable from absent.
        $this->getJson("/api/v1/orders/{$orderId}", $cashierB)->assertStatus(404);
        // And it must not appear in B's list.
        $this->getJson('/api/v1/orders', $cashierB)->assertStatus(200)->assertJsonCount(0, 'data.orders');
    }

    public function test_there_is_no_order_delete_route(): void
    {
        $s = $this->tenantWithCatalogue('e-nodelete');
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);
        $orderId = $this->postJson('/api/v1/orders', $this->orderBody($s), $headers)->json('data.order.id');

        // No destroy route exists (FR-066): cancellation is the only removal path.
        $this->deleteJson("/api/v1/orders/{$orderId}", [], $headers)->assertStatus(405);
    }

    public function test_the_receipt_shows_captured_prices(): void
    {
        $s = $this->tenantWithCatalogue('e-receipt', 8000);
        $headers = $this->asRole($s['tenant'], PermissionRegistry::ROLE_CASHIER);
        $orderId = $this->postJson('/api/v1/orders', $this->orderBody($s), $headers)->json('data.order.id');

        $this->getJson("/api/v1/orders/{$orderId}/receipt", $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.receipt.total_rupiah', 20000)
            ->assertJsonPath('data.receipt.lines.0.unit_price_rupiah', 8000);
    }
}
