<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Modules\Ordering\Models\Order;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Services\ProductionReadyService;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Production\Services\QualityControlService;
use App\Modules\Tracking\Http\PublicTrackingProjection;
use App\Modules\Tracking\Services\TrackingTokenService;
use App\Modules\Tracking\Support\CustomerVisibleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT B — THE PUBLIC PROJECTION (FR-089, FR-090).
 *
 * The exclusion tests are the ones that matter. A projection that shows the right
 * fields is table stakes; a projection that CANNOT show the wrong ones is the
 * security property, and the way to test it is to put the dangerous values in the
 * database first and then prove they are absent from the output.
 */
final class PublicProjectionTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function project(string $slug): array
    {
        $s = $this->trackingScenario($slug);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        return [PublicTrackingProjection::build($issued->token, $s['order']->fresh()), $s];
    }

    // =====================================================================
    // FR-089 — the safe-by-default content set
    // =====================================================================

    public function test_the_projection_key_set_is_exactly_the_allow_list(): void
    {
        [$projection] = $this->project('proj-keys');

        $keys = array_keys($projection);
        sort($keys);
        $allowed = PublicTrackingProjection::ALLOWED;
        sort($allowed);

        // Equality in BOTH directions. A missing field is a broken portal; an
        // extra field is a disclosure, and only equality catches both.
        $this->assertSame($allowed, $keys,
            'The public projection must assemble exactly the FR-089 field set — no more, no less. '
            .'A field not enumerated is not served (TRK-028).');
    }

    public function test_it_carries_the_order_number_brand_outlet_and_service(): void
    {
        [$projection, $s] = $this->project('proj-content');

        $this->assertSame($s['order']->order_number, $projection['order_number']);
        $this->assertStringContainsString('Merek Fiktif', $projection['brand']['name']);
        $this->assertStringContainsString('Outlet Fiktif', $projection['outlet']['name']);
        $this->assertSame(['Cuci Kiloan (fiktif)'], $projection['service_types']);
    }

    public function test_amount_due_is_an_integer_read_from_the_ledger(): void
    {
        [$projection] = $this->project('proj-amount');

        $this->assertIsInt($projection['amount_due_rupiah']);
        $this->assertSame(24000, $projection['amount_due_rupiah']);
        $this->assertSame('BELUM_DIBAYAR', $projection['payment_state']['code']);
    }

    // =====================================================================
    // FR-090 — the exclusions, proven against data that is actually present
    // =====================================================================

    public function test_it_never_carries_the_full_address(): void
    {
        $s = $this->trackingScenario('proj-no-address');
        $this->giveCustomerAnAddress($s['context']->tenantId(), $s['customer_id']);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $encoded = json_encode(PublicTrackingProjection::build($issued->token, $s['order']));

        // The address EXISTS in the database. The point is that no part of it
        // reaches the projection, in any state, with or without OTP (TRK-010).
        foreach (['Jalan Contoh Fiktif', 'RT 04', 'Kecamatan Fiktif', 'Kota Fiktif', '40000'] as $fragment) {
            $this->assertStringNotContainsString($fragment, (string) $encoded,
                "The portal must never show any part of a customer's address (FR-090, TRK-010).");
        }
    }

    public function test_it_never_carries_the_full_phone_number(): void
    {
        [$projection] = $this->project('proj-no-phone');

        $encoded = (string) json_encode($projection);
        $this->assertStringNotContainsString('6281200000000', $encoded);
        $this->assertStringNotContainsString('081200000000', $encoded);

        // Masked to country code plus the last four: enough to recognise, far too
        // few to dial (Rule 32 hard rule 4).
        $this->assertStringContainsString('0000', $projection['customer']['masked_phone']);
        $this->assertStringContainsString('+62', $projection['customer']['masked_phone']);
    }

    public function test_the_customer_name_is_masked(): void
    {
        [$projection] = $this->project('proj-masked-name');

        // "Budi Santoso Fiktif" -> given name plus an initial.
        $this->assertSame('Budi F.', $projection['customer']['masked_name']);
        $this->assertStringNotContainsString('Santoso', (string) json_encode($projection));
    }

    public function test_it_never_carries_internal_notes(): void
    {
        [$projection] = $this->project('proj-no-notes');

        // The fixture puts a real internal note on the order on purpose.
        $this->assertStringNotContainsString('Catatan internal', (string) json_encode($projection),
            'Internal notes are never shown on the portal (FR-090, TRK-016).');
        $this->assertStringNotContainsString('pewangi', (string) json_encode($projection));
    }

    public function test_it_never_carries_internal_identifiers(): void
    {
        [$projection, $s] = $this->project('proj-no-ids');

        $encoded = (string) json_encode($projection);

        foreach ([
            $s['order']->id,
            $s['order']->tenant_id,
            $s['customer_id'],
            $s['outlet_id'],
        ] as $internalId) {
            $this->assertStringNotContainsString($internalId, $encoded,
                'An internal UUID on a public surface is an enumeration handle.');
        }
    }

    public function test_it_never_carries_the_token_or_its_hash(): void
    {
        $s = $this->trackingScenario('proj-no-token');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $encoded = (string) json_encode(PublicTrackingProjection::build($issued->token, $s['order']));

        $this->assertStringNotContainsString($issued->plaintext(), $encoded);
        $this->assertStringNotContainsString($issued->token->token_hash, $encoded);
    }

    public function test_it_never_carries_another_order_of_the_same_customer(): void
    {
        $s = $this->trackingScenario('proj-no-other-orders');

        $otherOrderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $otherOrderId,
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'customer_id' => $s['customer_id'],
            'order_number' => 'ALS-2026-999999',
            'client_reference' => (string) Str::uuid(),
            'status' => Order::STATUS_RECEIVED,
            // The Step 5 CHECK requires total = subtotal - discount; a fixture
            // that ignores it is a fixture describing an order that cannot exist.
            'subtotal_rupiah' => 50000,
            'discount_rupiah' => 0,
            'total_rupiah' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $encoded = (string) json_encode(PublicTrackingProjection::build($issued->token, $s['order']));

        // The same customer's OTHER order exists and must be invisible: a link
        // is scoped to exactly one order (TRK-015, TRK-020).
        $this->assertStringNotContainsString('ALS-2026-999999', $encoded);
        $this->assertStringNotContainsString($otherOrderId, $encoded);
    }

    public function test_no_eta_is_fabricated(): void
    {
        [$projection] = $this->project('proj-no-eta');

        // Step 7 computes no ETA and the product never presents an estimate as a
        // guarantee (Rule 09 hard rule 1, TRACKING_DOMAIN §8).
        $this->assertNull($projection['estimated_completion']);
    }

    // =====================================================================
    // Status mapping and the immutable readiness anchor
    // =====================================================================

    public function test_internal_statuses_are_not_exposed_verbatim(): void
    {
        $s = $this->trackingScenario('proj-status-map');
        DB::table('orders')->where('id', $s['order']->id)->update(['status' => 'WASHING']);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order']->fresh(), $this->ref());

        $projection = PublicTrackingProjection::build($issued->token, $s['order']->fresh());

        // The customer sees "sedang dikerjakan", not the tenant's floor layout.
        $this->assertSame(CustomerVisibleStatus::IN_PROGRESS, $projection['status']['code']);
        $this->assertStringNotContainsString('WASHING', (string) json_encode($projection));
    }

    public function test_rework_is_shown_as_quality_checking_not_as_going_backwards(): void
    {
        $s = $this->trackingScenario('proj-rework');
        DB::table('orders')->where('id', $s['order']->id)->update(['status' => 'REWORK']);
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order']->fresh(), $this->ref());

        $projection = PublicTrackingProjection::build($issued->token, $s['order']->fresh());

        $this->assertSame(CustomerVisibleStatus::CHECKING, $projection['status']['code']);
        $this->assertStringNotContainsString('REWORK', (string) json_encode($projection));
    }

    /**
     * The claim that must survive rework (Rule 10, FR-076/FR-077).
     *
     * A customer told "siap diambil" must never afterwards be told "not ready" —
     * the first-ready fact is immutable and this projection reads it, never
     * re-derives it from the current status.
     */
    public function test_readiness_stays_true_after_the_order_re_enters_rework(): void
    {
        $s = $this->trackingScenario('proj-ready-sticky');
        $registry = new ProductionRegistry();
        $qc = new QualityControlService();
        $ready = app(ProductionReadyService::class);

        $job = $registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $job = $registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'FINISHING');
        $job = $registry->sendToQualityControl($s['context'], $job->id, (int) $job->version, $this->ref());
        $qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $this->ref());
        $ready->markReady($s['context'], $job->fresh()->id, $this->ref());

        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order']->fresh(), $this->ref());

        $whenReady = PublicTrackingProjection::build($issued->token, $s['order']->fresh());
        $this->assertTrue($whenReady['is_ready_for_pickup']);
        $this->assertNotNull($whenReady['first_ready_at']);
        $firstReadyAt = $whenReady['first_ready_at'];

        // The order goes back into rework afterwards.
        DB::table('orders')->where('id', $s['order']->id)->update(['status' => 'REWORK']);

        $afterRework = PublicTrackingProjection::build($issued->token, $s['order']->fresh());

        $this->assertTrue($afterRework['is_ready_for_pickup'],
            'A customer told "siap diambil" must never afterwards be told the laundry is not ready.');
        $this->assertSame($firstReadyAt, $afterRework['first_ready_at'],
            'The first-ready anchor is immutable and is never re-derived (Rule 10, FR-077).');
    }

    public function test_the_timeline_is_built_from_recorded_facts_in_order(): void
    {
        $s = $this->trackingScenario('proj-timeline');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        $projection = PublicTrackingProjection::build($issued->token, $s['order']->fresh());

        $this->assertNotEmpty($projection['status_history']);
        $this->assertSame(CustomerVisibleStatus::RECEIVED, $projection['status_history'][0]['code']);

        $timestamps = array_column($projection['status_history'], 'occurred_at');
        $sorted = $timestamps;
        sort($sorted);
        $this->assertSame($sorted, $timestamps, 'The timeline must be chronological.');
    }

    public function test_a_cancelled_order_shows_no_available_actions_and_no_reason(): void
    {
        $s = $this->trackingScenario('proj-cancelled');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());

        DB::table('orders')->where('id', $s['order']->id)->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Catatan internal: pelanggan tidak dapat dihubungi.',
        ]);

        $projection = PublicTrackingProjection::build($issued->token, $s['order']->fresh());

        $this->assertSame(CustomerVisibleStatus::CANCELLED, $projection['status']['code']);
        $this->assertSame([], $projection['available_actions']);

        // The cancellation REASON is an internal operational note and may name a
        // staff member or a dispute (FR-058). The fact is public; the reason is not.
        $this->assertStringNotContainsString('tidak dapat dihubungi', (string) json_encode($projection));
    }
}
