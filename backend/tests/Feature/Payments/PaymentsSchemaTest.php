<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * LOGICAL UNIT C — the payments/ledger schema, against live PostgreSQL (Rule 43).
 *
 * Proves the financial-integrity invariants are enforced by the ENGINE: integer
 * Rupiah, positive amounts (a reversal is a direction, not a negative sign),
 * idempotency uniqueness, structural tenant binding, reversal consistency, and —
 * the one Rule 04 makes an automatic NO-GO if it fails — that a financial row can
 * never be hard-deleted.
 */
final class PaymentsSchemaTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    /** @return array{tenant_id: string, order_id: string} */
    private function scenario(string $slug): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId, 'tenant_id' => $tenant->id,
            'code' => 'CUST-' . Str::upper(Str::random(6)), 'name' => 'Pelanggan Fiktif',
            'phone' => '081200000000', 'phone_normalized' => '6281200000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'tenant_id' => $tenant->id, 'outlet_id' => $outlet->id,
            'customer_id' => $customerId, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'client_reference' => (string) Str::uuid(), 'status' => 'RECEIVED',
            'subtotal_rupiah' => 20000, 'discount_rupiah' => 0, 'total_rupiah' => 20000,
            'placed_at' => now(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['tenant_id' => $tenant->id, 'order_id' => $orderId];
    }

    private function paymentRow(array $s, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['tenant_id'],
            'order_id' => $s['order_id'],
            'payment_number' => 'PAY-' . Str::upper(Str::random(10)),
            'client_reference' => (string) Str::uuid(),
            'kind' => 'payment',
            'method' => 'cash',
            'status' => 'succeeded',
            'currency' => 'IDR',
            'amount_rupiah' => 20000,
            'received_at' => now(),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function insertPayment(array $s, array $overrides = []): string
    {
        $row = $this->paymentRow($s, $overrides);
        DB::table('payments')->insert($row);

        return $row['id'];
    }

    private function assertRejected(callable $op, string $because): void
    {
        DB::beginTransaction();
        try {
            $op();
            DB::rollBack();
            $this->fail("The database accepted an operation it should have rejected: {$because}");
        } catch (QueryException) {
            DB::rollBack();
            $this->addToAssertionCount(1);
        }
    }

    public function test_a_valid_payment_is_accepted(): void
    {
        $s = $this->scenario('valid');
        $id = $this->insertPayment($s);
        $this->assertDatabaseHas('payments', ['id' => $id, 'amount_rupiah' => 20000, 'status' => 'succeeded']);
    }

    public function test_amount_must_be_positive(): void
    {
        $s = $this->scenario('amt');
        $this->assertRejected(fn () => $this->insertPayment($s, ['amount_rupiah' => 0]), 'zero amount');
        $this->assertRejected(fn () => $this->insertPayment($s, ['amount_rupiah' => -1]), 'negative amount');
    }

    public function test_method_must_be_canonical(): void
    {
        $s = $this->scenario('method');
        $this->assertRejected(fn () => $this->insertPayment($s, ['method' => 'gopay']), 'a non-canonical method');
    }

    public function test_kind_and_status_and_currency_are_constrained(): void
    {
        $s = $this->scenario('enum');
        $this->assertRejected(fn () => $this->insertPayment($s, ['kind' => 'topup']), 'an unknown kind');
        $this->assertRejected(fn () => $this->insertPayment($s, ['status' => 'paid']), 'a non-canonical status');
        $this->assertRejected(fn () => $this->insertPayment($s, ['currency' => 'USD']), 'a non-IDR currency');
    }

    public function test_client_reference_is_unique_per_tenant(): void
    {
        $s = $this->scenario('idem');
        $ref = (string) Str::uuid();
        $this->insertPayment($s, ['client_reference' => $ref]);
        $this->assertRejected(fn () => $this->insertPayment($s, ['client_reference' => $ref]), 'a replayed client_reference');
    }

    public function test_a_payment_cannot_settle_another_tenants_order(): void
    {
        $a = $this->scenario('iso-a');
        $b = $this->scenario('iso-b');
        $this->assertRejected(
            fn () => $this->insertPayment($a, ['order_id' => $b['order_id']]),
            "tenant A payment against tenant B's order",
        );
    }

    public function test_reversal_consistency_is_enforced(): void
    {
        $s = $this->scenario('rev');
        $paymentId = $this->insertPayment($s);

        // kind=reversal with no reverses_payment_id.
        $this->assertRejected(
            fn () => $this->insertPayment($s, ['kind' => 'reversal', 'reversal_reason' => 'x']),
            'a reversal that references no payment',
        );
        // kind=payment WITH a reverses_payment_id.
        $this->assertRejected(
            fn () => $this->insertPayment($s, ['reverses_payment_id' => $paymentId]),
            'a payment that references a reversed payment',
        );
        // kind=reversal with no reason.
        $this->assertRejected(
            fn () => $this->insertPayment($s, ['kind' => 'reversal', 'reverses_payment_id' => $paymentId]),
            'a reversal with no reason',
        );

        // A well-formed reversal IS accepted.
        $revId = $this->insertPayment($s, [
            'kind' => 'reversal', 'reverses_payment_id' => $paymentId,
            'reversal_reason' => 'Pengembalian sebagian', 'status' => 'succeeded',
        ]);
        $this->assertDatabaseHas('payments', ['id' => $revId, 'kind' => 'reversal']);
    }

    public function test_a_reversal_cannot_reference_another_tenants_payment(): void
    {
        $a = $this->scenario('ra');
        $b = $this->scenario('rb');
        $bPayment = $this->insertPayment($b);
        $this->assertRejected(
            fn () => $this->insertPayment($a, [
                'kind' => 'reversal', 'reverses_payment_id' => $bPayment, 'reversal_reason' => 'lintas tenant',
            ]),
            "a reversal in tenant A referencing tenant B's payment",
        );
    }

    public function test_a_payment_can_never_be_hard_deleted(): void
    {
        // FR-066 — the automatic NO-GO if it fails. The ledger is append-only,
        // enforced by an ENABLE ALWAYS trigger, even against a raw query delete.
        $s = $this->scenario('nodelete');
        $id = $this->insertPayment($s);

        $this->assertRejected(
            fn () => DB::table('payments')->where('id', $id)->delete(),
            'a hard delete of a financial transaction',
        );

        // Still there.
        $this->assertDatabaseHas('payments', ['id' => $id]);
    }
}
