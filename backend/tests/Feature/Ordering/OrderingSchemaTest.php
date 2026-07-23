<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * LOGICAL UNIT A — the ordering schema, tested against the LIVE PostgreSQL
 * database (Rule 43). Every invariant here is enforced by a database constraint,
 * not by application validation, so a code path that forgets to validate cannot
 * write a row the ledger's integrity depends on being impossible.
 *
 * These are the negative tests that make "the schema is correct" a checkable
 * claim rather than an opinion: each asserts the ENGINE rejects a bad row.
 */
final class OrderingSchemaTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private function makeCustomer(string $tenantId): string
    {
        $id = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'code' => 'CUST-' . Str::upper(Str::random(8)),
            'name' => 'Pelanggan Uji Fiktif',
            'phone' => '081200000000',
            'phone_normalized' => '6281200000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeService(string $tenantId): string
    {
        $id = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'code' => 'SVC-' . Str::upper(Str::random(8)),
            'name' => 'Cuci Kiloan Reguler (fiktif)',
            'unit_kind' => 'kiloan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** A tenant with an outlet, a customer, and a service — the parents an order needs. */
    private function scenario(string $slug): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant ' . $slug);
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);

        return [
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $this->makeCustomer($tenant->id),
            'service_id' => $this->makeService($tenant->id),
        ];
    }

    private function orderRow(array $s, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['tenant_id'],
            'outlet_id' => $s['outlet_id'],
            'customer_id' => $s['customer_id'],
            'order_number' => 'ALS-' . Str::upper(Str::random(10)),
            'client_reference' => (string) Str::uuid(),
            'status' => 'DRAFT',
            'currency' => 'IDR',
            'subtotal_rupiah' => 0,
            'discount_rupiah' => 0,
            'total_rupiah' => 0,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    private function lineRow(string $tenantId, string $orderId, string $serviceId, array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'line_number' => 1,
            'service_id' => $serviceId,
            'service_name' => 'Cuci Kiloan Reguler (fiktif)',
            'unit' => 'kilogram',
            'quantity_milli' => 2500,
            'unit_price_rupiah' => 7000,
            'discount_rupiah' => 0,
            'subtotal_rupiah' => 17500,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    /**
     * Assert the ENGINE rejects a row. Wrapped in a savepoint: under
     * RefreshDatabase every test runs inside one transaction, and a PostgreSQL
     * constraint violation aborts that transaction until it is rolled back — so
     * without a savepoint the FIRST rejection would poison every statement after
     * it (and make a later "rejection" pass for the wrong reason). Rolling back to
     * the savepoint clears the aborted state and keeps each assertion independent.
     */
    private function assertRejected(callable $insert, string $because): void
    {
        DB::beginTransaction();
        try {
            $insert();
            DB::rollBack();
            $this->fail("The database accepted a row it should have rejected: {$because}");
        } catch (QueryException) {
            DB::rollBack();
            $this->addToAssertionCount(1);
        }
    }

    // --- control: the happy path is accepted -------------------------------

    public function test_a_valid_order_and_line_are_accepted(): void
    {
        $s = $this->scenario('valid');
        $row = $this->orderRow($s, [
            'subtotal_rupiah' => 17500,
            'total_rupiah' => 17500,
        ]);
        DB::table('orders')->insert($row);
        DB::table('order_lines')->insert($this->lineRow($s['tenant_id'], $row['id'], $s['service_id']));

        $this->assertDatabaseHas('orders', ['id' => $row['id'], 'total_rupiah' => 17500]);
        $this->assertDatabaseHas('order_lines', ['order_id' => $row['id'], 'subtotal_rupiah' => 17500]);
    }

    // --- money invariants (Rule 04) ----------------------------------------

    public function test_total_must_equal_subtotal_minus_discount(): void
    {
        $s = $this->scenario('total');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, [
                'subtotal_rupiah' => 10000, 'discount_rupiah' => 0, 'total_rupiah' => 9999,
            ])),
            'total_rupiah != subtotal_rupiah - discount_rupiah',
        );
    }

    public function test_discount_may_not_exceed_subtotal(): void
    {
        $s = $this->scenario('disc');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, [
                'subtotal_rupiah' => 10000, 'discount_rupiah' => 10001, 'total_rupiah' => -1,
            ])),
            'discount_rupiah > subtotal_rupiah',
        );
    }

    public function test_money_may_not_be_negative(): void
    {
        $s = $this->scenario('neg');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, [
                'subtotal_rupiah' => -1, 'total_rupiah' => -1,
            ])),
            'negative subtotal_rupiah',
        );
    }

    public function test_currency_must_be_idr(): void
    {
        $s = $this->scenario('cur');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, ['currency' => 'USD'])),
            "currency other than 'IDR'",
        );
    }

    public function test_status_must_be_canonical(): void
    {
        $s = $this->scenario('stat');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, ['status' => 'PAID'])),
            'a status outside the fifteen canonical values',
        );
    }

    // --- idempotency (FR-059/FR-062) ---------------------------------------

    public function test_client_reference_is_unique_per_tenant(): void
    {
        $s = $this->scenario('idem');
        $ref = (string) Str::uuid();
        DB::table('orders')->insert($this->orderRow($s, ['client_reference' => $ref]));
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, ['client_reference' => $ref])),
            'a second order reusing a client_reference in the same tenant',
        );
    }

    public function test_order_number_is_unique_per_tenant(): void
    {
        $s = $this->scenario('num');
        $num = 'ALS-DUP-0001';
        DB::table('orders')->insert($this->orderRow($s, ['order_number' => $num]));
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, ['order_number' => $num])),
            'a duplicate order_number in the same tenant',
        );
    }

    // --- cancellation consistency (FR-058) ---------------------------------

    public function test_cancelled_order_requires_timestamp_and_reason(): void
    {
        $s = $this->scenario('cancel');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s, ['status' => 'CANCELLED'])),
            'CANCELLED with no cancelled_at',
        );
        $s2 = $this->scenario('cancel2');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($s2, [
                'status' => 'CANCELLED', 'cancelled_at' => now(),
            ])),
            'CANCELLED with no cancellation_reason',
        );
    }

    // --- tenant isolation at the engine (Rule 48, invariant P1) ------------

    public function test_an_order_cannot_reference_another_tenants_customer(): void
    {
        $a = $this->scenario('iso-a');
        $b = $this->scenario('iso-b');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($a, ['customer_id' => $b['customer_id']])),
            "tenant A order pointing at tenant B's customer",
        );
    }

    public function test_an_order_cannot_reference_another_tenants_outlet(): void
    {
        $a = $this->scenario('iso-a2');
        $b = $this->scenario('iso-b2');
        $this->assertRejected(
            fn () => DB::table('orders')->insert($this->orderRow($a, ['outlet_id' => $b['outlet_id']])),
            "tenant A order pointing at tenant B's outlet",
        );
    }

    public function test_a_line_cannot_reference_another_tenants_service(): void
    {
        $a = $this->scenario('iso-a3');
        $b = $this->scenario('iso-b3');
        $order = $this->orderRow($a, ['subtotal_rupiah' => 17500, 'total_rupiah' => 17500]);
        DB::table('orders')->insert($order);
        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($a['tenant_id'], $order['id'], $b['service_id']),
            ),
            "a line in tenant A pointing at tenant B's service",
        );
    }

    // --- line invariants ---------------------------------------------------

    public function test_a_line_must_have_exactly_one_priceable_target(): void
    {
        $s = $this->scenario('target');
        $order = $this->orderRow($s, ['subtotal_rupiah' => 17500, 'total_rupiah' => 17500]);
        DB::table('orders')->insert($order);

        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], [
                    'service_package_id' => (string) Str::uuid(),
                ]),
            ),
            'a line with two targets',
        );
        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], ['service_id' => null]),
            ),
            'a line with no target',
        );
    }

    public function test_line_quantity_must_be_positive(): void
    {
        $s = $this->scenario('qty');
        $order = $this->orderRow($s, ['subtotal_rupiah' => 17500, 'total_rupiah' => 17500]);
        DB::table('orders')->insert($order);
        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], ['quantity_milli' => 0]),
            ),
            'quantity_milli of zero',
        );
    }

    public function test_a_price_list_item_reference_requires_its_list(): void
    {
        $s = $this->scenario('snap');
        $order = $this->orderRow($s, ['subtotal_rupiah' => 17500, 'total_rupiah' => 17500]);
        DB::table('orders')->insert($order);
        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], [
                    'price_list_item_id' => (string) Str::uuid(),
                    'price_list_id' => null,
                ]),
            ),
            'a price_list_item_id with no price_list_id',
        );
    }

    public function test_line_numbers_are_unique_within_an_order(): void
    {
        $s = $this->scenario('lineno');
        $order = $this->orderRow($s, ['subtotal_rupiah' => 35000, 'total_rupiah' => 35000]);
        DB::table('orders')->insert($order);
        DB::table('order_lines')->insert($this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], ['line_number' => 1]));
        $this->assertRejected(
            fn () => DB::table('order_lines')->insert(
                $this->lineRow($s['tenant_id'], $order['id'], $s['service_id'], ['line_number' => 1]),
            ),
            'a duplicate line_number within one order',
        );
    }
}
