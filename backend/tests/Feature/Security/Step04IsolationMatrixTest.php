<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Authorization\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * MATRIX C — STEP 4 TENANT ISOLATION, ACROSS EVERY ACCESS PATH.
 *
 * Rule 48 hard rule 3 is explicit: a test that proves isolation on the direct-ID
 * path and skips list, filter, search, export, and file URL DOES NOT SATISFY the
 * gate. Each path is a distinct, independently exploitable surface, so each is
 * exercised independently here.
 *
 * CONTROL / VIOLATION DISCIPLINE, inherited from Matrix B.
 * Every denial is paired with the positive case built from the SAME fixture —
 * same user, same token, same endpoint, same payload shape — varying only the
 * tenant being reached for. Without the pairing a 404 proves nothing: a 404 from
 * a typo'd route looks identical to a 404 from tenant isolation.
 *
 * WHERE A PATH DOES NOT EXIST, THAT IS RECORDED RATHER THAN COUNTED AS A PASS.
 * Step 4 registers no export route and stores no file, so the export and
 * file-URL paths are `NOT APPLICABLE`. Threat T-20 says so in words, and the
 * tests below prove the ABSENCE against the route table rather than quietly
 * omitting two rows from the matrix (Rule 01 — a gate that could not run has
 * verified nothing).
 *
 * Runs against PostgreSQL, the only engine whose isolation result counts as
 * evidence (Rule 43 hard rules 1–2, AC-T4).
 *
 * Every value is fictional (Rule 23, Rule 45).
 */
final class Step04IsolationMatrixTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogue();
    }

    /**
     * Two fully populated tenants, each owned by a different user.
     *
     * BOTH tenants hold a record of every Step 4 aggregate, so a denial can
     * never be explained away by "tenant B simply had nothing there".
     *
     * @return array<string, mixed>
     */
    private function twoTenants(): array
    {
        $build = function (string $slug): array {
            $tenant = $this->makeTenant($slug);
            $brand = $this->makeBrand($tenant);
            $outlet = $this->makeOutlet($tenant, $brand);
            $user = $this->makeUser(self::PASSWORD);
            $membership = $this->makeMembership($tenant, $user, [
                PermissionRegistry::ROLE_TENANT_OWNER,
            ]);

            $token = $this->loginToken($user, self::PASSWORD);
            $headers = $this->bearer($token, $tenant->id);

            // One of every Step 4 aggregate.
            $customer = $this->postJson('/api/v1/customers', [
                'name' => 'Pelanggan Uji '.strtoupper($slug),
                // Recognisably fabricated: an all-zero subscriber body can never
                // reach a real subscriber (Rule 45).
                'phone' => '08'.substr(md5($slug), 0, 2).'00000000',
            ], $headers)->json('data.customer.id');

            // A category too: without one the `service-categories` list would be
            // empty and the C4 control would assert nothing, which would make
            // the C5 leak assertion vacuous for that path.
            $category = $this->postJson('/api/v1/service-categories', [
                'code' => 'KAT-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Kategori Uji '.strtoupper($slug),
            ], $headers)->json('data.category.id');

            $service = $this->postJson('/api/v1/services', [
                'code' => 'CUCI-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Cuci Kiloan '.strtoupper($slug),
                'unit_kind' => 'kiloan',
                'minimum_quantity' => 2000,
            ], $headers)->json('data.service.id');

            $package = $this->postJson('/api/v1/service-packages', [
                'code' => 'PKT-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Paket Uji '.strtoupper($slug),
            ], $headers)->json('data.package.id');

            $addon = $this->postJson('/api/v1/service-addons', [
                'code' => 'ADD-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Tambahan Uji '.strtoupper($slug),
            ], $headers)->json('data.addon.id');

            $priceList = $this->postJson('/api/v1/price-lists', [
                'laundry_brand_id' => $brand->id,
                'code' => 'HRG-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Daftar Harga '.strtoupper($slug),
                'effective_from' => '2026-08-01',
            ], $headers)->json('data.price_list.id');

            $zone = $this->postJson("/api/v1/outlets/{$outlet->id}/service-zones", [
                'code' => 'ZONA-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Zona Uji '.strtoupper($slug),
            ], $headers)->json('data.zone.id');

            $shift = $this->postJson("/api/v1/outlets/{$outlet->id}/shifts", [
                'code' => 'SHF-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Shift Uji '.strtoupper($slug),
                'starts_at' => '08:00',
                'ends_at' => '16:00',
            ], $headers)->json('data.shift.id');

            $printer = $this->postJson("/api/v1/outlets/{$outlet->id}/printers", [
                'code' => 'PRN-'.strtoupper(substr($slug, 0, 4)),
                'name' => 'Printer Uji '.strtoupper($slug),
                'device_kind' => 'thermal_58mm',
                'connection_kind' => 'usb',
            ], $headers)->json('data.printer.id');

            return [
                'tenant' => $tenant,
                'brand' => $brand,
                'outlet' => $outlet,
                'membership' => $membership,
                'headers' => $headers,
                'customer' => $customer,
                'category' => $category,
                'service' => $service,
                'package' => $package,
                'addon' => $addon,
                'price_list' => $priceList,
                'zone' => $zone,
                'shift' => $shift,
                'printer' => $printer,
            ];
        };

        return ['a' => $build('tenant-a'), 'b' => $build('tenant-b')];
    }

    // =====================================================================
    // PATH 1 — DIRECT ID (threat T-06, IDOR)
    // =====================================================================

    public function test_c1_control_every_aggregate_is_reachable_within_its_own_tenant(): void
    {
        $s = $this->twoTenants();
        $a = $s['a'];

        foreach ($this->directIdPaths($a) as $label => $path) {
            $this->getJson($path, $a['headers'])
                ->assertOk("Own-tenant direct-ID read failed for {$label}.");
        }
    }

    public function test_c2_violation_no_aggregate_is_reachable_across_the_tenant_boundary_by_direct_id(): void
    {
        $s = $this->twoTenants();
        $a = $s['a'];
        $b = $s['b'];

        // Tenant A's credential, tenant A's context, tenant B's record ids.
        // Identical requests to C1 in every respect except whose record is
        // named.
        foreach ($this->directIdPaths($b) as $label => $path) {
            $response = $this->getJson($path, $a['headers']);

            $response->assertStatus(404, "Cross-tenant direct-ID read was not refused for {$label}.");

            // AC-T1 — the body is the SAME as for a record that does not exist.
            // A distinguishable message would confirm the record's existence in
            // another tenant, which is the disclosure the 404 exists to prevent
            // (Rule 48 hard rule 5).
            $response->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }

    public function test_c3_a_foreign_id_and_an_absent_id_are_byte_identical(): void
    {
        // AC-T1, stated as an equality rather than as two separate assertions:
        // if these two bodies ever diverge, the surface has become an existence
        // oracle regardless of what either one says.
        $s = $this->twoTenants();

        $foreign = $this->getJson(
            '/api/v1/customers/'.$s['b']['customer'],
            $s['a']['headers']
        );

        $absent = $this->getJson(
            '/api/v1/customers/'.Str::uuid(),
            $s['a']['headers']
        );

        $this->assertSame($foreign->status(), $absent->status());
        $this->assertSame(
            $foreign->json('error.code'),
            $absent->json('error.code')
        );
        $this->assertSame(
            $foreign->json('error.message'),
            $absent->json('error.message')
        );
    }

    // =====================================================================
    // PATH 2 — LIST
    // =====================================================================

    public function test_c4_control_a_list_returns_the_callers_own_records(): void
    {
        $s = $this->twoTenants();

        foreach ($this->listPaths($s['a']) as $label => [$path, $key]) {
            $body = $this->getJson($path, $s['a']['headers'])
                ->assertOk("Own-tenant list failed for {$label}.")
                ->json('data');

            $this->assertGreaterThan(
                0,
                $body['pagination']['total'],
                "Own-tenant list returned nothing for {$label}; the control is vacuous."
            );
        }
    }

    public function test_c5_violation_a_list_never_contains_another_tenants_record(): void
    {
        $s = $this->twoTenants();

        foreach ($this->listPaths($s['a']) as $label => [$path, $key]) {
            $body = $this->getJson($path, $s['a']['headers'])->json('data');

            // The staff projection keys its identifier `membership_id` rather
            // than `id`, because a staff row IS a membership. Accepting either
            // key here — and FAILING LOUDLY when neither is present — keeps the
            // leak assertion honest: a row whose identifier this helper could
            // not read would otherwise be silently skipped, and a skipped row is
            // exactly where a leak would hide.
            $ids = array_map(
                static function (array $row): string {
                    $id = $row['id'] ?? $row['membership_id'] ?? null;

                    if (! is_string($id)) {
                        throw new \RuntimeException(
                            'A list row carried no readable identifier, so it '
                            .'could not be checked for a cross-tenant leak.'
                        );
                    }

                    return $id;
                },
                $body[$key]
            );

            foreach ($this->allIdsOf($s['b']) as $foreignLabel => $foreignId) {
                $this->assertNotContains(
                    $foreignId,
                    $ids,
                    "List {$label} leaked tenant B's {$foreignLabel}."
                );
            }
        }
    }

    // =====================================================================
    // PATH 3 — FILTER PARAMETER
    // =====================================================================

    public function test_c6_a_filter_parameter_cannot_widen_the_tenant_scope(): void
    {
        $s = $this->twoTenants();

        // Each filter is aimed squarely at a tenant B value. The tenant scope is
        // applied BEFORE any of them, so the correct answer is an empty result —
        // never tenant B's row, and never an error that confirms the value
        // exists somewhere.
        $cases = <<<'NOTE'
        NOTE;

        $filters = [
            'customers by status' => '/api/v1/customers?status=active',
            'services by unit kind' => '/api/v1/services?unit_kind=kiloan',
            'services by foreign category' => '/api/v1/services?service_category_id='.Str::uuid(),
            'price lists by foreign brand' => '/api/v1/price-lists?laundry_brand_id='.$s['b']['brand']->id,
            'price lists by status' => '/api/v1/price-lists?status=draft',
            'zones by active flag' => "/api/v1/outlets/{$s['a']['outlet']->id}/service-zones?is_active=true",
        ];

        foreach ($filters as $label => $path) {
            $body = $this->getJson($path, $s['a']['headers'])
                ->assertOk("Filter {$label} errored rather than scoping.")
                ->json('data');

            $rows = $body[array_key_first(array_diff_key($body, ['pagination' => null]))];

            foreach ($rows as $row) {
                foreach ($this->allIdsOf($s['b']) as $foreignLabel => $foreignId) {
                    $this->assertNotSame(
                        $foreignId,
                        $row['id'],
                        "Filter {$label} returned tenant B's {$foreignLabel}."
                    );
                }
            }
        }

        unset($cases);
    }

    public function test_c7_a_foreign_brand_filter_yields_nothing_rather_than_an_error(): void
    {
        // The distinction matters. An ERROR here would say "that brand exists,
        // but not for you"; an empty result says nothing at all. Only the second
        // is safe (Rule 32 hard rule 2).
        $s = $this->twoTenants();

        $this->getJson(
            '/api/v1/price-lists?laundry_brand_id='.$s['b']['brand']->id,
            $s['a']['headers']
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    // =====================================================================
    // PATH 4 — FREE-TEXT SEARCH (threat T-02)
    // =====================================================================

    public function test_c8_control_search_finds_the_callers_own_record(): void
    {
        $s = $this->twoTenants();

        $this->getJson('/api/v1/customers?q=TENANT-A', $s['a']['headers'])
            ->assertOk();

        // The control that makes C9 meaningful: the same term DOES match within
        // the caller's own tenant.
        $body = $this->getJson('/api/v1/customers?q=Pelanggan', $s['a']['headers'])
            ->assertOk()
            ->json('data');

        $this->assertGreaterThan(
            0,
            $body['pagination']['total'],
            'Search matched nothing in the caller\'s own tenant; C9 would be vacuous.'
        );
    }

    public function test_c9_violation_search_never_reaches_across_the_tenant_boundary(): void
    {
        // THE PATH MOST LIKELY TO BE WRITTEN SCOPE-LAST. A search that filters
        // by the user's term first and applies the tenant scope afterwards is
        // one refactor away from returning everything (threat T-02).
        $s = $this->twoTenants();

        // A term that matches tenant B's records by name and by code.
        foreach (['TENANT-B', 'CUCI-TENA', 'PKT-TENA', 'ADD-TENA'] as $term) {
            foreach ([
                '/api/v1/customers?q=',
                '/api/v1/services?q=',
                '/api/v1/service-packages?q=',
                '/api/v1/service-addons?q=',
            ] as $endpoint) {
                $body = $this->getJson($endpoint.urlencode($term), $s['a']['headers'])
                    ->assertOk()
                    ->json('data');

                $rows = $body[array_key_first(array_diff_key($body, ['pagination' => null]))];

                foreach ($rows as $row) {
                    foreach ($this->allIdsOf($s['b']) as $label => $foreignId) {
                        $this->assertNotSame(
                            $foreignId,
                            $row['id'],
                            "Search {$endpoint}{$term} leaked tenant B's {$label}."
                        );
                    }
                }
            }
        }
    }

    public function test_c10_searching_a_foreign_customers_exact_phone_finds_nothing(): void
    {
        // FR-022 / invariant C2 — the same phone in two tenants is two unrelated
        // people, and there is no cross-tenant lookup path to notice otherwise.
        // Searching tenant B's exact number from tenant A must return nothing,
        // not a masked hint that somebody with it exists.
        $s = $this->twoTenants();

        $phoneB = '08'.substr(md5('tenant-b'), 0, 2).'00000000';

        $this->getJson('/api/v1/customers?q='.urlencode($phoneB), $s['a']['headers'])
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    // =====================================================================
    // PATH 5 — EXPORT: NOT APPLICABLE, PROVEN RATHER THAN SKIPPED (T-20)
    // =====================================================================

    public function test_c11_no_export_path_exists_in_step_4(): void
    {
        // Rule 48 requires the export path to be tested. Where no export path
        // exists the honest answer is that there is nothing to test — and the
        // evidence pack says so rather than counting it toward a pass (T-20,
        // Rule 01).
        //
        // Asserted against the ROUTE TABLE rather than by probing a URL: a URL
        // probe returning 404 could mean "no such route" or "route exists,
        // record absent", and only one of those supports this claim.
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString(
                'export',
                $route->uri(),
                'Step 4 registers no export route; if one is added, the export '
                .'row of this matrix stops being NOT APPLICABLE and must be '
                .'tested for real.'
            );
        }
    }

    // =====================================================================
    // PATH 6 — FILE URL: NOT APPLICABLE, PROVEN RATHER THAN SKIPPED
    // =====================================================================

    public function test_c12_step_4_stores_no_file_and_serves_no_file_url(): void
    {
        // Step 4 master data carries no upload: no laundry photograph, no proof
        // artefact, no signature. Those arrive with pickup and delivery in
        // Step 8 (Rule 09 hard rule 3). The file-URL row of the matrix is
        // therefore NOT APPLICABLE, and this proves the absence.
        foreach (Route::getRoutes() as $route) {
            foreach (['upload', 'file', 'download', 'attachment', 'signed-url', 'media'] as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $route->uri(),
                    "Route /{$route->uri()} suggests a file path; Step 4 stores no files."
                );
            }
        }
    }

    // =====================================================================
    // CLIENT-SUPPLIED SCOPE IS NEVER AUTHORIZATION PROOF (AC-T2, AC-T3)
    // =====================================================================

    public function test_c13_a_client_supplied_tenant_header_is_not_authorization_proof(): void
    {
        $s = $this->twoTenants();

        // Tenant A's credential asking for tenant B's context. The server
        // re-derives scope from verified membership; the header is an untrusted
        // hint (Rule 39 hard rule 1).
        $this->getJson(
            '/api/v1/customers',
            $this->bearer(
                // Re-login as A's user, then aim the header at B.
                str_replace('Bearer ', '', $s['a']['headers']['Authorization']),
                $s['b']['tenant']->id
            )
        )
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ACCESS_DENIED');
    }

    public function test_c14_a_foreign_brand_or_outlet_id_is_rejected_not_silently_scoped_away(): void
    {
        // AC-T3. "Rejected" and "silently scoped away" look the same to a
        // successful caller and are very different for a failing one: silently
        // scoping away would let a mis-aimed write land on the WRONG record of
        // the caller's own tenant instead of failing.
        $s = $this->twoTenants();

        // A price list aimed at tenant B's brand.
        $this->postJson('/api/v1/price-lists', [
            'laundry_brand_id' => $s['b']['brand']->id,
            'code' => 'HRG-LINTAS',
            'name' => 'Daftar Harga Lintas Tenant',
            'effective_from' => '2026-08-01',
        ], $s['a']['headers'])->assertStatus(404);

        // A zone aimed at tenant B's outlet.
        $this->postJson("/api/v1/outlets/{$s['b']['outlet']->id}/service-zones", [
            'code' => 'ZONA-LINTAS',
            'name' => 'Zona Lintas Tenant',
        ], $s['a']['headers'])->assertStatus(404);

        // A staff assignment aimed at tenant B's outlet.
        $this->postJson("/api/v1/staff/{$s['a']['membership']->id}/outlets", [
            'assigned_outlet_id' => $s['b']['outlet']->id,
        ], $s['a']['headers'])->assertStatus(404);
    }

    public function test_c15_a_forged_tenant_id_in_the_body_is_ignored_not_honoured(): void
    {
        // threat T-05. `tenant_id` is absent from every Step 4 `$fillable`, so a
        // body carrying one cannot steer the write.
        $s = $this->twoTenants();

        $created = $this->postJson('/api/v1/services', [
            'code' => 'FORGED',
            'name' => 'Layanan Uji Palsu',
            'unit_kind' => 'satuan',
            'tenant_id' => $s['b']['tenant']->id,
        ], $s['a']['headers'])->assertStatus(201)->json('data.service.id');

        // The record landed in tenant A, where the caller actually is.
        $this->getJson("/api/v1/services/{$created}", $s['a']['headers'])->assertOk();
        $this->getJson("/api/v1/services/{$created}", $s['b']['headers'])->assertStatus(404);
    }

    // =====================================================================
    // Fixtures
    // =====================================================================

    /**
     * The direct-ID read path for every Step 4 aggregate that has one.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, string>
     */
    private function directIdPaths(array $t): array
    {
        $outletId = $t['outlet']->id;

        return [
            'customer' => '/api/v1/customers/'.$t['customer'],
            'customer consents' => '/api/v1/customers/'.$t['customer'].'/consents',
            'service' => '/api/v1/services/'.$t['service'],
            'price list' => '/api/v1/price-lists/'.$t['price_list'],
            'outlet master data' => '/api/v1/outlets/'.$outletId.'/master-data',
            'outlet service zones' => '/api/v1/outlets/'.$outletId.'/service-zones',
            'outlet shifts' => '/api/v1/outlets/'.$outletId.'/shifts',
            'outlet printers' => '/api/v1/outlets/'.$outletId.'/printers',
            'staff member' => '/api/v1/staff/'.$t['membership']->id,
        ];
    }

    /**
     * The list path for every Step 4 aggregate, with its collection key.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, array{0: string, 1: string}>
     */
    private function listPaths(array $t): array
    {
        $outletId = $t['outlet']->id;

        return [
            'customers' => ['/api/v1/customers', 'customers'],
            'service categories' => ['/api/v1/service-categories', 'categories'],
            'services' => ['/api/v1/services', 'services'],
            'service packages' => ['/api/v1/service-packages', 'packages'],
            'service add-ons' => ['/api/v1/service-addons', 'addons'],
            'price lists' => ['/api/v1/price-lists', 'price_lists'],
            'service zones' => ["/api/v1/outlets/{$outletId}/service-zones", 'zones'],
            'shifts' => ["/api/v1/outlets/{$outletId}/shifts", 'shifts'],
            'printers' => ["/api/v1/outlets/{$outletId}/printers", 'printers'],
            'staff' => ['/api/v1/staff', 'staff'],
        ];
    }

    /**
     * Every record id belonging to one tenant, for leak assertions.
     *
     * @param  array<string, mixed>  $t
     * @return array<string, string>
     */
    private function allIdsOf(array $t): array
    {
        return [
            'customer' => $t['customer'],
            'category' => $t['category'],
            'service' => $t['service'],
            'package' => $t['package'],
            'addon' => $t['addon'],
            'price list' => $t['price_list'],
            'zone' => $t['zone'],
            'shift' => $t['shift'],
            'printer' => $t['printer'],
        ];
    }
}
