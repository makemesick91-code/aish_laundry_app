<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\CustomerManagement\Http\AddressProjection;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Models\CustomerAddress;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * FR-024 / FR-025 — SAVED ADDRESSES AND CONTEXT-AWARE MASKING (SEC-05).
 *
 * The table, the model and a read projection all existed before this. Nothing
 * wrote to them, and the masking was a document. A requirement whose storage
 * exists and whose writer does not is unimplemented with the furniture arranged
 * (Rule 50 — "a table is not a feature").
 *
 * Runs against PostgreSQL, the only engine whose isolation result counts as
 * evidence (Rule 43).
 *
 * Every address here is fictional and recognisably so: `Jl. Contoh Fiktif`,
 * postal codes in a reserved-looking block, and no real district (Rule 23,
 * Rule 45).
 */
final class CustomerAddressTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    private const STREET = 'Jl. Contoh Fiktif No. 12, RT 03 / RW 05';

    private const NOTES = 'Pagar contoh fiktif, seberang pos ronda fiktif.';

    /**
     * @param  list<string>  $roles
     * @return array{tenant: Tenant, customer: Customer, headers: array<string, string>}
     */
    private function scenario(array $roles = [PermissionRegistry::ROLE_TENANT_OWNER]): array
    {
        $this->seedCatalogue();

        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, $roles);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $customer = $this->makeCustomerDirectly($tenant->id, '081200000701');

        return ['tenant' => $tenant, 'customer' => $customer, 'headers' => $headers];
    }

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

    /** @param array<string, mixed> $overrides */
    private function createAddress(array $headers, Customer $customer, array $overrides = []): array
    {
        return $this->withHeaders($headers)->postJson(
            "/api/v1/customers/{$customer->id}/addresses",
            [
                'label' => 'Rumah',
                'address_line' => self::STREET,
                'district' => 'Kelurahan Contoh Fiktif',
                'city' => 'Kota Contoh Fiktif',
                'province' => 'Provinsi Contoh Fiktif',
                'postal_code' => '40123',
                'notes' => self::NOTES,
                ...$overrides,
            ]
        )->assertStatus(201)->json('data.address');
    }

    // =======================================================================
    // FR-024 — the writer
    // =======================================================================

    public function test_an_address_is_created_and_scoped_to_the_tenant_and_customer(): void
    {
        ['tenant' => $tenant, 'customer' => $customer, 'headers' => $headers] = $this->scenario();

        $address = $this->createAddress($headers, $customer);

        $stored = CustomerAddress::query()->findOrFail($address['id']);
        $this->assertSame($tenant->id, $stored->tenant_id);
        $this->assertSame($customer->id, $stored->customer_id);
        $this->assertSame(self::STREET, $stored->address_line);
        $this->assertTrue($stored->is_active);
    }

    public function test_an_address_is_updated_and_reloads_from_the_server(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}", [
                'label' => 'Kantor',
                'city' => 'Kota Contoh Fiktif Dua',
            ])
            ->assertOk()
            ->assertJsonPath('data.address.label', 'Kantor');

        // Re-read rather than trusting the write response. A write that returns
        // the right shape and persists the wrong thing is exactly what a
        // reload catches.
        $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}")
            ->assertOk()
            ->assertJsonPath('data.address.city', 'Kota Contoh Fiktif Dua');
    }

    public function test_archiving_deactivates_without_deleting(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);

        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}/archive")
            ->assertOk()
            ->assertJsonPath('data.address.is_active', false);

        // The ROW SURVIVES. An address a past pickup went to is not removable,
        // or a past custody transfer becomes unexplainable (threat T-18).
        $this->assertNotNull(CustomerAddress::query()->find($address['id']));
    }

    public function test_reactivation_restores_the_address_but_not_its_primary_status(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $first = $this->createAddress($headers, $customer);
        $second = $this->createAddress($headers, $customer, ['label' => 'Kantor', 'is_primary' => true]);

        $this->assertTrue($first !== null && $second['is_primary']);

        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$second['id']}/archive")
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$second['id']}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.address.is_active', true)
            // NOT primary again. A primary that came back on its own would
            // displace whatever the customer has been using since.
            ->assertJsonPath('data.address.is_primary', false);
    }

    public function test_the_first_address_becomes_primary_and_a_later_primary_displaces_it(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();

        $first = $this->createAddress($headers, $customer);
        $this->assertTrue($first['is_primary'], 'the first saved address must become the primary');

        $second = $this->createAddress($headers, $customer, ['label' => 'Kantor', 'is_primary' => true]);
        $this->assertTrue($second['is_primary']);

        // AT MOST ONE. Two primaries would make "where does this customer
        // usually want it delivered" a question with two answers.
        $this->assertSame(
            1,
            CustomerAddress::query()->where('customer_id', $customer->id)->where('is_primary', true)->count()
        );
    }

    public function test_unticking_the_only_primary_is_refused_rather_than_silently_accepted(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}", [
                'is_primary' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.is_primary.0', 'promote_another_first');
    }

    public function test_archiving_the_primary_promotes_nothing(): void
    {
        // Software does not choose where a parcel goes. The customer is left
        // with no primary until a human decides (Rule 09).
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $primary = $this->createAddress($headers, $customer);
        $this->createAddress($headers, $customer, ['label' => 'Kantor']);

        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$primary['id']}/archive")
            ->assertOk();

        $this->assertSame(
            0,
            CustomerAddress::query()->where('customer_id', $customer->id)->where('is_primary', true)->count()
        );
    }

    public function test_a_malformed_postal_code_is_refused(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();

        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses", [
                'label' => 'Rumah',
                'address_line' => self::STREET,
                'postal_code' => 'ABCDE',
            ])
            ->assertStatus(422);
    }

    public function test_an_address_cannot_be_created_against_an_unknown_customer(): void
    {
        ['headers' => $headers] = $this->scenario();

        $this->withHeaders($headers)
            ->postJson('/api/v1/customers/'.Str::uuid().'/addresses', [
                'label' => 'Rumah',
                'address_line' => self::STREET,
            ])
            ->assertNotFound();
    }

    // =======================================================================
    // Tenant isolation — every access path (Rule 48 hard rule 3)
    // =======================================================================

    public function test_tenant_isolation_across_every_address_access_path(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $userA = $this->makeUser(self::PASSWORD);
        $userB = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $headersA = $this->bearer($this->loginToken($userA, self::PASSWORD), $tenantA->id);
        $headersB = $this->bearer($this->loginToken($userB, self::PASSWORD), $tenantB->id);

        $customerB = $this->makeCustomerDirectly($tenantB->id, '081200000702');
        $addressB = $this->createAddress($headersB, $customerB);

        $customerA = $this->makeCustomerDirectly($tenantA->id, '081200000703');

        // (1) DIRECT ID — through B's own customer.
        $this->withHeaders($headersA)
            ->getJson("/api/v1/customers/{$customerB->id}/addresses/{$addressB['id']}")
            ->assertNotFound();

        // (2) LIST, via B's customer.
        $this->withHeaders($headersA)
            ->getJson("/api/v1/customers/{$customerB->id}/addresses")
            ->assertNotFound();

        // (3) B's ADDRESS ID smuggled under A's OWN customer — the path a
        // tenant-scoped customer lookup alone would not close.
        $this->withHeaders($headersA)
            ->getJson("/api/v1/customers/{$customerA->id}/addresses/{$addressB['id']}")
            ->assertNotFound();

        // (4) CROSS-TENANT UPDATE.
        $this->withHeaders($headersA)
            ->patchJson("/api/v1/customers/{$customerA->id}/addresses/{$addressB['id']}", ['label' => 'Dibajak'])
            ->assertNotFound();

        // (5) CROSS-TENANT ARCHIVE.
        $this->withHeaders($headersA)
            ->postJson("/api/v1/customers/{$customerA->id}/addresses/{$addressB['id']}/archive")
            ->assertNotFound();

        // (6) CREATE against B's customer.
        $this->withHeaders($headersA)
            ->postJson("/api/v1/customers/{$customerB->id}/addresses", [
                'label' => 'Rumah', 'address_line' => self::STREET,
            ])
            ->assertNotFound();

        // Nothing changed in tenant B.
        $this->assertSame('Rumah', CustomerAddress::query()->findOrFail($addressB['id'])->label);
        $this->assertTrue(CustomerAddress::query()->findOrFail($addressB['id'])->is_active);
    }

    public function test_a_foreign_address_and_an_absent_address_are_indistinguishable(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');
        $userA = $this->makeUser(self::PASSWORD);
        $userB = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenantA, $userA, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $this->makeMembership($tenantB, $userB, [PermissionRegistry::ROLE_TENANT_OWNER]);

        $headersA = $this->bearer($this->loginToken($userA, self::PASSWORD), $tenantA->id);
        $headersB = $this->bearer($this->loginToken($userB, self::PASSWORD), $tenantB->id);

        $customerA = $this->makeCustomerDirectly($tenantA->id, '081200000704');
        $customerB = $this->makeCustomerDirectly($tenantB->id, '081200000705');
        $addressB = $this->createAddress($headersB, $customerB);

        $foreign = $this->withHeaders($headersA)
            ->getJson("/api/v1/customers/{$customerA->id}/addresses/{$addressB['id']}");
        $absent = $this->withHeaders($headersA)
            ->getJson("/api/v1/customers/{$customerA->id}/addresses/".Str::uuid());

        $this->assertSame($foreign->status(), $absent->status());
        $this->assertSame($foreign->json('error.code'), $absent->json('error.code'));
        $this->assertSame($foreign->json('error.message'), $absent->json('error.message'));
    }

    // =======================================================================
    // FR-025 — masking, enforced server-side
    // =======================================================================

    public function test_a_manage_context_receives_the_full_address(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);

        $body = $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}")
            ->assertOk()
            ->json('data.address');

        $this->assertSame(AddressProjection::CONTEXT_FULL, $body['precision']);
        $this->assertSame(self::STREET, $body['address_line']);
        $this->assertSame('40123', $body['postal_code']);
        $this->assertSame(self::NOTES, $body['notes']);
    }

    /**
     * AREA precision, tested directly against the projection.
     *
     * WHY NOT OVER HTTP, STATED PLAINLY: no role in the shipped registry holds
     * `customer.view` WITHOUT `customer.manage`. The four roles that can reach a
     * customer at all — tenant_owner, tenant_admin, outlet_manager, cashier —
     * hold both, and every other role holds neither. So the AREA branch is
     * currently UNREACHABLE over HTTP, and the contexts a request can actually
     * produce today are FULL and NONE.
     *
     * That is a fact about Step 4's permission set, not a gap in the masking,
     * and it is recorded rather than disguised. An HTTP test that appeared to
     * exercise AREA would have to invent a permission combination the product
     * does not ship, and would then be asserting something about a fixture
     * rather than about the system.
     *
     * The branch is built and tested because Step 8 needs it: a courier needs
     * delivery precision without customer-management rights, and that role will
     * arrive with its own permission (see AddressProjection's note). Building
     * the masking only when that role lands would mean retrofitting a privacy
     * control into a surface already in use.
     */
    public function test_the_area_projection_omits_the_street_rather_than_masking_it(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $created = $this->createAddress($headers, $customer);
        $address = CustomerAddress::query()->findOrFail($created['id']);

        $area = AddressProjection::forContext($address, AddressProjection::CONTEXT_AREA);

        $this->assertSame(AddressProjection::CONTEXT_AREA, $area['precision']);
        $this->assertSame('Kota Contoh Fiktif', $area['city']);
        $this->assertSame('Kelurahan Contoh Fiktif', $area['district']);

        // NOT MERELY MASKED — ABSENT. The fields were never assembled, so there
        // is no hidden full value in the payload to recover, and a client that
        // ignores the documented shape learns nothing extra.
        $this->assertArrayNotHasKey('address_line', $area);
        $this->assertArrayNotHasKey('postal_code', $area);
        $this->assertArrayNotHasKey('notes', $area);

        $serialised = json_encode($area, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::STREET, $serialised);
        $this->assertStringNotContainsString('40123', $serialised);
        $this->assertStringNotContainsString(self::NOTES, $serialised);
    }

    /**
     * The reachability fact above, asserted so it cannot rot silently.
     *
     * If a future role gains `customer.view` without `customer.manage`, this
     * fails and whoever added it is told to extend the HTTP masking tests —
     * rather than the AREA branch quietly becoming live and untested.
     */
    public function test_no_shipped_role_reaches_the_area_context_yet(): void
    {
        $viewOnly = [];

        foreach (PermissionRegistry::roleKeys() as $role) {
            $held = PermissionRegistry::permissionsForTenantRoles([$role]);
            $view = in_array(PermissionRegistry::CUSTOMER_VIEW, $held, true);
            $manage = in_array(PermissionRegistry::CUSTOMER_MANAGE, $held, true);

            if ($view && ! $manage) {
                $viewOnly[] = $role;
            }
        }

        $this->assertSame(
            [],
            $viewOnly,
            'A role now holds customer.view without customer.manage, so the AREA '
            .'masking context is reachable over HTTP for the first time. Add HTTP-level '
            .'masking coverage for it before this is relaxed: '.implode(', ', $viewOnly)
        );
    }

    public function test_a_context_with_no_customer_permission_receives_no_address(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();

        $owner = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $owner, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $ownerHeaders = $this->bearer($this->loginToken($owner, self::PASSWORD), $tenant->id);

        $customer = $this->makeCustomerDirectly($tenant->id, '081200000707');
        $address = $this->createAddress($ownerHeaders, $customer);

        $operator = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $operator, [PermissionRegistry::ROLE_PRODUCTION_OPERATOR]);
        $operatorHeaders = $this->bearer($this->loginToken($operator, self::PASSWORD), $tenant->id);

        $response = $this->withHeaders($operatorHeaders)
            ->getJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}");

        $this->assertContains($response->status(), [403, 404]);
        $this->assertStringNotContainsString(self::STREET, $response->getContent());
    }

    public function test_the_list_projection_carries_no_location_even_at_full_precision(): void
    {
        // Rule 32 §2.2 rule 7 — fifty addresses on one screen is the tenant's
        // customer base in a single photograph. This holds for the HIGHEST
        // permission level, which is what makes it a projection rule rather than
        // a permission rule.
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $this->createAddress($headers, $customer);

        $response = $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}/addresses")
            ->assertOk();

        $this->assertSame(AddressProjection::CONTEXT_FULL, $response->json('data.precision'));

        $row = $response->json('data.addresses.0');
        $this->assertSame('Rumah', $row['label']);
        $this->assertArrayNotHasKey('address_line', $row);
        $this->assertArrayNotHasKey('district', $row);
        $this->assertArrayNotHasKey('city', $row);

        $this->assertStringNotContainsString(self::STREET, $response->getContent());
    }

    public function test_the_customer_detail_projection_routes_through_the_same_masking(): void
    {
        // The relationship path. A masked address endpoint would be pointless if
        // the customer detail endpoint embedded the full address anyway — a
        // relationship is an access path like any other (Rule 48 hard rule 3).
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $this->createAddress($headers, $customer);

        // A manage context legitimately sees it here.
        $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.customer.addresses.0.precision', AddressProjection::CONTEXT_FULL);

        // And the projection is the one that decides, not the controller: asked
        // for a context that grants nothing, it emits nothing, so a caller that
        // reaches this path without address rights receives no address rather
        // than a filtered copy of one.
        $stored = CustomerAddress::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertNull(AddressProjection::forContext($stored, AddressProjection::CONTEXT_NONE));
    }

    /**
     * The fail-closed default, asserted rather than merely commented.
     *
     * `CustomerProjection::detail()` takes a nullable context so that an
     * existing caller cannot silently pass the WRONG one by omission. The
     * comment said omission yields no addresses; nothing checked it, because
     * every caller passes a context explicitly — an adversarial mutation
     * flipping the default from NONE to FULL passed the entire suite. A
     * fail-closed default nobody exercises is a fail-closed default nobody
     * knows is broken.
     */
    public function test_the_detail_projection_defaults_to_disclosing_no_address(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $this->createAddress($headers, $customer);

        $projected = \App\Modules\CustomerManagement\Http\CustomerProjection::detail(
            $customer->fresh(['addresses'])
        );

        $this->assertSame([], $projected['addresses']);
        $this->assertStringNotContainsString(
            self::STREET,
            json_encode($projected, JSON_THROW_ON_ERROR)
        );
    }

    public function test_a_search_result_never_carries_an_address(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $this->createAddress($headers, $customer);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/customers?q=081200000701')
            ->assertOk();

        $this->assertStringNotContainsString(self::STREET, $response->getContent());
    }

    public function test_no_address_value_is_ever_placed_in_a_url(): void
    {
        // A path or query string is logged by every proxy in front of the
        // application, kept in browser history, and passed on in a referrer.
        // Addresses are referenced by opaque identifier only.
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(static fn ($r): string => $r->uri())
            ->filter(static fn (string $u): bool => str_contains($u, 'addresses'))
            ->values()
            ->all();

        foreach ($routes as $uri) {
            foreach (['address_line', 'postal', 'city', 'district'] as $leak) {
                $this->assertStringNotContainsString($leak, $uri, "route {$uri} names an address field");
            }
        }
    }

    // =======================================================================
    // Concurrency, audit, and error hygiene
    // =======================================================================

    public function test_a_stale_write_is_refused_with_conflict_and_leaves_the_session_intact(): void
    {
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);
        $staleVersion = (string) $address['version'];

        $this->withHeaders($headers)
            ->patchJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}", ['label' => 'Kantor'])
            ->assertOk();

        $this->withHeaders([...$headers, 'If-Unmodified-Since-Version' => $staleVersion])
            ->patchJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}", ['label' => 'Gudang'])
            ->assertStatus(409);

        // The refused edit changed nothing.
        $this->assertSame('Kantor', CustomerAddress::query()->findOrFail($address['id'])->label);

        // And the SESSION survives. A conflict is a record-scoped refusal, never
        // a reason to end a session or clear a credential.
        $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}/addresses")
            ->assertOk();
    }

    public function test_every_address_write_is_audited_without_recording_the_address(): void
    {
        ['tenant' => $tenant, 'customer' => $customer, 'headers' => $headers] = $this->scenario();
        $address = $this->createAddress($headers, $customer);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}", ['label' => 'Kantor'])
            ->assertOk();
        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}/archive")
            ->assertOk();
        $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses/{$address['id']}/reactivate")
            ->assertOk();

        foreach ([
            AuditAction::CUSTOMER_ADDRESS_CREATED,
            AuditAction::CUSTOMER_ADDRESS_UPDATED,
            AuditAction::CUSTOMER_ADDRESS_ARCHIVED,
            AuditAction::CUSTOMER_ADDRESS_REACTIVATED,
        ] as $action) {
            $this->assertNotNull(
                AuditEntry::query()->where('tenant_id', $tenant->id)->where('action', $action)->first(),
                "no audit entry for {$action}"
            );
        }

        // THE AUDIT MUST NOT BECOME A SECOND COPY OF THE ADDRESS. The trail
        // records that an address changed and who changed it — not where
        // somebody lives, in a table with different retention and a different
        // audience (Rule 46 hard rule 2).
        $rows = AuditEntry::query()->get()
            ->map(static fn (AuditEntry $e): string => json_encode($e->toArray(), JSON_THROW_ON_ERROR))
            ->implode("\n");

        foreach ([self::STREET, self::NOTES, '40123', 'Kelurahan Contoh Fiktif'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $rows, "an audit row carries '{$forbidden}'");
        }
    }

    public function test_a_validation_error_does_not_echo_the_address_back(): void
    {
        // An error body is the most-copied thing in a support ticket.
        ['customer' => $customer, 'headers' => $headers] = $this->scenario();

        $response = $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses", [
                'label' => 'Rumah',
                'address_line' => self::STREET,
                'postal_code' => 'BUKAN-KODE-POS',
            ])
            ->assertStatus(422);

        $this->assertStringNotContainsString(self::STREET, $response->getContent());
    }

    public function test_a_client_supplied_tenant_id_in_the_body_is_ignored(): void
    {
        $this->seedCatalogue();
        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenantA, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenantA->id);

        $customer = $this->makeCustomerDirectly($tenantA->id, '081200000709');

        $address = $this->withHeaders($headers)
            ->postJson("/api/v1/customers/{$customer->id}/addresses", [
                'label' => 'Rumah',
                'address_line' => self::STREET,
                // An untrusted hint, never authorization proof (Rule 39 hard rule 1).
                'tenant_id' => $tenantB->id,
                'customer_id' => Str::uuid()->toString(),
            ])
            ->assertStatus(201)
            ->json('data.address');

        $stored = CustomerAddress::query()->findOrFail($address['id']);
        $this->assertSame($tenantA->id, $stored->tenant_id);
        $this->assertSame($customer->id, $stored->customer_id);
    }
}
