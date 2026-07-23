<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Authorization\PermissionRegistry;
use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * SERVICE CATALOGUE AND PRICE-LIST HTTP SURFACES — FR-031 … FR-040.
 *
 * The persistence and the money constraints were already covered by
 * `PriceListIntegrityTest`. This suite covers the APPLICATION SURFACE those
 * constraints sit behind: authorization, tenant scoping on every access path,
 * the draft/publish boundary, and the refusal of money that is not integer
 * Rupiah.
 *
 * Runs against PostgreSQL (Rule 43). Every value is fictional (Rule 23).
 */
final class ServiceCatalogSurfaceTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /** @return array{tenant: Tenant, brand: LaundryBrand, token: string} */
    private function ownerScenario(): array
    {
        $this->seedCatalogue();

        $tenant = $this->makeTenant();
        $brand = $this->makeBrand($tenant);
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);

        return [
            'tenant' => $tenant,
            'brand' => $brand,
            'token' => $this->loginToken($user, self::PASSWORD),
        ];
    }

    /** @return array<string, string> */
    private function asRole(Tenant $tenant, string $role): array
    {
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [$role]);

        return $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);
    }

    /** Create an active service and return its id. */
    private function makeService(array $headers, string $code = 'CUCI-KILO'): string
    {
        return $this->withHeaders($headers)
            ->postJson('/api/v1/services', [
                'code' => $code,
                'name' => 'Cuci Kiloan Uji',
                'unit_kind' => Service::UNIT_KILOAN,
                'minimum_quantity' => 2000,
            ])
            ->assertStatus(201)
            ->json('data.service.id');
    }

    // ==================================================================
    // FR-031 — kiloan and satuan
    // ==================================================================

    public function test_a_kiloan_and_a_satuan_service_are_created(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $this->withHeaders($headers)
            ->postJson('/api/v1/services', [
                'code' => 'CUCI-KILO',
                'name' => 'Cuci Kiloan Uji',
                'unit_kind' => Service::UNIT_KILOAN,
                'minimum_quantity' => 2000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.service.unit_kind', 'kiloan')
            // The unit is stated, so a reader never has to guess whether 2000 is
            // grams or items.
            ->assertJsonPath('data.service.minimum_quantity_unit', 'gram');

        $this->withHeaders($headers)
            ->postJson('/api/v1/services', [
                'code' => 'SETRIKA-PCS',
                'name' => 'Setrika Satuan Uji',
                'unit_kind' => Service::UNIT_SATUAN,
                'minimum_quantity' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.service.minimum_quantity_unit', 'item');
    }

    public function test_a_free_text_unit_kind_is_refused(): void
    {
        // FR-031 — no free-text unit. A downstream reader must be able to tell a
        // weight from a count without parsing a label.
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson('/api/v1/services', [
                'code' => 'ANEH',
                'name' => 'Layanan Uji',
                'unit_kind' => 'per lusin',
            ])
            ->assertStatus(422);
    }

    public function test_the_same_service_code_is_permitted_in_two_different_tenants(): void
    {
        // A code unique GLOBALLY would disclose that another tenant holds it
        // (Rule 32); uniqueness is `(tenant_id, code)`.
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $idA = $this->makeService($this->bearer($tokenA, $tenantA->id));
        $idB = $this->makeService($this->bearer($tokenB, $tenantB->id));

        $this->assertNotSame($idA, $idB);
    }

    public function test_a_duplicate_service_code_within_one_tenant_is_refused(): void
    {
        // KEPT AS ITS OWN TEST, and the constraint attempt is the LAST thing it
        // does, on purpose.
        //
        // `RefreshDatabase` wraps each test in one transaction, and PostgreSQL
        // aborts a transaction outright once a statement in it fails — every
        // later query then returns 25P02 regardless of what it asks. That is an
        // artefact of the test harness, not of the application: in production
        // each request is its own transaction, so a refused duplicate leaves the
        // next request entirely unaffected.
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $this->makeService($headers);

        $this->withHeaders($headers)
            ->postJson('/api/v1/services', [
                'code' => 'CUCI-KILO',
                'name' => 'Duplikat Uji',
                'unit_kind' => Service::UNIT_SATUAN,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.code.0', 'duplicate');
    }

    public function test_a_zero_minimum_quantity_is_refused(): void
    {
        // A minimum of zero is not a minimum (invariant S3).
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson('/api/v1/services', [
                'code' => 'NOL',
                'name' => 'Layanan Uji Nol',
                'unit_kind' => Service::UNIT_KILOAN,
                'minimum_quantity' => 0,
            ])
            ->assertStatus(422);
    }

    public function test_a_category_from_another_tenant_does_not_resolve(): void
    {
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $foreignCategory = $this->withHeaders($this->bearer($tokenB, $tenantB->id))
            ->postJson('/api/v1/service-categories', ['code' => 'KAT-B', 'name' => 'Kategori Uji B'])
            ->json('data.category.id');

        // Indistinguishable from "no such category" (Rule 48 hard rule 5).
        $this->withHeaders($this->bearer($tokenA, $tenantA->id))
            ->postJson('/api/v1/services', [
                'code' => 'LINTAS',
                'name' => 'Layanan Uji',
                'unit_kind' => Service::UNIT_SATUAN,
                'service_category_id' => $foreignCategory,
            ])
            ->assertStatus(404);
    }

    // ==================================================================
    // FR-032 — packages
    // ==================================================================

    public function test_a_package_composition_is_replaced_wholesale(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $serviceA = $this->makeService($headers, 'CUCI-A');
        $serviceB = $this->makeService($headers, 'CUCI-B');

        $package = $this->withHeaders($headers)
            ->postJson('/api/v1/service-packages', ['code' => 'PKT-1', 'name' => 'Paket Uji Satu'])
            ->assertStatus(201)
            ->json('data.package.id');

        $this->withHeaders($headers)
            ->putJson("/api/v1/service-packages/{$package}/items", [
                'items' => [
                    ['service_id' => $serviceA, 'quantity' => 2],
                    ['service_id' => $serviceB, 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.package.items');

        // Replacement, not merge: the composition is meaningful only as a whole.
        $this->withHeaders($headers)
            ->putJson("/api/v1/service-packages/{$package}/items", [
                'items' => [['service_id' => $serviceA, 'quantity' => 5]],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.package.items')
            ->assertJsonPath('data.package.items.0.quantity', 5);
    }

    public function test_a_package_cannot_compose_another_tenants_service(): void
    {
        // INVARIANT S5. The composite foreign key would refuse it; resolving
        // within the tenant first gives a clean 404 instead of a constraint
        // error, and discloses nothing.
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $headersA = $this->bearer($tokenA, $tenantA->id);
        $foreignService = $this->makeService($this->bearer($tokenB, $tenantB->id), 'CUCI-B');

        $package = $this->withHeaders($headersA)
            ->postJson('/api/v1/service-packages', ['code' => 'PKT-1', 'name' => 'Paket Uji'])
            ->json('data.package.id');

        $this->withHeaders($headersA)
            ->putJson("/api/v1/service-packages/{$package}/items", [
                'items' => [['service_id' => $foreignService, 'quantity' => 1]],
            ])
            ->assertStatus(404);
    }

    // ==================================================================
    // FR-033 — add-ons are catalogue entries only
    // ==================================================================

    public function test_an_addon_is_a_catalogue_entry_with_no_order_linkage(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $body = $this->withHeaders($headers)
            ->postJson('/api/v1/service-addons', ['code' => 'EXPRESS', 'name' => 'Express Uji'])
            ->assertStatus(201)
            ->json('data.addon');

        // DEC-0031 B — applying an add-on to an order line is Step 5. The
        // projection must expose no order, order-line, or quantity linkage.
        foreach (['order_id', 'order_line_id', 'quantity', 'amount_rupiah'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $body);
        }
    }

    // ==================================================================
    // FR-034 … FR-036 — price lists, publishing, immutability
    // ==================================================================

    public function test_a_draft_price_list_is_created_for_a_brand(): void
    {
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();

        $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-2026-01',
                'name' => 'Daftar Harga Uji 2026',
                'effective_from' => '2026-08-01',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.price_list.status', 'draft')
            ->assertJsonPath('data.price_list.currency', 'IDR')
            ->assertJsonPath('data.price_list.is_editable', true);
    }

    public function test_a_brand_from_another_tenant_is_refused(): void
    {
        // FR-034 + invariant P1. The brand's tenant is re-derived server-side;
        // the supplied id is an untrusted hint (Rule 39 hard rule 1, AC-T3).
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['brand' => $brandB] = $this->ownerScenario();

        $this->withHeaders($this->bearer($tokenA, $tenantA->id))
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brandB->id,
                'code' => 'HRG-LINTAS',
                'name' => 'Daftar Harga Lintas Tenant',
                'effective_from' => '2026-08-01',
            ])
            ->assertStatus(404);
    }

    public function test_a_published_price_list_cannot_be_edited(): void
    {
        // FR-035, FR-036. A Step 5 order captures a price and a reprinted
        // document must resolve it, so a published version is frozen.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $list = $this->withHeaders($headers)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        $item = $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", [
                'service_id' => $service,
                'amount_rupiah' => 7000,
            ])
            ->assertStatus(201)
            ->json('data.item.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/publish")
            ->assertOk()
            ->assertJsonPath('data.price_list.status', 'active')
            ->assertJsonPath('data.price_list.is_editable', false);

        // Adding, editing, and removing are all refused once published.
        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", ['service_id' => $service, 'amount_rupiah' => 9000])
            ->assertStatus(422)
            ->assertJsonPath('error.details.price_list.0', 'published');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/price-lists/{$list}/items/{$item}", ['amount_rupiah' => 9000])
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/price-lists/{$list}/items/{$item}")
            ->assertStatus(422);

        // And the stored amount is untouched.
        $this->assertSame(
            7000,
            (int) DB::table('price_list_items')->where('id', $item)->value('amount_rupiah')
        );
    }

    public function test_publishing_requires_its_own_permission(): void
    {
        // An outlet manager holds neither PRICE_LIST_MANAGE nor
        // PRICE_LIST_PUBLISH: authoring and publishing prices are tenant-wide
        // commercial acts (FR-034, FR-035).
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $ownerHeaders = $this->bearer($token, $tenant->id);

        $list = $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        $manager = $this->asRole($tenant, PermissionRegistry::ROLE_OUTLET_MANAGER);

        // The manager may READ the price list.
        $this->withHeaders($manager)->getJson("/api/v1/price-lists/{$list}")->assertOk();

        // But may neither author nor publish.
        $this->withHeaders($manager)
            ->postJson("/api/v1/price-lists/{$list}/items", ['service_id' => (string) \Illuminate\Support\Str::uuid(), 'amount_rupiah' => 1000])
            ->assertStatus(403);

        $this->withHeaders($manager)
            ->postJson("/api/v1/price-lists/{$list}/publish")
            ->assertStatus(403);
    }

    public function test_a_cashier_may_read_prices_and_may_not_change_them(): void
    {
        // A kasir changing a price is exactly the financial control point FR-039
        // exists to guard.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();

        $list = $this->withHeaders($this->bearer($token, $tenant->id))
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        $cashier = $this->asRole($tenant, PermissionRegistry::ROLE_CASHIER);

        $this->withHeaders($cashier)->getJson('/api/v1/price-lists')->assertOk();
        $this->withHeaders($cashier)->getJson('/api/v1/services')->assertOk();

        $this->withHeaders($cashier)
            ->postJson('/api/v1/services', ['code' => 'X', 'name' => 'X', 'unit_kind' => 'satuan'])
            ->assertStatus(403);

        $this->withHeaders($cashier)
            ->postJson("/api/v1/price-lists/{$list}/publish")
            ->assertStatus(403);
    }

    public function test_superseding_leaves_the_prior_version_byte_identical(): void
    {
        // FR-035/FR-036 — the whole reason versions are insert-only.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $makeList = function (string $code, string $from) use ($headers, $brand): string {
            return $this->withHeaders($headers)
                ->postJson('/api/v1/price-lists', [
                    'laundry_brand_id' => $brand->id,
                    'code' => $code,
                    'name' => 'Daftar Harga '.$code,
                    'effective_from' => $from,
                ])
                ->json('data.price_list.id');
        };

        $v1 = $makeList('HRG-V1', '2026-08-01');

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$v1}/items", ['service_id' => $service, 'amount_rupiah' => 7000])
            ->assertStatus(201);

        $this->withHeaders($headers)->postJson("/api/v1/price-lists/{$v1}/publish")->assertOk();

        $v1ItemsBefore = DB::table('price_list_items')->where('price_list_id', $v1)->orderBy('id')->get()->toArray();

        $v2 = $makeList('HRG-V2', '2026-09-01');

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$v2}/items", ['service_id' => $service, 'amount_rupiah' => 8000])
            ->assertStatus(201);

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$v2}/publish", ['supersedes_price_list_id' => $v1])
            ->assertOk();

        $v1ItemsAfter = DB::table('price_list_items')->where('price_list_id', $v1)->orderBy('id')->get()->toArray();

        $this->assertEquals(
            $v1ItemsBefore,
            $v1ItemsAfter,
            'Publishing v2 must leave v1 byte-identical (FR-035).'
        );

        $this->assertSame(
            PriceList::STATUS_SUPERSEDED,
            DB::table('price_lists')->where('id', $v1)->value('status')
        );
    }

    // ==================================================================
    // FR-037 — money is integer Rupiah, at the surface too
    // ==================================================================

    public function test_a_fractional_amount_is_refused_at_the_api_boundary(): void
    {
        // Rule 04 hard rule 2. The column is `bigint` and the cast is `integer`;
        // this proves the SURFACE refuses a float rather than coercing it, so a
        // client cannot introduce one one layer above the schema.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $list = $this->withHeaders($headers)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        foreach ([7000.5, '7000.50', 'Rp7.000', -1] as $bad) {
            $this->withHeaders($headers)
                ->postJson("/api/v1/price-lists/{$list}/items", [
                    'service_id' => $service,
                    'amount_rupiah' => $bad,
                ])
                ->assertStatus(422);
        }
    }

    public function test_an_amount_is_emitted_as_an_integer_not_a_formatted_string(): void
    {
        // Money is never inferred from a display string (Rule 04). Formatting is
        // a client concern applied to an integer.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $list = $this->withHeaders($headers)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        $body = $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", ['service_id' => $service, 'amount_rupiah' => 17500])
            ->json('data.item');

        $this->assertIsInt($body['amount_rupiah']);
        $this->assertSame(17500, $body['amount_rupiah']);
        $this->assertSame('IDR', $body['currency']);
    }

    public function test_an_inactive_service_cannot_be_priced(): void
    {
        // INVARIANT S7. A price for something withdrawn from sale would quietly
        // become live the moment the service was reactivated.
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/services/{$service}", ['is_active' => false])
            ->assertOk();

        $list = $this->withHeaders($headers)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", ['service_id' => $service, 'amount_rupiah' => 7000])
            ->assertStatus(422)
            ->assertJsonPath('error.details.target.0', 'inactive');
    }

    public function test_a_price_row_must_reference_exactly_one_target(): void
    {
        ['tenant' => $tenant, 'brand' => $brand, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $addon = $this->withHeaders($headers)
            ->postJson('/api/v1/service-addons', ['code' => 'EXPRESS', 'name' => 'Express Uji'])
            ->json('data.addon.id');

        $list = $this->withHeaders($headers)
            ->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-1',
                'name' => 'Daftar Harga Uji',
                'effective_from' => '2026-08-01',
            ])
            ->json('data.price_list.id');

        // Two targets — no defined meaning.
        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", [
                'service_id' => $service,
                'service_addon_id' => $addon,
                'amount_rupiah' => 7000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.target.0', 'exactly_one_required');

        // No target — unusable.
        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$list}/items", ['amount_rupiah' => 7000])
            ->assertStatus(422);
    }

    // ==================================================================
    // Bounded listing and tenant scoping across every access path
    // ==================================================================

    public function test_catalogue_listing_is_tenant_scoped_on_list_filter_and_search(): void
    {
        // Rule 48 hard rule 3 — each access path is independently exploitable
        // and is therefore tested independently.
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $headersA = $this->bearer($tokenA, $tenantA->id);
        $headersB = $this->bearer($tokenB, $tenantB->id);

        $this->makeService($headersB, 'RAHASIA-B');

        // list
        $this->withHeaders($headersA)->getJson('/api/v1/services')
            ->assertOk()->assertJsonPath('data.pagination.total', 0);

        // filter
        $this->withHeaders($headersA)->getJson('/api/v1/services?unit_kind=kiloan')
            ->assertOk()->assertJsonPath('data.pagination.total', 0);

        // free-text search — the path most likely to be written scope-last
        $this->withHeaders($headersA)->getJson('/api/v1/services?q=RAHASIA')
            ->assertOk()->assertJsonPath('data.pagination.total', 0);
    }

    public function test_a_service_from_another_tenant_is_not_addressable_by_direct_id(): void
    {
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $foreign = $this->makeService($this->bearer($tokenB, $tenantB->id));

        $headersA = $this->bearer($tokenA, $tenantA->id);

        $this->withHeaders($headersA)->getJson("/api/v1/services/{$foreign}")->assertStatus(404);
        $this->withHeaders($headersA)->patchJson("/api/v1/services/{$foreign}", ['name' => 'Dibajak'])->assertStatus(404);
    }

    public function test_an_arbitrary_sort_field_is_refused_and_pagination_is_bounded(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $this->withHeaders($headers)->getJson('/api/v1/services?sort=tenant_id')->assertStatus(422);

        $this->withHeaders($headers)->getJson('/api/v1/services?per_page=99999')
            ->assertOk()->assertJsonPath('data.pagination.per_page', 100);
    }

    public function test_a_client_supplied_tenant_id_is_never_authorization_proof(): void
    {
        // AC-T2, Rule 39 hard rule 1. The header is a REQUEST for a tenant, and
        // the server re-derives scope from verified membership.
        ['tenant' => $tenantA, 'token' => $tokenA] = $this->ownerScenario();
        ['tenant' => $tenantB, 'token' => $tokenB] = $this->ownerScenario();

        $this->makeService($this->bearer($tokenB, $tenantB->id), 'RAHASIA-B');

        // Tenant A's credential asking for tenant B.
        $this->withHeaders($this->bearer($tokenA, $tenantB->id))
            ->getJson('/api/v1/services')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    // ==================================================================
    // Optimistic concurrency on the catalogue (threat T-12)
    // ==================================================================

    public function test_a_stale_catalogue_write_is_refused(): void
    {
        ['tenant' => $tenant, 'token' => $token] = $this->ownerScenario();
        $headers = $this->bearer($token, $tenant->id);

        $service = $this->makeService($headers);

        $version = $this->withHeaders($headers)
            ->getJson("/api/v1/services/{$service}")
            ->json('data.service.version');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/services/{$service}", ['name' => 'Nama Pertama'])
            ->assertOk();

        $this->withHeaders([...$headers, 'If-Unmodified-Since-Version' => $version])
            ->patchJson("/api/v1/services/{$service}", ['name' => 'Nama Kedua'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');

        $this->assertSame(
            'Nama Pertama',
            DB::table('service_catalog')->where('id', $service)->value('name')
        );
    }

    // ==================================================================
    // Absent by design
    // ==================================================================

    public function test_no_out_of_scope_business_route_exists(): void
    {
        // DEC-0035 authorised the Step 5 order/payment/receipt surface, so those
        // tokens are no longer forbidden. This assertion now guards the CURRENT
        // forward boundary: Step 6+ business features (production, tracking,
        // pickup, delivery, reminders, subscription) and features never in Step 5
        // scope (invoice, export, bulk, checkout, cart) must still have no route.
        $forbidden = [
            'invoice', 'export', 'bulk', 'checkout', 'cart',
            'produksi', 'production', 'washing', 'drying', 'tracking',
            'whatsapp', 'pickup', 'penjemputan', 'delivery', 'pengantaran',
            'reminder', 'subscription',
        ];

        foreach (app('router')->getRoutes() as $route) {
            foreach ($forbidden as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $route->uri(),
                    "Route /{$route->uri()} contains the out-of-scope token \"{$token}\"."
                );
            }
        }
    }

    public function test_there_is_no_delete_route_for_a_service_package_or_price_list(): void
    {
        $deletes = [];

        foreach (app('router')->getRoutes() as $route) {
            if (in_array('DELETE', $route->methods(), true)) {
                $deletes[] = $route->uri();
            }
        }

        // The ONLY Step 4 DELETE routes are: removing a role from a membership,
        // removing an item from a DRAFT price list (which has priced nothing),
        // and revoking one's own session. Anything a future order could
        // reference is deactivated, never destroyed (T-18).
        foreach ($deletes as $uri) {
            $this->assertTrue(
                str_contains($uri, 'roles/')
                    || str_contains($uri, 'price-lists/{priceList}/items/')
                    || str_contains($uri, 'sessions/'),
                "Unexpected DELETE route /{$uri} — master data is deactivated, not deleted."
            );
        }
    }
}
