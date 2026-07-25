<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\Ordering\Models\Order;
use App\Modules\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Step 7 fixtures: a tenant with an outlet, a customer, and a placed order.
 *
 * Written out longhand rather than as factories, for the same reason
 * BuildsTenantScenario is: every relationship here crosses a tenant boundary the
 * schema constrains, and an isolation test needs to be able to vary that binding
 * on purpose at the call site.
 *
 * EVERY VALUE IS FICTIONAL AND RECOGNISABLY SO (Rule 23, Rule 45). The phone
 * numbers are sequential zero-padded placeholders that cannot reach a subscriber;
 * the names are marked "Fiktif"; the addresses are invented. This repository is
 * PUBLIC and a plausible-looking fake reads as a real disclosure to an outsider.
 */
trait BuildsTrackingScenario
{
    use BuildsTenantScenario;

    /**
     * A complete, placed order with its tenant context.
     *
     * @return array{context: TenantContext, order: Order, customer_id: string, outlet_id: string}
     */
    protected function trackingScenario(string $slug, string $timezone = 'Asia/Jakarta'): array
    {
        $tenant = $this->makeTenant($slug, 'Tenant Fiktif '.$slug);
        $brand = $this->makeBrand($tenant, 'Merek Fiktif '.$slug);
        $outlet = $this->makeOutlet($tenant, $brand, 'Outlet Fiktif '.$slug);

        // The outlet timezone drives quiet hours (FR-097). Varying it is how the
        // multi-timezone tests prove evaluation is OUTLET-local, not server-local.
        $outlet->forceFill(['timezone' => $timezone])->save();

        $user = $this->makeUser(email: 'uji.'.Str::lower(Str::random(10)).'@contoh.invalid');
        $membership = $this->makeMembership($tenant, $user);

        $customerId = (string) Str::uuid();
        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'code' => 'CUST-'.Str::upper(Str::random(8)),
            'name' => 'Budi Santoso Fiktif',
            'phone' => '081200000000',
            // Recognisably fabricated: an all-zero subscriber body.
            'phone_normalized' => '6281200000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serviceId = (string) Str::uuid();
        DB::table('service_catalog')->insert([
            'id' => $serviceId,
            'tenant_id' => $tenant->id,
            'code' => 'SVC-'.Str::upper(Str::random(8)),
            'name' => 'Cuci Kiloan (fiktif)',
            'unit_kind' => 'kiloan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'outlet_id' => $outlet->id,
            'customer_id' => $customerId,
            // Short, sequential-looking, and PRINTED — guessable by design, which
            // is exactly why it grants no access (FR-087, TRK-003).
            'order_number' => 'ALS-2026-000042',
            'client_reference' => (string) Str::uuid(),
            'status' => Order::STATUS_RECEIVED,
            'subtotal_rupiah' => 24000,
            'discount_rupiah' => 0,
            'total_rupiah' => 24000,
            'placed_at' => now()->subHours(2),
            // An internal note. The projection must NEVER surface this (TRK-016).
            'special_instructions' => 'Catatan internal: pelanggan minta jangan pakai pewangi.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'order_id' => $orderId,
            'line_number' => 1,
            'service_id' => $serviceId,
            'service_name' => 'Cuci Kiloan (fiktif)',
            'unit' => 'kilogram',
            'quantity_milli' => 3000,
            'unit_price_rupiah' => 8000,
            'subtotal_rupiah' => 24000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'context' => new TenantContext($tenant, $membership, $outlet),
            'order' => Order::query()->forTenant($tenant->id)->findOrFail($orderId),
            'customer_id' => $customerId,
            'outlet_id' => $outlet->id,
        ];
    }

    /** A saved address, so "the portal never shows a full address" has something to fail on. */
    protected function giveCustomerAnAddress(string $tenantId, string $customerId): string
    {
        $addressId = (string) Str::uuid();

        DB::table('customer_addresses')->insert([
            'id' => $addressId,
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'label' => 'Rumah',
            'address_line' => 'Jalan Contoh Fiktif Nomor 123, RT 04 RW 07',
            'district' => 'Kecamatan Fiktif',
            'city' => 'Kota Fiktif',
            'province' => 'Provinsi Fiktif',
            'postal_code' => '40000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $addressId;
    }

    /** Record a marketing consent decision (Step 4's append-only table, FR-027/FR-028). */
    protected function recordConsent(string $tenantId, string $customerId, string $state): void
    {
        DB::table('customer_consents')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
            'state' => $state,
            'source' => 'uji',
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function ref(): string
    {
        return (string) Str::uuid();
    }
}
