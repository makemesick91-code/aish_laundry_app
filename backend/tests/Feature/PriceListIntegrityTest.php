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
