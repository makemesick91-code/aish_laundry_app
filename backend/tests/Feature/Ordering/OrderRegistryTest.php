<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Services\OrderRegistry;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * LOGICAL UNIT B — the OrderRegistry writer, against live PostgreSQL.
 *
 * Proves the domain guarantees: server-authoritative totals resolved from the
 * Step 4 price list (FR-051), the historical-price snapshot surviving a later
 * price change (FR-036), idempotent creation (FR-062), the enumerated lifecycle
 * (Rule 19), and tenant guards on every path (Rule 48).
 */
final class OrderRegistryTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private function registry(): OrderRegistry
    {
        return app(OrderRegistry::class);
    }

    /**
     * A tenant with everything an order needs, and a cashier context.
     *
     * @return array{context: TenantContext, customer_id: string, outlet_id: string, service_id: string, price_list_id: string, item_id: string}
     */
    private function scenario(string $slug, int $priceRupiah = 7000): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);
        $user = $this->makeUser();
        // No role needed: these exercise the OrderRegistry writer directly, not
        // the Gate. Authorization/RBAC is covered at the controller layer (Unit E).
        $membership = $this->makeMembership($tenant, $user);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id,
            'code' => 'CUST-' . Str::upper(Str::random(6)), 'name' => 'Pelanggan Fiktif',
            'phone' => '081200000000', 'phone_normalized' => '6281200000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId, 'tenant_id' => $tenant->id,
            'code' => 'SVC-' . Str::upper(Str::random(6)), 'name' => 'Cuci Kiloan Reguler',
            'unit_kind' => 'kiloan', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $priceListId = (string) Str::uuid();
        DB::table('price_lists')->insert([
            'id' => $priceListId, 'tenant_id' => $tenant->id, 'laundry_brand_id' => $brand->id,
            'code' => 'PL-' . Str::upper(Str::random(6)), 'name' => 'Harga Aktif', 'currency' => 'IDR',
            'status' => 'active', 'effective_from' => now()->toDateString(), 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = (string) Str::uuid();
        DB::table('price_list_items')->insert([
            'id' => $itemId, 'tenant_id' => $tenant->id, 'price_list_id' => $priceListId,
            'service_id' => $serviceId, 'amount_rupiah' => $priceRupiah,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'context' => new TenantContext($tenant, $membership, $outlet),
            'customer_id' => $customerId, 'outlet_id' => $outlet->id,
            'service_id' => $serviceId, 'price_list_id' => $priceListId, 'item_id' => $itemId,
        ];
    }

    private function draftInput(array $s, array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $s['customer_id'],
            'outlet_id' => $s['outlet_id'],
            'client_reference' => (string) Str::uuid(),
            'lines' => [
                ['target_type' => 'service', 'target_id' => $s['service_id'], 'quantity_milli' => 2500],
            ],
        ], $overrides);
    }

    public function test_create_computes_server_authoritative_totals_from_the_price_list(): void
    {
        $s = $this->scenario('totals', 7000);
        $order = $this->registry()->create($s['context'], $this->draftInput($s));

        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertSame(17500, $order->subtotal_rupiah); // 7000 × 2.5 kg
        $this->assertSame(17500, $order->total_rupiah);
        $this->assertStringStartsWith('ORD-', $order->order_number);

        $line = $order->lines->first();
        $this->assertSame(7000, $line->unit_price_rupiah);       // snapshot
        $this->assertSame($s['price_list_id'], $line->price_list_id);
        $this->assertSame($s['item_id'], $line->price_list_item_id);
        $this->assertSame('kilogram', $line->unit);
    }

    public function test_create_is_idempotent_on_client_reference(): void
    {
        $s = $this->scenario('idem');
        $ref = (string) Str::uuid();
        $first = $this->registry()->create($s['context'], $this->draftInput($s, ['client_reference' => $ref]));
        $second = $this->registry()->create($s['context'], $this->draftInput($s, ['client_reference' => $ref]));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->forTenant($s['context']->tenantId())->count());
    }

    public function test_the_captured_price_survives_a_later_price_list_change(): void
    {
        // FR-036 end-to-end: the order keeps the price that applied at intake.
        $s = $this->scenario('fr036', 7000);
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $this->assertSame(17500, $order->total_rupiah);

        // The price list changes afterward — a supersession in production, here a
        // direct raise for the test's purpose.
        DB::table('price_list_items')->where('id', $s['item_id'])->update(['amount_rupiah' => 9000]);

        $reloaded = Order::query()->with('lines')->forTenant($s['context']->tenantId())->findOrFail($order->id);
        $this->assertSame(7000, $reloaded->lines->first()->unit_price_rupiah);
        $this->assertSame(17500, $reloaded->total_rupiah);
    }

    public function test_create_rejects_a_service_with_no_active_price(): void
    {
        $s = $this->scenario('noprice');
        DB::table('price_list_items')->where('id', $s['item_id'])->delete();
        $this->expectException(ApiException::class);
        $this->registry()->create($s['context'], $this->draftInput($s));
    }

    public function test_create_rejects_a_cross_tenant_customer(): void
    {
        $a = $this->scenario('xa');
        $b = $this->scenario('xb');
        $this->expectException(ApiException::class);
        $this->registry()->create($a['context'], $this->draftInput($a, ['customer_id' => $b['customer_id']]));
    }

    public function test_create_rejects_a_cross_tenant_service(): void
    {
        $a = $this->scenario('xa2');
        $b = $this->scenario('xb2');
        $this->expectException(ApiException::class);
        $this->registry()->create($a['context'], $this->draftInput($a, [
            'lines' => [['target_type' => 'service', 'target_id' => $b['service_id'], 'quantity_milli' => 1000]],
        ]));
    }

    public function test_create_requires_at_least_one_line(): void
    {
        $s = $this->scenario('noline');
        $this->expectException(ApiException::class);
        $this->registry()->create($s['context'], $this->draftInput($s, ['lines' => []]));
    }

    public function test_place_transitions_draft_to_received(): void
    {
        $s = $this->scenario('place');
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $placed = $this->registry()->place($s['context'], $order);

        $this->assertSame(Order::STATUS_RECEIVED, $placed->status);
        $this->assertNotNull($placed->placed_at);
    }

    public function test_place_rejects_a_non_draft_order(): void
    {
        $s = $this->scenario('place2');
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $this->registry()->place($s['context'], $order);
        $this->expectException(ApiException::class); // already RECEIVED
        $this->registry()->place($s['context'], $order->fresh());
    }

    public function test_cancel_requires_a_reason(): void
    {
        $s = $this->scenario('cancel');
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $this->expectException(ApiException::class);
        $this->registry()->cancel($s['context'], $order, '   ');
    }

    public function test_cancel_sets_cancelled_state_and_reason(): void
    {
        $s = $this->scenario('cancel2');
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $cancelled = $this->registry()->cancel($s['context'], $order, 'Pelanggan berubah pikiran');

        $this->assertSame(Order::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('Pelanggan berubah pikiran', $cancelled->cancellation_reason);
    }

    public function test_cancel_of_another_tenants_order_is_not_found(): void
    {
        $a = $this->scenario('ta');
        $b = $this->scenario('tb');
        $order = $this->registry()->create($a['context'], $this->draftInput($a));

        // Tenant B's context must not be able to cancel tenant A's order.
        $this->expectException(ApiException::class);
        $this->registry()->cancel($b['context'], $order, 'mencoba lintas tenant');
    }

    public function test_an_order_records_an_audit_entry(): void
    {
        $s = $this->scenario('audit');
        $order = $this->registry()->create($s['context'], $this->draftInput($s));
        $this->assertDatabaseHas('audit_entries', [
            'action' => 'order.created',
            'subject_id' => $order->id,
            'tenant_id' => $s['context']->tenantId(),
        ]);
    }
}
