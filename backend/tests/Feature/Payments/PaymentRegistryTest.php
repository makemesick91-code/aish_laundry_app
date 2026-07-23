<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Ordering\Http\ReceiptProjection;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentRegistry;
use App\Modules\Payments\Support\OrderBalance;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * LOGICAL UNIT D — the PaymentRegistry and the derived balance, against live
 * PostgreSQL. Proves idempotent recording (FR-062), server-decided paid state
 * (FR-064), the outstanding-balance/overpayment rule (FR-070), gateway
 * confirmation with replay protection (FR-063), reversal-not-deletion (FR-066,
 * FR-067), and tenant guards (Rule 48).
 */
final class PaymentRegistryTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private function registry(): PaymentRegistry
    {
        return app(PaymentRegistry::class);
    }

    /** @return array{context: TenantContext, order: Order} */
    private function scenario(string $slug, int $total = 20000, string $status = 'RECEIVED'): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);
        $user = $this->makeUser();
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
            'id' => $serviceId, 'tenant_id' => $tenant->id, 'code' => 'SVC-' . Str::upper(Str::random(6)),
            'name' => 'Cuci Kiloan', 'unit_kind' => 'kiloan', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderId = (string) Str::uuid();
        $cancelled = $status === 'CANCELLED';
        DB::table('orders')->insert([
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id,
            'customer_id' => $customerId, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'client_reference' => (string) Str::uuid(), 'status' => $status,
            'subtotal_rupiah' => $total, 'discount_rupiah' => 0, 'total_rupiah' => $total,
            'placed_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            // The orders_cancellation_consistent CHECK requires these together.
            'cancelled_at' => $cancelled ? now() : null,
            'cancellation_reason' => $cancelled ? 'Dibatalkan untuk pengujian' : null,
        ]);
        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'order_id' => $orderId,
            'line_number' => 1, 'service_id' => $serviceId, 'service_name' => 'Cuci Kiloan',
            'unit' => 'kilogram', 'quantity_milli' => 2500, 'unit_price_rupiah' => 8000,
            'discount_rupiah' => 0, 'subtotal_rupiah' => $total, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['context' => new TenantContext($tenant, $membership, $outlet), 'order' => Order::findOrFail($orderId)];
    }

    private function cash(array $overrides = []): array
    {
        return array_merge(['method' => 'cash', 'amount_rupiah' => 20000, 'client_reference' => (string) Str::uuid()], $overrides);
    }

    public function test_a_cash_payment_succeeds_immediately_and_pays_the_order(): void
    {
        $s = $this->scenario('cash');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash());

        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $balance = OrderBalance::for($s['order']);
        $this->assertSame(20000, $balance['paid_rupiah']);
        $this->assertSame(0, $balance['outstanding_rupiah']);
        $this->assertSame(OrderBalance::STATE_PAID, $balance['state']);
    }

    public function test_a_partial_payment_leaves_the_order_partial(): void
    {
        $s = $this->scenario('partial');
        $this->registry()->record($s['context'], $s['order'], $this->cash(['amount_rupiah' => 12000]));
        $balance = OrderBalance::for($s['order']);
        $this->assertSame(12000, $balance['paid_rupiah']);
        $this->assertSame(8000, $balance['outstanding_rupiah']);
        $this->assertSame(OrderBalance::STATE_PARTIAL, $balance['state']);

        $this->registry()->record($s['context'], $s['order'], $this->cash(['amount_rupiah' => 8000]));
        $this->assertSame(OrderBalance::STATE_PAID, OrderBalance::for($s['order'])['state']);
    }

    public function test_recording_is_idempotent_on_client_reference(): void
    {
        $s = $this->scenario('idem');
        $ref = (string) Str::uuid();
        $first = $this->registry()->record($s['context'], $s['order'], $this->cash(['client_reference' => $ref]));
        $second = $this->registry()->record($s['context'], $s['order'], $this->cash(['client_reference' => $ref]));
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->forTenant($s['context']->tenantId())->count());
    }

    public function test_overpayment_is_rejected(): void
    {
        $s = $this->scenario('over');
        $this->expectException(ApiException::class);
        $this->registry()->record($s['context'], $s['order'], $this->cash(['amount_rupiah' => 20001]));
    }

    public function test_a_payment_on_a_cancelled_order_is_rejected(): void
    {
        $s = $this->scenario('cancelled', 20000, 'CANCELLED');
        // Give the cancelled order valid cancellation fields for the CHECK.
        DB::table('orders')->where('id', $s['order']->id)->update([
            'cancelled_at' => now(), 'cancellation_reason' => 'batal',
        ]);
        $this->expectException(ApiException::class);
        $this->registry()->record($s['context'], $s['order']->fresh(), $this->cash());
    }

    public function test_a_qris_payment_is_pending_until_a_verified_callback(): void
    {
        $s = $this->scenario('qris');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash(['method' => 'qris']));
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(OrderBalance::STATE_UNPAID, OrderBalance::for($s['order'])['state']);

        $confirmed = $this->registry()->confirmGateway($s['context'], $payment, ['amount_rupiah' => 20000, 'gateway_reference' => 'REF-FIKTIF-1']);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $confirmed->status);
        $this->assertSame(OrderBalance::STATE_PAID, OrderBalance::for($s['order'])['state']);
    }

    public function test_gateway_confirmation_rejects_an_amount_mismatch(): void
    {
        $s = $this->scenario('mismatch');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash(['method' => 'qris']));
        $this->expectException(ApiException::class);
        $this->registry()->confirmGateway($s['context'], $payment, ['amount_rupiah' => 19999]);
    }

    public function test_gateway_confirmation_rejects_a_replay(): void
    {
        $s = $this->scenario('replay');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash(['method' => 'qris']));
        $this->registry()->confirmGateway($s['context'], $payment, ['amount_rupiah' => 20000]);
        $this->expectException(ApiException::class); // second confirmation is a replay
        $this->registry()->confirmGateway($s['context'], $payment->fresh(), ['amount_rupiah' => 20000]);
    }

    public function test_a_reversal_reduces_the_balance_and_never_deletes(): void
    {
        $s = $this->scenario('reverse');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash());
        $this->assertSame(OrderBalance::STATE_PAID, OrderBalance::for($s['order'])['state']);

        $reversal = $this->registry()->reverse($s['context'], $payment, 5000, 'Kompensasi keterlambatan');
        $this->assertSame(Payment::KIND_REVERSAL, $reversal->kind);

        $balance = OrderBalance::for($s['order']);
        $this->assertSame(15000, $balance['paid_rupiah']);
        $this->assertSame(OrderBalance::STATE_PARTIAL, $balance['state']);
        // The original payment row still exists (append-only).
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    public function test_a_reversal_may_not_exceed_the_reversible_amount(): void
    {
        $s = $this->scenario('overrev');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash());
        $this->registry()->reverse($s['context'], $payment, 15000, 'sebagian');
        $this->expectException(ApiException::class); // only 5000 remains reversible
        $this->registry()->reverse($s['context'], $payment->fresh(), 6000, 'melebihi');
    }

    public function test_a_reversal_requires_a_reason(): void
    {
        $s = $this->scenario('revreason');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash());
        $this->expectException(ApiException::class);
        $this->registry()->reverse($s['context'], $payment, 1000, '  ');
    }

    public function test_a_payment_cannot_be_reversed_from_another_tenant(): void
    {
        $a = $this->scenario('ta');
        $b = $this->scenario('tb');
        $payment = $this->registry()->record($a['context'], $a['order'], $this->cash());
        $this->expectException(ApiException::class);
        $this->registry()->reverse($b['context'], $payment, 1000, 'lintas tenant');
    }

    public function test_the_receipt_projection_shows_captured_prices_and_balance(): void
    {
        $s = $this->scenario('receipt');
        $this->registry()->record($s['context'], $s['order'], $this->cash(['amount_rupiah' => 12000]));
        $payments = Payment::query()->forTenant($s['context']->tenantId())->where('order_id', $s['order']->id)->get();

        $receipt = ReceiptProjection::of($s['order'], $payments);
        $this->assertSame(20000, $receipt['total_rupiah']);
        $this->assertSame(12000, $receipt['paid_rupiah']);
        $this->assertSame(8000, $receipt['outstanding_rupiah']);
        $this->assertSame('partial', $receipt['payment_state']);
        $this->assertSame(8000, $receipt['lines'][0]['unit_price_rupiah']); // captured snapshot
        $this->assertArrayNotHasKey('cost_price_rupiah', $receipt['lines'][0]);
    }

    public function test_a_payment_records_an_audit_entry(): void
    {
        $s = $this->scenario('audit');
        $payment = $this->registry()->record($s['context'], $s['order'], $this->cash());
        $this->assertDatabaseHas('audit_entries', [
            'action' => 'payment.recorded',
            'subject_id' => $payment->id,
            'tenant_id' => $s['context']->tenantId(),
        ]);
    }
}
