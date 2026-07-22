<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\ServiceCatalog\Models\PriceList;
use App\Modules\ServiceCatalog\Models\PriceListItem;
use App\Modules\ServiceCatalog\Models\Service;
use App\Modules\ServiceCatalog\Services\PriceListPublisher;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\BuildsTenantScenario;
use Tests\TestCase;

/**
 * FINANCIAL INTEGRITY for price lists — FR-034 … FR-038, Rule 04.
 *
 * These are hard-gate tests. A failure here is an automatic NO-GO, not a defect
 * to schedule (Rule 04, hard rule 12).
 *
 * The money-type assertion queries the LIVE PostgreSQL schema rather than
 * reading the migration source. A migration can say `bigInteger` and the column
 * still end up something else — through a later ALTER, a rollback that half
 * applied, or an engine that silently substitutes a type. Reading the source
 * would prove only that the source says the right thing (AC-F1).
 */
final class PriceListIntegrityTest extends TestCase
{
    use BuildsTenantScenario;
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // FR-037 / AC-F1 — integer Rupiah, verified against the live schema
    // -----------------------------------------------------------------------

    public function test_no_step_4_money_column_is_a_floating_point_type(): void
    {
        $floating = DB::select(<<<'SQL'
            SELECT table_name, column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND (column_name LIKE '%amount%' OR column_name LIKE '%price%'
                   OR column_name LIKE '%rupiah%' OR column_name LIKE '%total%')
              AND data_type IN ('real', 'double precision', 'numeric', 'money')
        SQL);

        $this->assertSame(
            [],
            $floating,
            'Rule 04 hard rule 2: floating point is forbidden in every money path. '
            .'Offending columns: '.json_encode($floating, JSON_THROW_ON_ERROR)
        );
    }

    public function test_the_price_column_is_specifically_a_64_bit_integer(): void
    {
        $type = DB::scalar(<<<'SQL'
            SELECT data_type FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'price_list_items'
              AND column_name = 'amount_rupiah'
        SQL);

        // bigint, not integer: Rp2.1 billion is an ordinary monthly figure for a
        // laundry chain and would overflow a 32-bit column.
        $this->assertSame('bigint', $type);
    }

    public function test_a_negative_price_is_rejected_by_the_database(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->draft($context, $brand);
        $service = $this->makeService($context->tenantId(), 'SVC-NEG');

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->addItem($context, $list, $service, -1);
    }

    public function test_a_price_of_zero_is_allowed(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->draft($context, $brand);
        $service = $this->makeService($context->tenantId(), 'SVC-FREE');

        // Zero is a legitimate price — a free service in a package, a waived
        // charge. Only NEGATIVE is nonsense (a discount is a Step 5 concept).
        $item = $this->addItem($context, $list, $service, 0);

        $this->assertSame(0, $item->amount_rupiah);
        $this->assertIsInt($item->fresh()->amount_rupiah, 'the cast must stay integer');
    }

    public function test_a_large_amount_survives_a_round_trip_without_precision_loss(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->draft($context, $brand);
        $service = $this->makeService($context->tenantId(), 'SVC-BIG');

        // Beyond 2^32, and beyond the range where a float64 can represent every
        // integer exactly once combined with arithmetic.
        $amount = 9_007_199_254_740_991;

        $this->addItem($context, $list, $service, $amount);

        $this->assertSame(
            $amount,
            PriceListItem::query()->where('price_list_id', $list->id)->first()->amount_rupiah
        );
    }

    // -----------------------------------------------------------------------
    // FR-035 / AC-F3 — immutability after publication
    // -----------------------------------------------------------------------

    public function test_a_published_price_list_cannot_be_edited(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->publishedList($context, $brand, '2026-08-01');

        $this->expectException(RuntimeException::class);

        $list->name = 'Diubah setelah terbit';
        $list->save();
    }

    /**
     * SEC-04 — the POSITIVE control the immutability guard never had.
     *
     * Every existing test above asserts that a forbidden change is refused, and
     * they all passed — while the guard was in fact refusing EVERYTHING.
     * `HasOptimisticVersion` registers its `updating` hook during
     * `bootTraits()`, which runs before `booted()`, so the concurrency counter
     * was already dirty when the immutability check ran and appeared as a
     * forbidden business-field change on every save. The allow-list was empty in
     * practice.
     *
     * A guard that refuses everything satisfies every negative test in the file.
     * That is why a negative-only suite cannot establish this contract, and why
     * this test exists for each permitted field individually rather than as one
     * combined save.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('permittedPostPublishFields')]
    public function test_a_permitted_lifecycle_field_may_change_after_publication(
        string $field,
        mixed $value,
    ): void {
        [$context, $brand] = $this->scenario();
        $list = $this->publishedList($context, $brand, '2026-08-01');
        $before = (int) $list->version;

        // For the boolean, write the OPPOSITE of what is stored. Writing the
        // value it already holds leaves the model clean, Eloquent issues no
        // statement at all, and the test would pass without the guard ever being
        // consulted — the same "green for an unrelated reason" shape this
        // finding is about.
        if ($field === 'is_default') {
            $value = ! (bool) $list->is_default;
        }

        // Resolved here rather than in the provider, because it must reference
        // a real list in THIS tenant to satisfy the composite foreign key.
        if ($field === 'supersedes_price_list_id') {
            $value = $this->draft($context, $brand, 'PL-SEBELUM', '2026-06-01')->id;
        }

        $list->{$field} = $value;
        $list->save();

        $reloaded = PriceList::query()->findOrFail($list->id);

        $this->assertSame(
            $value,
            $field === 'is_default' ? (bool) $reloaded->{$field} : $reloaded->{$field},
            "{$field} is on the permitted list but did not persist"
        );

        // The counter advances as a CONSEQUENCE of the permitted write. If it
        // did not, a stale writer could reuse a token this write consumed.
        $this->assertSame($before + 1, (int) $reloaded->version);
    }

    /** @return array<string, array{string, mixed}> */
    public static function permittedPostPublishFields(): array
    {
        return [
            'status' => ['status', PriceList::STATUS_ARCHIVED],
            'is_default' => ['is_default', false],
            // On the allow-list and previously untested. An allow-list entry
            // nothing exercises is indistinguishable from one that does not
            // work — which is the whole shape of this finding.
            //
            // The value is resolved at run time from a REAL list in the same
            // tenant. It used to be a hand-written UUID referencing nothing,
            // which passed only because the column had no foreign key — the
            // test was enshrining the very gap review N3 found.
            'supersedes_price_list_id' => ['supersedes_price_list_id', null],
        ];
    }

    /**
     * The forbidden set, each named separately.
     *
     * Grouping them would let one surviving refusal carry the whole assertion,
     * which is the failure mode this finding is about.
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenPostPublishFields')]
    public function test_a_commercial_field_still_cannot_change_after_publication(
        string $field,
        mixed $value,
    ): void {
        [$context, $brand] = $this->scenario();
        $list = $this->publishedList($context, $brand, '2026-08-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/{$field}/");

        $list->{$field} = $value;
        $list->save();
    }

    /** @return array<string, array{string, mixed}> */
    public static function forbiddenPostPublishFields(): array
    {
        return [
            'code' => ['code', 'PL-DIUBAH'],
            'name' => ['name', 'Diubah setelah terbit'],
            'effective_from' => ['effective_from', '2027-01-01'],
            'effective_until' => ['effective_until', '2027-12-31'],
            'laundry_brand_id' => ['laundry_brand_id', '00000000-0000-4000-8000-000000000000'],
        ];
    }

    /**
     * A published price list cannot be deleted, softly or otherwise.
     *
     * `deleted_at` is on the SYSTEM_MANAGED list, so the immutability guard
     * permitted the write and `delete()` on an active list SUCCEEDED — after
     * which the row left the default Eloquent scope and the list stopped being
     * findable. FR-036 requires a historical order's captured price to survive
     * any later change, and it resolves through this model.
     *
     * Found by independent review. Latent rather than exploited: no HTTP route
     * reaches it today. Step 5 is what would make it reachable.
     */
    public function test_a_published_price_list_cannot_be_soft_deleted(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->publishedList($context, $brand, '2026-08-01');

        $this->expectException(RuntimeException::class);

        $list->delete();
    }

    /** The control: a DRAFT may still be cleaned up. */
    public function test_a_draft_price_list_may_still_be_deleted(): void
    {
        [$context, $brand] = $this->scenario();
        $draft = $this->draft($context, $brand, 'PL-BUANG', '2026-08-01');

        $draft->delete();

        $this->assertNull(PriceList::query()->find($draft->id));
        $this->assertNotNull(PriceList::withTrashed()->find($draft->id));
    }

    /**
     * A caller may not supply its own concurrency token, even though the token
     * is now permitted to change.
     *
     * The distinction the fix rests on: `version` is server-owned bookkeeping
     * that moves as a consequence of a write, never the substance of one. If it
     * had simply been appended to the caller-facing allow-list, this would pass
     * a client-chosen value straight through and a client could defeat the
     * stale-write check by sending whatever the row currently holds.
     */
    public function test_a_client_cannot_choose_the_version(): void
    {
        [$context, $brand] = $this->scenario();
        $list = $this->publishedList($context, $brand, '2026-08-01');
        $before = (int) $list->version;

        // Paired with a genuinely permitted change, so an UPDATE actually
        // happens. Filling `version` alone leaves the model clean and Eloquent
        // issues no statement at all, which would pass for the wrong reason.
        $list->fill(['version' => 9_999]);
        $list->is_default = ! (bool) $list->is_default;
        $list->save();

        $this->assertSame($before + 1, (int) PriceList::query()->findOrFail($list->id)->version);
    }

    /**
     * Superseding advances the outgoing list's version.
     *
     * The publisher closes the outgoing window with a targeted query-builder
     * update, deliberately going around the model guard. Going around the model
     * also went around `HasOptimisticVersion`, so the row changed underneath
     * anyone holding its version while still answering to that version — a
     * stale write would have been accepted against a list that had just been
     * superseded.
     */
    public function test_superseding_advances_the_outgoing_lists_version(): void
    {
        [$context, $brand] = $this->scenario();
        $first = $this->publishedList($context, $brand, '2026-08-01');
        $before = (int) $first->fresh()->version;

        $second = $this->draft($context, $brand, 'PL-BERIKUT', '2026-09-01');
        app(PriceListPublisher::class)->publish($context, $second, $first);

        $this->assertSame(PriceList::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(
            $before + 1,
            (int) $first->fresh()->version,
            'the outgoing list changed without advancing its concurrency token'
        );
    }

    /** No HTTP route mutates a published price list. Asserted, not assumed. */
    public function test_no_route_exposes_a_post_publish_price_list_mutation(): void
    {
        $mutating = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->filter(static fn ($r): bool => str_contains($r->uri(), 'price-lists'))
            ->filter(static fn ($r): bool => (bool) array_intersect($r->methods(), ['PATCH', 'PUT', 'DELETE']))
            ->map(static fn ($r): string => implode('|', $r->methods()).' '.$r->uri())
            ->values()
            ->all();

        // Only the DRAFT item routes may mutate. A route against the price list
        // itself would be a post-publish edit surface, which does not exist.
        foreach ($mutating as $route) {
            $this->assertStringContainsString('/items', $route, "unexpected mutating route: {$route}");
        }
    }

    public function test_items_of_a_published_price_list_cannot_be_edited_or_added(): void
    {
        [$context, $brand] = $this->scenario();
        $service = $this->makeService($context->tenantId(), 'SVC-FROZEN');

        $list = $this->draft($context, $brand, 'PL-FROZEN', '2026-08-01');
        $item = $this->addItem($context, $list, $service, 15_000);

        app(PriceListPublisher::class)->publish($context, $list);

        $item->refresh();
        $this->expectException(RuntimeException::class);

        $item->amount_rupiah = 99_000;
        $item->save();
    }

    /**
     * The "or added" half of the test above, which that test never performed.
     *
     * Its name promised two properties and exercised one. The `creating` guard
     * on `PriceListItem` did exist, so nothing was broken — but an unexercised
     * guard is one refactor away from silently not existing, and the test name
     * would still have read as covering it.
     */
    public function test_an_item_cannot_be_added_to_a_published_price_list(): void
    {
        [$context, $brand] = $this->scenario();
        $service = $this->makeService($context->tenantId(), 'SVC-LATE');
        $list = $this->publishedList($context, $brand, '2026-08-01');

        $this->expectException(RuntimeException::class);

        $this->addItem($context, $list, $service, 25_000);
    }

    public function test_superseding_leaves_the_prior_version_prices_byte_identical(): void
    {
        [$context, $brand] = $this->scenario();
        $service = $this->makeService($context->tenantId(), 'SVC-HIST');

        $v1 = $this->draft($context, $brand, 'PL-V1', '2026-08-01');
        $this->addItem($context, $v1, $service, 12_000);
        app(PriceListPublisher::class)->publish($context, $v1);

        $before = PriceListItem::query()->where('price_list_id', $v1->id)->get()->toArray();

        $v2 = $this->draft($context, $brand, 'PL-V2', '2026-09-01');
        $this->addItem($context, $v2, $service, 18_000);
        app(PriceListPublisher::class)->publish($context, $v2, $v1->fresh());

        $after = PriceListItem::query()->where('price_list_id', $v1->id)->get()->toArray();

        // FR-036 depends on this: a Step 5 order that captured v1's price must
        // still resolve 12_000 after v2 exists.
        $this->assertEquals($before, $after);
        $this->assertSame(12_000, (int) $after[0]['amount_rupiah']);

        $this->assertSame(PriceList::STATUS_SUPERSEDED, $v1->fresh()->status);
        $this->assertSame($v1->id, $v2->fresh()->supersedes_price_list_id);
    }

    // -----------------------------------------------------------------------
    // FR-035 / AC-F4 — overlap prevention, enforced by PostgreSQL
    // -----------------------------------------------------------------------

    public function test_two_overlapping_active_price_lists_for_one_brand_are_rejected(): void
    {
        [$context, $brand] = $this->scenario();

        $this->publishedList($context, $brand, '2026-08-01', '2026-12-31', 'PL-A');

        $overlapping = $this->draft($context, $brand, 'PL-B', '2026-10-01', '2027-01-31');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('bertumpang tindih');

        app(PriceListPublisher::class)->publish($context, $overlapping);
    }

    public function test_price_lists_that_merely_touch_are_treated_as_overlapping(): void
    {
        [$context, $brand] = $this->scenario();

        $this->publishedList($context, $brand, '2026-08-01', '2026-08-31', 'PL-A');

        // Starts on the SAME day the first ends. Both would apply that day and
        // neither would win, so the inclusive range rejects it.
        $touching = $this->draft($context, $brand, 'PL-B', '2026-08-31', '2026-09-30');

        $this->expectException(ApiException::class);

        app(PriceListPublisher::class)->publish($context, $touching);
    }

    public function test_non_overlapping_price_lists_for_one_brand_are_allowed(): void
    {
        [$context, $brand] = $this->scenario();

        $this->publishedList($context, $brand, '2026-08-01', '2026-08-30', 'PL-A');
        $second = $this->draft($context, $brand, 'PL-B', '2026-08-31', '2026-09-30');

        $published = app(PriceListPublisher::class)->publish($context, $second);

        $this->assertSame(PriceList::STATUS_ACTIVE, $published->status);
    }

    public function test_different_brands_may_hold_overlapping_price_lists(): void
    {
        [$context, $brandA] = $this->scenario();
        $brandB = $this->makeBrand(Tenant::query()->findOrFail($context->tenantId()), 'Merek Kedua Fiktif');

        $this->publishedList($context, $brandA, '2026-08-01', '2026-12-31', 'PL-A');

        // FR-034 — brands price independently. The exclusion constraint is
        // scoped to the brand, so this must succeed.
        $forB = $this->draft($context, $brandB, 'PL-B', '2026-08-01', '2026-12-31');
        $published = app(PriceListPublisher::class)->publish($context, $forB);

        $this->assertSame(PriceList::STATUS_ACTIVE, $published->status);
    }

    // -----------------------------------------------------------------------
    // Tenant isolation (Rule 48)
    // -----------------------------------------------------------------------

    public function test_a_price_list_cannot_be_created_against_another_tenants_brand(): void
    {
        [$contextA] = $this->scenario('tenant-a');
        [$contextB, $brandB] = $this->scenario('tenant-b');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('tidak ditemukan');

        // Tenant A supplying tenant B's brand id: resolved within A's scope, so
        // it simply does not exist (Rule 48, hard rule 5).
        app(PriceListPublisher::class)->createDraft($contextA, $brandB->id, [
            'code' => 'PL-CURI',
            'name' => 'Daftar Harga Fiktif',
            'effective_from' => '2026-08-01',
        ]);
    }

    public function test_the_database_rejects_a_cross_tenant_brand_pairing_outright(): void
    {
        [$contextA] = $this->scenario('tenant-a');
        [, $brandB] = $this->scenario('tenant-b');

        // Bypassing the application entirely, as an import or a migration would.
        // The composite foreign key is what stops it.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('price_lists')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $contextA->tenantId(),
            'laundry_brand_id' => $brandB->id,
            'code' => 'PL-SQL',
            'name' => 'Daftar Harga Fiktif',
            'currency' => 'IDR',
            'status' => 'draft',
            'effective_from' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Fixtures
    // -----------------------------------------------------------------------

    /** @return array{0: TenantContext, 1: LaundryBrand} */
    private function scenario(string $slug = 'tenant-uji'): array
    {
        $this->seedCatalogue();
        $tenant = $this->makeTenant($slug);
        $brand = $this->makeBrand($tenant);
        $user = $this->makeUser();
        $membership = $this->makeMembership($tenant, $user, ['tenant_owner']);

        return [new TenantContext($tenant, Membership::query()->findOrFail($membership->id)), $brand];
    }

    private function draft(
        TenantContext $context,
        LaundryBrand $brand,
        string $code = 'PL-DRAFT',
        string $from = '2026-08-01',
        ?string $until = null,
    ): PriceList {
        return app(PriceListPublisher::class)->createDraft($context, $brand->id, [
            'code' => $code,
            'name' => 'Daftar Harga Uji Fiktif',
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
    }

    private function publishedList(
        TenantContext $context,
        LaundryBrand $brand,
        string $from,
        ?string $until = null,
        string $code = 'PL-PUB',
    ): PriceList {
        return app(PriceListPublisher::class)->publish(
            $context,
            $this->draft($context, $brand, $code, $from, $until)
        );
    }

    private function makeService(string $tenantId, string $code): Service
    {
        $service = new Service([
            'code' => $code,
            'name' => 'Layanan Uji Fiktif',
            'unit_kind' => Service::UNIT_KILOAN,
            'minimum_quantity' => 1000,
        ]);
        $service->tenant_id = $tenantId;
        $service->save();

        return $service;
    }

    private function addItem(
        TenantContext $context,
        PriceList $list,
        Service $service,
        int $amount,
    ): PriceListItem {
        $item = new PriceListItem([
            'service_id' => $service->id,
            'amount_rupiah' => $amount,
        ]);
        $item->tenant_id = $context->tenantId();
        $item->price_list_id = $list->id;
        $item->save();

        return $item;
    }
}
