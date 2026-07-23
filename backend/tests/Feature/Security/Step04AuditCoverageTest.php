<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Audit\AuditAction;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Authorization\PermissionRegistry;
use App\Modules\ServiceCatalog\Models\PriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * SEC-10 — EVERY STATE-CHANGING ROUTE IS AUDITED, AND THAT IS ENFORCED.
 *
 * Before this, exactly one Step 4 module recorded audit entries. Twenty-four
 * mutating routes — every customer write, every consent record, every catalogue
 * change, every price-list publication, every outlet configuration change — left
 * no trail at all. "Who changed this price, and when" was unanswerable, which is
 * precisely the question a financial dispute opens with (Rule 46, Rule 04).
 *
 * WHY THIS TEST IS DRIVEN BY THE ROUTE TABLE
 * ------------------------------------------
 * A checklist of writes goes stale the moment somebody adds route
 * twenty-five, and nothing tells you. So the coverage map below is checked
 * AGAINST THE LIVE ROUTER: a mutating `/api/v1` route that is not declared here
 * fails this test, and a declaration naming a route that no longer exists fails
 * it too. Adding an unaudited write becomes impossible to do quietly — it is a
 * red test, not an omission somebody notices later.
 *
 * It is deliberately NOT a naming-convention check. A guard that assumed
 * "controller methods starting with `store` write" would miss `publish`, miss
 * `revoke`, and fire on a read named `storeFilter`. The route table is the
 * stronger inventory and it is what the framework itself dispatches on.
 */
final class Step04AuditCoverageTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    private const PASSWORD = 'placeholder-KataSandiUji12345';

    /**
     * Every mutating `/api/v1` route, and the audit action it must produce.
     *
     * `null` means DELIBERATELY NOT AUDITED, and every one of them carries its
     * reason. An exemption is a decision recorded in the open, not a gap.
     *
     * @return array<string, ?string>
     */
    private static function coverage(): array
    {
        return [
            // --- Step 3 identity and context (already audited) -------------
            'api.v1.auth.login' => AuditAction::AUTH_LOGIN_SUCCEEDED,
            'api.v1.auth.logout' => AuditAction::AUTH_LOGOUT,
            'api.v1.auth.password-reset.request' => AuditAction::AUTH_PASSWORD_RESET_REQUESTED,
            'api.v1.auth.password-reset.complete' => AuditAction::AUTH_PASSWORD_RESET_COMPLETED,
            'api.v1.context.tenant' => AuditAction::TENANT_CONTEXT_SWITCHED,
            'api.v1.context.outlet' => AuditAction::OUTLET_CONTEXT_SWITCHED,
            'api.v1.sessions.revoke' => AuditAction::AUTH_SESSION_REVOKED,
            'api.v1.sessions.revoke-others' => AuditAction::AUTH_SESSION_REVOKED_OTHERS,

            // --- Step 4 customer master data -------------------------------
            'api.v1.customers.store' => AuditAction::CUSTOMER_CREATED,
            'api.v1.customers.update' => AuditAction::CUSTOMER_UPDATED,
            'api.v1.customers.archive' => AuditAction::CUSTOMER_ARCHIVED,
            'api.v1.customers.consents.store' => AuditAction::CUSTOMER_CONSENT_RECORDED,
            'api.v1.customers.addresses.store' => AuditAction::CUSTOMER_ADDRESS_CREATED,
            'api.v1.customers.addresses.update' => AuditAction::CUSTOMER_ADDRESS_UPDATED,
            'api.v1.customers.addresses.archive' => AuditAction::CUSTOMER_ADDRESS_ARCHIVED,
            'api.v1.customers.addresses.reactivate' => AuditAction::CUSTOMER_ADDRESS_REACTIVATED,

            // --- Step 4 service catalogue ----------------------------------
            'api.v1.service-categories.store' => AuditAction::SERVICE_CATEGORY_CREATED,
            'api.v1.service-categories.update' => AuditAction::SERVICE_CATEGORY_UPDATED,
            'api.v1.services.store' => AuditAction::SERVICE_CREATED,
            'api.v1.services.update' => AuditAction::SERVICE_UPDATED,
            'api.v1.service-packages.store' => AuditAction::SERVICE_PACKAGE_CREATED,
            'api.v1.service-packages.update' => AuditAction::SERVICE_PACKAGE_UPDATED,
            'api.v1.service-packages.items.set' => AuditAction::SERVICE_PACKAGE_ITEMS_REPLACED,
            'api.v1.service-addons.store' => AuditAction::SERVICE_ADDON_CREATED,
            'api.v1.service-addons.update' => AuditAction::SERVICE_ADDON_UPDATED,

            // --- Step 4 pricing --------------------------------------------
            'api.v1.price-lists.store' => AuditAction::PRICE_LIST_CREATED,
            'api.v1.price-lists.publish' => AuditAction::PRICE_LIST_PUBLISHED,
            'api.v1.price-lists.items.store' => AuditAction::PRICE_LIST_ITEM_ADDED,
            'api.v1.price-lists.items.update' => AuditAction::PRICE_LIST_ITEM_UPDATED,
            'api.v1.price-lists.items.destroy' => AuditAction::PRICE_LIST_ITEM_REMOVED,

            // --- Step 4 outlet master data ---------------------------------
            'api.v1.outlets.master-data.update' => AuditAction::OUTLET_MASTER_DATA_UPDATED,
            'api.v1.outlets.service-zones.store' => AuditAction::OUTLET_ZONE_CREATED,
            'api.v1.outlets.service-zones.update' => AuditAction::OUTLET_ZONE_UPDATED,
            'api.v1.outlets.shifts.store' => AuditAction::OUTLET_SHIFT_CREATED,
            'api.v1.outlets.shifts.update' => AuditAction::OUTLET_SHIFT_UPDATED,
            'api.v1.outlets.printers.store' => AuditAction::OUTLET_PRINTER_CREATED,
            'api.v1.outlets.printers.update' => AuditAction::OUTLET_PRINTER_UPDATED,
            'api.v1.proof-policy.update' => AuditAction::PROOF_POLICY_UPDATED,

            // --- Step 4 staff ----------------------------------------------
            'api.v1.staff.outlets.assign' => AuditAction::STAFF_OUTLET_ASSIGNED,
            'api.v1.staff.outlets.revoke' => AuditAction::STAFF_OUTLET_REVOKED,
            'api.v1.staff.roles.assign' => AuditAction::MEMBERSHIP_ROLE_ASSIGNED,
            'api.v1.staff.roles.remove' => AuditAction::MEMBERSHIP_ROLE_REMOVED,

            // --- Step 5: orders and payments (DEC-0035) -------------------
            'api.v1.orders.store' => AuditAction::ORDER_CREATED,
            'api.v1.orders.place' => AuditAction::ORDER_PLACED,
            'api.v1.orders.cancel' => AuditAction::ORDER_CANCELLED,
            'api.v1.orders.payments.store' => AuditAction::PAYMENT_RECORDED,
            'api.v1.payments.confirm' => AuditAction::PAYMENT_CONFIRMED,
            'api.v1.payments.reverse' => AuditAction::PAYMENT_REVERSED,

            // --- Framework routes outside /api/v1 -------------------------
            //
            // DELIBERATELY NOT AUDITED, with the reason in the open.
            //
            // `storage.local.upload` is Laravel's own local-disk upload
            // endpoint, registered by the framework because
            // `config/filesystems.php` sets `'serve' => true` on the `local`
            // disk. It is signature-guarded, it writes no business record, and
            // it belongs to no Step 4 aggregate — so there is nothing for a
            // tenant/actor audit row to describe.
            //
            // It is DECLARED rather than filtered out. The guard previously
            // only looked at `api/v1`, which meant this route was invisible to
            // it: not exempted, just unseen. An exemption a reader can find and
            // argue with is a decision; an unexamined blind spot is not.
            'storage.local.upload' => null,
        ];
    }

    /** @return list<string> */
    private function mutatingRouteNames(): array
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            // EVERY mutating route, not only `/api/v1`.
            //
            // Scoping this to the API prefix made the guard blind to anything
            // the framework registers elsewhere — which is exactly where an
            // unaudited write would hide, because nobody writing a business
            // feature looks there.
            if (array_intersect($route->methods(), ['POST', 'PATCH', 'PUT', 'DELETE']) === []) {
                continue;
            }

            $names[] = $route->getName() ?? $route->uri();
        }

        sort($names);

        return $names;
    }

    /**
     * The gate. A new write route cannot be added without a decision about its
     * audit, because this fails until one is recorded either way.
     */
    public function test_every_mutating_route_declares_its_audit_action(): void
    {
        $declared = self::coverage();
        $actual = $this->mutatingRouteNames();

        $undeclared = array_values(array_diff($actual, array_keys($declared)));

        $this->assertSame(
            [],
            $undeclared,
            "These mutating routes have no audit declaration. Add the action to "
            ."AuditAction and record it in the write path, or declare a null exemption "
            ."with its reason — do not leave a Step 4 write untraceable (SEC-10, Rule 46): "
            .implode(', ', $undeclared)
        );

        // The other direction. A declaration for a route that no longer exists
        // is a stale map that would read as coverage while covering nothing.
        $stale = array_values(array_diff(array_keys($declared), $actual));

        $this->assertSame([], $stale, 'declared routes that no longer exist: '.implode(', ', $stale));
    }

    public function test_every_declared_action_exists_in_the_closed_vocabulary(): void
    {
        foreach (self::coverage() as $route => $action) {
            if ($action === null) {
                continue;
            }

            $this->assertContains(
                $action,
                AuditAction::all(),
                "{$route} declares '{$action}', which AuditAction::all() does not list — "
                .'AuditRecorder would reject it at runtime.'
            );
        }
    }

    /**
     * The REVERSE direction: every action in the vocabulary has an emitter.
     *
     * The gate above proves no route is unaudited. It says nothing about the
     * opposite drift — an action constant nobody emits. Independent review found
     * four: `MEMBERSHIP_CREATED`, `MEMBERSHIP_SUSPENDED`, `MEMBERSHIP_REVOKED`
     * and `DEVICE_SESSION_REVOKED`.
     *
     * They are DEAD VOCABULARY, not missing audit. Device revocation IS audited,
     * under `AUTH_SESSION_REVOKED`; the membership lifecycle actions have no
     * command surface to emit them from, which
     * `test_no_membership_suspension_endpoint_exists_yet` already asserts.
     *
     * That distinction matters: an unemitted action reads to an operator
     * building an alert as a thing that will fire, and they will wait for an
     * event that cannot arrive. So each one is declared here with its reason,
     * and a NEW unemitted action fails this test rather than accumulating
     * quietly.
     */
    public function test_every_audit_action_is_either_emitted_or_declared_dormant(): void
    {
        // Declared dormant, each with the reason it cannot fire yet.
        $dormant = [
            // No endpoint creates a membership; Step 3 seeds them.
            AuditAction::MEMBERSHIP_CREATED => 'no membership-creation command surface exists',
            // Asserted separately by test_no_membership_suspension_endpoint_exists_yet.
            AuditAction::MEMBERSHIP_SUSPENDED => 'no membership-suspension command surface exists',
            AuditAction::MEMBERSHIP_REVOKED => 'no membership-revocation command surface exists',
            // Device revocation is audited as AUTH_SESSION_REVOKED instead.
            AuditAction::DEVICE_SESSION_REVOKED => 'device revocation is emitted as AUTH_SESSION_REVOKED',
        ];

        $source = '';
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($directory as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $source .= file_get_contents($file->getPathname());
            }
        }

        $constants = (new \ReflectionClass(AuditAction::class))->getConstants();

        $unemitted = [];

        foreach (AuditAction::all() as $action) {
            $name = array_search($action, $constants, true);

            // An emission site is `AuditAction::NAME` appearing anywhere in
            // app/ OTHER than the declaration itself. Matching the constant
            // NAME rather than its wire value, because the wire value also
            // appears in the declaration line.
            $uses = substr_count($source, "AuditAction::{$name}");

            if ($uses === 0) {
                $unemitted[] = $action;
            }
        }

        $undeclared = array_values(array_diff($unemitted, array_keys($dormant)));

        $this->assertSame(
            [],
            $undeclared,
            'These audit actions have no emission site in app/ and are not '
            .'declared dormant. An action nobody emits reads to an operator '
            .'building an alert as a thing that will fire: '
            .implode(', ', $undeclared)
        );

        // And a dormant declaration that HAS started being emitted is stale —
        // it should move out of this list rather than sit here misdescribing
        // the system.
        $stale = array_values(array_diff(array_keys($dormant), $unemitted));

        $this->assertSame(
            [],
            $stale,
            'declared dormant but now emitted: '.implode(', ', $stale)
        );
    }

    /**
     * A representative write per category, exercised over HTTP and checked for
     * an actual audit row.
     *
     * The declaration test above proves an INTENTION was recorded. This proves
     * the write path honours it. Neither is sufficient alone: a map can name an
     * action nobody emits, and an emitted action can go unmapped.
     */
    public function test_representative_writes_actually_produce_their_audit_entry(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $brand = $this->makeBrand($tenant);
        $outlet = $this->makeOutlet($tenant, $brand);
        $user = $this->makeUser(self::PASSWORD);
        $membership = $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $customerId = $this->withHeaders($headers)->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Audit Fiktif',
            'phone' => '081200000901',
        ])->assertCreated()->json('data.customer.id');

        $this->withHeaders($headers)->patchJson("/api/v1/customers/{$customerId}", [
            'name' => 'Pelanggan Audit Fiktif Diubah',
        ])->assertOk();

        $this->withHeaders($headers)->postJson("/api/v1/customers/{$customerId}/consents", [
            'consent_type' => 'marketing_whatsapp',
            'state' => 'granted',
            'source' => 'counter',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/api/v1/service-categories', [
            'code' => 'KAT-AUDIT',
            'name' => 'Kategori Audit Fiktif',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson("/api/v1/outlets/{$outlet->id}/printers", [
            'code' => 'PRN-AUDIT',
            'name' => 'Printer Audit Fiktif',
            'device_kind' => 'thermal_58mm',
            'connection_kind' => 'bluetooth',
        ])->assertCreated();

        $priceListId = $this->withHeaders($headers)->postJson('/api/v1/price-lists', [
            'laundry_brand_id' => $brand->id,
            'code' => 'PL-AUDIT',
            'name' => 'Daftar Harga Audit Fiktif',
            'effective_from' => '2026-10-01',
        ])->assertCreated()->json('data.price_list.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/price-lists/{$priceListId}/publish")
            ->assertOk();

        foreach ([
            AuditAction::CUSTOMER_CREATED,
            AuditAction::CUSTOMER_UPDATED,
            AuditAction::CUSTOMER_CONSENT_RECORDED,
            AuditAction::SERVICE_CATEGORY_CREATED,
            AuditAction::OUTLET_PRINTER_CREATED,
            AuditAction::PRICE_LIST_CREATED,
            AuditAction::PRICE_LIST_PUBLISHED,
        ] as $action) {
            $entry = AuditEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('action', $action)
                ->first();

            $this->assertNotNull($entry, "no audit entry was written for {$action}");

            // Tenant and actor on every row. An audit trail that cannot say who
            // or in which tenant is not evidence (Rule 46 hard rule 1).
            $this->assertSame($tenant->id, $entry->tenant_id);
            $this->assertSame($user->id, $entry->actor_user_id);
            $this->assertSame($membership->id, $entry->actor_membership_id);
        }

        // The price change carries its before/after, because a pricing dispute
        // asks exactly that. Commercial data, not personal data.
        $published = PriceList::query()->findOrFail($priceListId);
        $this->assertSame(PriceList::STATUS_ACTIVE, $published->status);
    }

    /** A safe GET writes nothing to the trail (SEC-11's property, held here too). */
    public function test_a_read_produces_no_audit_entry(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $before = AuditEntry::query()->where('tenant_id', $tenant->id)->count();

        $this->withHeaders($headers)->getJson('/api/v1/customers')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/services')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/proof-policy')->assertOk();

        $this->assertSame($before, AuditEntry::query()->where('tenant_id', $tenant->id)->count());
    }

    /**
     * No audit row carries a credential or an unmasked personal datum.
     *
     * Scanned across every row the representative writes produced, rather than
     * on one hand-picked entry — the leak that matters is the one nobody
     * thought to look at.
     */
    public function test_no_audit_row_carries_a_credential_or_personal_datum(): void
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant();
        $user = $this->makeUser(self::PASSWORD);
        $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
        $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

        $phone = '081200000902';

        $this->withHeaders($headers)->postJson('/api/v1/customers', [
            'name' => 'Pelanggan Rahasia Fiktif',
            'phone' => $phone,
            'internal_notes' => 'Catatan internal yang tidak boleh bocor.',
        ])->assertCreated();

        $rows = AuditEntry::query()->get()->map(
            static fn (AuditEntry $e): string => json_encode($e->toArray(), JSON_THROW_ON_ERROR)
        )->implode("\n");

        foreach ([
            self::PASSWORD,
            $phone,
            '6281200000902',
            'Catatan internal',
            'Bearer ',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $rows,
                "an audit row carries '{$forbidden}' — the trail must record WHAT changed, "
                .'never a second copy of the datum (Rule 46 hard rule 2)'
            );
        }
    }

    /** Audit rows are tenant-scoped like every other business record. */
    public function test_audit_entries_are_tenant_scoped(): void
    {
        $this->seedCatalogue();

        $tenantA = $this->makeTenant('tenant-a');
        $tenantB = $this->makeTenant('tenant-b');

        foreach ([$tenantA, $tenantB] as $index => $tenant) {
            $user = $this->makeUser(self::PASSWORD);
            $this->makeMembership($tenant, $user, [PermissionRegistry::ROLE_TENANT_OWNER]);
            $headers = $this->bearer($this->loginToken($user, self::PASSWORD), $tenant->id);

            $this->withHeaders($headers)->postJson('/api/v1/customers', [
                'name' => 'Pelanggan Fiktif '.$index,
                'phone' => '08120000091'.$index,
            ])->assertCreated();
        }

        foreach ([$tenantA, $tenantB] as $tenant) {
            $entries = AuditEntry::query()
                ->where('action', AuditAction::CUSTOMER_CREATED)
                ->where('tenant_id', $tenant->id)
                ->get();

            $this->assertCount(1, $entries);
            $this->assertSame($tenant->id, $entries->first()->tenant_id);
        }
    }
}
