<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\CustomerManagement\Http\AddressProjection;
use App\Modules\CustomerManagement\Http\CustomerProjection;
use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\CustomerManagement\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * N6 — `internal_notes` is context-gated server-side.
 *
 * It used to be emitted at EVERY masking context, including `NONE`. That made
 * `NONE` mean "no address" rather than "no detail" — not what the name says,
 * and not what a caller reading the name would assume.
 *
 * `internal_notes` is the same class of datum as an address `notes` field,
 * which is withheld below `FULL` precisely because operator free text carries
 * location. Internal notes carry that and more: service history, complaints,
 * and whatever a staff member thought worth recording about a person.
 *
 * The finding was LATENT, not live: no shipped role reaches `AREA`. That is a
 * fact about today's permission topology, and a topology is not a control
 * (Rule 03 — hiding is never the access control). Test 14 fails the moment the
 * topology changes.
 *
 * Every note used here is recognisably fictional and deliberately bland — an
 * evidence pack carrying a realistic internal note about a customer would be
 * the disclosure this test exists to prevent (Rule 23, Rule 45).
 */
final class InternalNotesProjectionTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /** Deliberately bland, and distinctive enough to grep for. */
    private const NOTE = 'CATATAN-INTERNAL-FIKTIF-UJI-0001';

    private function customerWithNote(string $tenantId, string $phone): Customer
    {
        $customer = new Customer([
            'name' => 'Pelanggan Uji Fiktif',
            'phone' => $phone,
            'internal_notes' => self::NOTE,
        ]);
        $customer->tenant_id = $tenantId;
        $customer->phone_normalized = PhoneNumber::normalize($phone);
        $customer->code = 'PLG-'.substr($phone, -6);
        $customer->status = Customer::STATUS_ACTIVE;
        $customer->save();

        return $customer;
    }

    // 1
    public function test_a_full_context_receives_internal_notes(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000801');

        $projected = CustomerProjection::detail($customer, AddressProjection::CONTEXT_FULL);

        $this->assertSame(self::NOTE, $projected['internal_notes']);
    }

    // 2
    public function test_an_area_context_omits_internal_notes(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000802');

        $projected = CustomerProjection::detail($customer, AddressProjection::CONTEXT_AREA);

        // ABSENT, not null. The key is never assembled, so there is no hidden
        // value in the payload for a client to recover.
        $this->assertArrayNotHasKey('internal_notes', $projected);
    }

    // 3
    public function test_a_none_context_omits_internal_notes(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000803');

        $projected = CustomerProjection::detail($customer, AddressProjection::CONTEXT_NONE);

        $this->assertArrayNotHasKey('internal_notes', $projected);
    }

    // 4 — the fail-closed default.
    public function test_omitting_the_context_entirely_yields_no_internal_notes(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000804');

        $projected = CustomerProjection::detail($customer);

        $this->assertArrayNotHasKey('internal_notes', $projected);
    }

    // 5, 6, 7 — list, detail and search over HTTP.
    public function test_no_http_projection_leaks_the_note_to_a_list_or_search(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $customer = $this->customerWithNote($tenant->id, '081200000805');

        // LIST — never carries it at any permission level.
        $list = $this->withHeaders($headers)->getJson('/api/v1/customers')->assertOk();
        $this->assertStringNotContainsString(self::NOTE, $list->getContent());

        // SEARCH — including a search that matches this very customer.
        $search = $this->withHeaders($headers)
            ->getJson('/api/v1/customers?q=081200000805')
            ->assertOk();
        $this->assertStringNotContainsString(self::NOTE, $search->getContent());

        // DETAIL — a tenant owner holds customer.manage, so FULL applies and
        // the note IS returned. Without this the assertions above would also
        // pass if the field had simply been deleted from the product.
        $detail = $this->withHeaders($headers)
            ->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk();
        $this->assertStringContainsString(self::NOTE, $detail->getContent());
    }

    // 8 — the relationship path.
    public function test_the_address_relationship_path_does_not_carry_the_note(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000806');

        foreach ([AddressProjection::CONTEXT_AREA, AddressProjection::CONTEXT_NONE] as $context) {
            $serialised = json_encode(
                CustomerProjection::detail($customer->fresh(['addresses']), $context),
                JSON_THROW_ON_ERROR
            );

            $this->assertStringNotContainsString(self::NOTE, $serialised);
        }
    }

    // 9 — nothing hidden in the serialised form.
    public function test_the_serialised_payload_contains_no_hidden_raw_value(): void
    {
        $customer = $this->customerWithNote($this->makeTenant()->id, '081200000807');

        $serialised = json_encode(
            CustomerProjection::detail($customer, AddressProjection::CONTEXT_NONE),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(self::NOTE, $serialised);
        $this->assertStringNotContainsString('internal_notes', $serialised);
    }

    // 10, 11 — errors and logs.
    public function test_a_validation_error_and_the_log_carry_no_note(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $logFile = storage_path('logs/laravel.log');
        $before = is_file($logFile) ? filesize($logFile) : 0;

        // A note supplied alongside an invalid phone. The error must not echo it.
        $response = $this->withHeaders($headers)->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Uji Fiktif',
            'phone' => '',
            'internal_notes' => self::NOTE,
        ])->assertStatus(422);

        $this->assertStringNotContainsString(self::NOTE, $response->getContent());

        $written = is_file($logFile)
            ? (string) file_get_contents($logFile, false, null, $before)
            : '';
        $this->assertStringNotContainsString(self::NOTE, $written);
    }

    // 12 — audit metadata.
    public function test_no_audit_row_carries_the_note(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $created = $this->withHeaders($headers)->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Uji Fiktif',
            'phone' => '081200000808',
            'internal_notes' => self::NOTE,
        ])->assertCreated()->json('data.customer.id');

        $this->withHeaders($headers)->patchJson("/api/v1/customers/{$created}", [
            'internal_notes' => self::NOTE.'-DIUBAH',
        ])->assertOk();

        $rows = AuditEntry::query()->get()
            ->map(static fn (AuditEntry $e): string => json_encode($e->toArray(), JSON_THROW_ON_ERROR))
            ->implode("\n");

        // The audit records WHICH FIELD changed, never the note itself.
        $this->assertStringNotContainsString(self::NOTE, $rows);
        $this->assertStringContainsString('internal_notes', $rows, 'the changed field name is still recorded');
    }

    // 13 — no context-insensitive cache path exists.
    public function test_no_customer_projection_is_cached_under_a_context_insensitive_key(): void
    {
        // A cached FULL projection served to an AREA caller would defeat the
        // gate entirely, and a cache key without the context dimension is how
        // that happens. Step 4 caches no projection at all; this asserts the
        // absence rather than assuming it (Rule 44 hard rule 1).
        $sources = '';
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Modules/CustomerManagement'))
        );

        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources .= file_get_contents($file->getPathname());
            }
        }

        foreach (['Cache::', 'cache()->', 'remember('] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $sources,
                "customer management caches something ({$needle}); a projection cached "
                .'without a context dimension would serve a FULL payload to an AREA caller'
            );
        }
    }

    // 14 — topology drift detector, naming internal_notes specifically.
    public function test_no_shipped_role_reaches_a_context_that_would_change_note_visibility(): void
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
            .'context is reachable over HTTP for the first time. Add HTTP-level '
            .'coverage asserting internal_notes is omitted for it before relaxing '
            .'this: '.implode(', ', $viewOnly)
        );
    }

    // 15 — the internal_notes column is not reachable by any other route shape.
    public function test_no_route_exposes_internal_notes_outside_the_gated_projection(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertStringNotContainsString(
                'internal_notes',
                $route->uri(),
                'a route names internal_notes in its URI'
            );
        }

        // And it is not sortable or filterable, which would let a caller infer
        // its contents without ever being shown them.
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/customers?sort=internal_notes')
            ->assertStatus(422);
    }
}
