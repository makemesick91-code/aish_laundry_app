<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * Customer master data — FR-021 … FR-030.
 *
 * Runs against PostgreSQL, which is the only engine whose result counts as
 * tenant-isolation evidence (Rule 43, hard rules 1-2). Several assertions here
 * depend on PostgreSQL-specific behaviour — partial unique indexes, check
 * constraints, and the append-only RULEs on `customer_consents` — and would
 * silently pass on a substitute engine that ignores them.
 *
 * Every value is fictional and recognisably so (Rule 23, Rule 45).
 */
final class CustomerMasterDataTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // FR-021 / FR-023 — profile and search
    // -----------------------------------------------------------------------

    public function test_a_customer_is_created_and_scoped_to_the_active_tenant(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Uji Fiktif',
            'phone' => '081200000001',
        ], $this->bearer($token, $tenant->id));

        $response->assertCreated();

        $id = $response->json('data.customer.id');
        $this->assertNotNull($id);

        $customer = Customer::query()->findOrFail($id);
        $this->assertSame($tenant->id, $customer->tenant_id);
        $this->assertSame('6281200000001', $customer->phone_normalized);
        $this->assertSame(Customer::STATUS_ACTIVE, $customer->status);
    }

    public function test_phone_is_normalized_server_side_so_different_renderings_are_one_customer(): void
    {
        $this->assertSame('6281200000001', PhoneNumber::normalize('081200000001'));
        $this->assertSame('6281200000001', PhoneNumber::normalize('+62 812-0000-0001'));
        $this->assertSame('6281200000001', PhoneNumber::normalize('62 812 0000 0001'));
        $this->assertSame('6281200000001', PhoneNumber::normalize('812 0000 0001'));
    }

    public function test_a_duplicate_phone_within_one_tenant_is_rejected_and_never_merged(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $first = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Satu Fiktif',
            'phone' => '081200000002',
        ], $this->bearer($token, $tenant->id))->assertCreated();

        // Same person, typed differently.
        $second = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Satu Fiktif',
            'phone' => '+62 812 0000 0002',
        ], $this->bearer($token, $tenant->id));

        $second->assertStatus(422);
        $this->assertSame(
            $first->json('data.customer.id'),
            $second->json('error.details.existing_customer_id'),
            'the conflict must name the existing customer so an operator can act on it'
        );

        // DETECTED, NOT MERGED: still exactly one row.
        $this->assertSame(1, Customer::query()->forTenant($tenant->id)->count());
    }

    /**
     * FR-022 — the requirement this whole aggregate exists to protect.
     */
    public function test_the_same_phone_in_two_tenants_is_two_unrelated_customers(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $tokenA = $this->loginToken($userA);
        $tokenB = $this->loginToken($userB);

        $sharedPhone = '081200000003';

        $a = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Bersama Fiktif',
            'phone' => $sharedPhone,
        ], $this->bearer($tokenA, $tenantA->id))->assertCreated();

        // The SAME number in another tenant must succeed, not conflict.
        $b = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Bersama Fiktif',
            'phone' => $sharedPhone,
        ], $this->bearer($tokenB, $tenantB->id))->assertCreated();

        $this->assertNotSame($a->json('data.customer.id'), $b->json('data.customer.id'));

        $rows = Customer::query()->where('phone_normalized', PhoneNumber::normalize($sharedPhone))->get();
        $this->assertCount(2, $rows, 'two unrelated profiles, never merged');
        $this->assertEqualsCanonicalizing(
            [$tenantA->id, $tenantB->id],
            $rows->pluck('tenant_id')->all()
        );
    }

    // -----------------------------------------------------------------------
    // Tenant isolation — every access path (Rule 48, hard rule 3)
    // -----------------------------------------------------------------------

    public function test_tenant_isolation_across_every_access_path(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $tokenA = $this->loginToken($userA);
        $tokenB = $this->loginToken($userB);

        // A record that belongs to tenant B only.
        $bCustomerId = $this->postJson('/api/v1/customers', [
            'name' => 'Rahasia Tenant B Fiktif',
            'phone' => '081200000004',
        ], $this->bearer($tokenB, $tenantB->id))->assertCreated()->json('data.customer.id');

        $headersA = $this->bearer($tokenA, $tenantA->id);

        // (1) DIRECT ID
        $this->getJson("/api/v1/customers/{$bCustomerId}", $headersA)->assertNotFound();

        // (2) LIST
        $list = $this->getJson('/api/v1/customers', $headersA)->assertOk();
        $this->assertSame([], $list->json('data.customers'));
        $this->assertSame(0, $list->json('data.pagination.total'));

        // (3) FILTER
        $this->getJson('/api/v1/customers?status=active', $headersA)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        // (4) FREE-TEXT SEARCH — by name AND by the other tenant's phone.
        $this->getJson('/api/v1/customers?q=Rahasia', $headersA)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
        $this->getJson('/api/v1/customers?q=081200000004', $headersA)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        // (5) MUTATION through a foreign id
        $this->patchJson("/api/v1/customers/{$bCustomerId}", ['name' => 'Diubah'], $headersA)
            ->assertNotFound();
        $this->postJson("/api/v1/customers/{$bCustomerId}/archive", [], $headersA)
            ->assertNotFound();

        // (6) RELATED-RESOURCE traversal
        $this->getJson("/api/v1/customers/{$bCustomerId}/consents", $headersA)->assertNotFound();
        $this->postJson("/api/v1/customers/{$bCustomerId}/consents", [
            'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
            'state' => CustomerConsent::STATE_GRANTED,
            'source' => 'counter',
        ], $headersA)->assertNotFound();

        // Tenant B's record is untouched by any of the above.
        $this->assertSame(
            'Rahasia Tenant B Fiktif',
            Customer::query()->findOrFail($bCustomerId)->name
        );
    }

    public function test_a_foreign_record_and_an_absent_record_are_indistinguishable(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $tokenA = $this->loginToken($userA);
        $tokenB = $this->loginToken($userB);

        $foreignId = $this->postJson('/api/v1/customers', [
            'name' => 'Milik B Fiktif',
            'phone' => '081200000005',
        ], $this->bearer($tokenB, $tenantB->id))->assertCreated()->json('data.customer.id');

        $absentId = '00000000-0000-4000-8000-000000000000';
        $headersA = $this->bearer($tokenA, $tenantA->id);

        $foreign = $this->getJson("/api/v1/customers/{$foreignId}", $headersA)->assertNotFound();
        $absent = $this->getJson("/api/v1/customers/{$absentId}", $headersA)->assertNotFound();

        // Rule 48, hard rule 5: the two cases render identically.
        $this->assertSame($foreign->json('error.code'), $absent->json('error.code'));
        $this->assertSame($foreign->json('error.message'), $absent->json('error.message'));
    }

    // -----------------------------------------------------------------------
    // Mass assignment (threat T-05)
    // -----------------------------------------------------------------------

    public function test_a_client_supplied_tenant_id_in_the_body_is_ignored(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userA = $this->makeUser();
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $tokenA = $this->loginToken($userA);

        $id = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000006',
            // Hostile input: all three are server-derived and must be ignored.
            'tenant_id' => $tenantB->id,
            'code' => 'DIPILIH-SENDIRI',
            'phone_normalized' => '629999999999',
        ], $this->bearer($tokenA, $tenantA->id))->assertCreated()->json('data.customer.id');

        $customer = Customer::query()->findOrFail($id);

        $this->assertSame($tenantA->id, $customer->tenant_id);
        $this->assertNotSame('DIPILIH-SENDIRI', $customer->code);
        $this->assertSame(PhoneNumber::normalize('081200000006'), $customer->phone_normalized);
    }

    // -----------------------------------------------------------------------
    // FR-025 / FR-026 / FR-030 — masking and the allow-list projection
    // -----------------------------------------------------------------------

    public function test_the_list_projection_masks_the_phone_and_omits_notes_and_address(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000007',
            'internal_notes' => 'Catatan internal rahasia',
        ], $this->bearer($token, $tenant->id))->assertCreated();

        $row = $this->getJson('/api/v1/customers', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->json('data.customers.0');

        $this->assertArrayHasKey('phone_masked', $row);
        $this->assertArrayNotHasKey('phone', $row, 'a list row never carries the full phone');
        $this->assertArrayNotHasKey('phone_normalized', $row, 'the match key is not display data');
        $this->assertArrayNotHasKey('internal_notes', $row);
        $this->assertArrayNotHasKey('addresses', $row, 'Rule 32 hard rule 4: no address in a list row');
        $this->assertStringNotContainsString('81200000007', json_encode($row, JSON_THROW_ON_ERROR));
    }

    // -----------------------------------------------------------------------
    // FR-027 / FR-028 — consent
    // -----------------------------------------------------------------------

    public function test_consent_is_recorded_with_a_server_side_timestamp_and_actor(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);
        $headers = $this->bearer($token, $tenant->id);

        $id = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000008',
        ], $headers)->assertCreated()->json('data.customer.id');

        $this->postJson("/api/v1/customers/{$id}/consents", [
            'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
            'state' => CustomerConsent::STATE_GRANTED,
            'source' => 'counter',
            // A hostile backdate. Must be ignored (threat T-07).
            'recorded_at' => '1999-01-01T00:00:00+07:00',
        ], $headers)->assertCreated();

        $consent = CustomerConsent::query()->where('customer_id', $id)->firstOrFail();

        $this->assertSame($membership->id, $consent->recorded_by_membership_id);
        $this->assertTrue(
            $consent->recorded_at->year >= 2026,
            'recorded_at is server-side and cannot be backdated by the client'
        );
    }

    public function test_withdrawal_appends_a_record_and_the_latest_state_governs(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);
        $headers = $this->bearer($token, $tenant->id);

        $id = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000009',
        ], $headers)->assertCreated()->json('data.customer.id');

        foreach ([CustomerConsent::STATE_GRANTED, CustomerConsent::STATE_WITHDRAWN] as $state) {
            $this->postJson("/api/v1/customers/{$id}/consents", [
                'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
                'state' => $state,
                'source' => 'counter',
            ], $headers)->assertCreated();
        }

        $customer = Customer::query()->findOrFail($id);

        $this->assertSame(2, CustomerConsent::query()->where('customer_id', $id)->count());
        $this->assertFalse($customer->hasMarketingConsent(CustomerConsent::TYPE_MARKETING_WHATSAPP));

        $this->assertSame(
            CustomerConsent::STATE_WITHDRAWN,
            $this->getJson("/api/v1/customers/{$id}/consents", $headers)
                ->assertOk()
                ->json('data.current.'.CustomerConsent::TYPE_MARKETING_WHATSAPP)
        );
    }

    public function test_no_consent_record_at_all_is_not_consent(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $customer = $this->makeCustomerDirectly($tenant->id, '081200000010');

        $this->assertNull($customer->currentConsentState(CustomerConsent::TYPE_MARKETING_WHATSAPP));
        $this->assertFalse($customer->hasMarketingConsent(CustomerConsent::TYPE_MARKETING_WHATSAPP));
    }

    /**
     * FR-028 — the requirement that makes consent history evidence.
     *
     * Three layers are asserted separately, because each covers a path the
     * others cannot see.
     */
    public function test_an_opt_out_cannot_be_reset_by_the_model(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $customer = $this->makeCustomerDirectly($tenant->id, '081200000011');
        $consent = $this->makeConsentDirectly($customer, CustomerConsent::STATE_WITHDRAWN);

        $this->expectException(RuntimeException::class);

        $consent->state = CustomerConsent::STATE_GRANTED;
        $consent->save();
    }

    public function test_an_opt_out_cannot_be_reset_by_raw_sql_bypassing_the_application(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $customer = $this->makeCustomerDirectly($tenant->id, '081200000012');
        $this->makeConsentDirectly($customer, CustomerConsent::STATE_WITHDRAWN);

        // The path FR-028 actually names: a migration or an import running
        // OUTSIDE the application. The PostgreSQL RULE makes it a no-op.
        DB::statement("UPDATE customer_consents SET state = 'granted'");
        DB::statement('DELETE FROM customer_consents');

        $this->assertSame(1, CustomerConsent::query()->where('customer_id', $customer->id)->count());
        $this->assertSame(
            CustomerConsent::STATE_WITHDRAWN,
            $customer->fresh()->currentConsentState(CustomerConsent::TYPE_MARKETING_WHATSAPP)
        );
    }

    // -----------------------------------------------------------------------
    // Authorization (Rule 40)
    // -----------------------------------------------------------------------

    public function test_a_role_without_customer_permission_is_denied(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        // A courier holds no customer permission at all.
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_COURIER]);
        $token = $this->loginToken($user);

        $this->getJson('/api/v1/customers', $this->bearer($token, $tenant->id))->assertForbidden();
        $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000013',
        ], $this->bearer($token, $tenant->id))->assertForbidden();
    }

    public function test_a_cashier_may_manage_customers_but_not_their_consent(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_CASHIER]);
        $token = $this->loginToken($user);
        $headers = $this->bearer($token, $tenant->id);

        $id = $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Fiktif',
            'phone' => '081200000014',
        ], $headers)->assertCreated()->json('data.customer.id');

        // Consent is a separate permission: a kasir records customers, not
        // legal positions.
        $this->postJson("/api/v1/customers/{$id}/consents", [
            'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
            'state' => CustomerConsent::STATE_GRANTED,
            'source' => 'counter',
        ], $headers)->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Bounded query surface (threats T-17, T-19, T-20)
    // -----------------------------------------------------------------------

    public function test_an_unknown_sort_field_is_rejected_rather_than_interpolated(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $this->getJson('/api/v1/customers?sort=internal_notes', $this->bearer($token, $tenant->id))
            ->assertStatus(422);
    }

    public function test_page_size_is_capped(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser();
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $token = $this->loginToken($user);

        $this->getJson('/api/v1/customers?per_page=100000', $this->bearer($token, $tenant->id))
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 100);
    }

    public function test_no_bulk_mutation_or_export_route_exists_in_step_4(): void
    {
        $names = collect(app('router')->getRoutes())
            ->map(static fn ($r) => (string) $r->uri())
            ->filter(static fn (string $u) => str_contains($u, 'customers'))
            ->values();

        foreach ($names as $uri) {
            $this->assertStringNotContainsString('export', $uri);
            $this->assertStringNotContainsString('bulk', $uri);
        }
    }

    // -----------------------------------------------------------------------
    // Fixtures. Explicit, so the tenant binding stays visible at the call site.
    // -----------------------------------------------------------------------

    private function makeCustomerDirectly(string $tenantId, string $phone): Customer
    {
        $customer = new Customer(['name' => 'Pelanggan Uji Fiktif', 'phone' => $phone]);
        $customer->tenant_id = $tenantId;
        $customer->phone_normalized = PhoneNumber::normalize($phone);
        $customer->code = 'PLG-'.substr($phone, -6);
        $customer->status = Customer::STATUS_ACTIVE;
        $customer->save();

        return $customer;
    }

    private function makeConsentDirectly(Customer $customer, string $state): CustomerConsent
    {
        $consent = new CustomerConsent([
            'consent_type' => CustomerConsent::TYPE_MARKETING_WHATSAPP,
            'state' => $state,
            'source' => 'counter',
        ]);
        $consent->tenant_id = $customer->tenant_id;
        $consent->customer_id = $customer->id;
        $consent->recorded_at = now();
        $consent->save();

        return $consent;
    }
}
