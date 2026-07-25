<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Notification\Services\NotificationIntentService;
use App\Modules\Notification\Services\QuietHours;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Organization\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT F — CONSENT, CLASSIFICATION, AND QUIET HOURS (FR-096, FR-097).
 *
 * The boundary cases are the substance here. "Quiet hours are respected" is easy
 * to claim and easy to get wrong at 20:00 exactly, at midnight, and in a tenant
 * whose outlets span WIB and WIT.
 */
final class NotificationPolicyTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private NotificationIntentService $intents;

    private FakeNotificationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intents = app(NotificationIntentService::class);
        $this->provider = app(NotificationProvider::class)->reset();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Freeze the clock at a given WIB wall-clock time (UTC+7). */
    private function atJakarta(string $time): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-25 '.$time, 'Asia/Jakarta')->setTimezone('UTC'));
    }

    // =====================================================================
    // FR-096 — transactional vs marketing, and consent
    // =====================================================================

    public function test_a_transactional_message_needs_no_marketing_consent(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-transactional');

        // No consent row at all. A message about the customer's OWN order does
        // not require marketing consent (NOTIFICATION_DOMAIN §6).
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->assertSame(NotificationIntent::CATEGORY_TRANSACTIONAL, $intent->category);
        $this->assertSame(NotificationIntent::STATE_PENDING, $intent->state);
    }

    public function test_marketing_with_no_consent_row_is_blocked_because_absence_is_not_consent(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-marketing-none');

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        // NOT-011: a customer who has never been asked has not agreed. The
        // default is BLOCKED, not allowed.
        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $intent->state);
        $this->assertSame(NotificationIntent::SUPPRESSED_MARKETING_NO_CONSENT, $intent->suppression_reason);
    }

    public function test_marketing_with_granted_consent_is_allowed(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-marketing-granted');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        $this->assertSame(NotificationIntent::STATE_PENDING, $intent->state);
    }

    public function test_a_withdrawal_after_a_grant_blocks_marketing(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-marketing-withdrawn');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_WITHDRAWN);

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $intent->state);
        $this->assertSame(NotificationIntent::SUPPRESSED_MARKETING_OPTED_OUT, $intent->suppression_reason);
    }

    public function test_opt_out_is_honoured_at_send_time_not_only_at_queue_time(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-optout-at-send');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');
        $this->assertSame(NotificationIntent::STATE_PENDING, $intent->state);

        // The customer opts out AFTER the message was queued (NOT-005).
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_WITHDRAWN);

        $result = (new NotificationDispatcher($this->provider))->dispatch($intent->fresh());

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $result->state);
        $this->assertSame([], $this->provider->sent,
            'A customer who opts out between queue and dispatch has still opted out.');
    }

    public function test_the_category_comes_from_the_template_and_cannot_be_relabelled(): void
    {
        // NOT-024, structurally: `categoryFor()` is the only source, and a caller
        // has no argument through which to claim a different category.
        $this->assertSame(
            NotificationIntent::CATEGORY_MARKETING,
            NotificationTemplate::categoryFor(NotificationTemplate::MARKETING_PROMOTION)
        );
        $this->assertSame(
            NotificationIntent::CATEGORY_TRANSACTIONAL,
            NotificationTemplate::categoryFor(NotificationTemplate::ORDER_RECEIVED)
        );

        $reflection = new \ReflectionMethod(NotificationIntentService::class, 'enqueue');
        $parameters = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $reflection->getParameters()
        );

        $this->assertNotContains('category', $parameters,
            'If a caller could pass a category, "marketing is never routed through a '
            .'transactional path" would be a convention rather than a guarantee (NOT-024).');
    }

    public function test_an_unknown_template_is_refused_rather_than_defaulted(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-unknown-template');

        // Defaulting would mean guessing whether the message needs consent.
        // `enqueue` catches and returns null rather than throwing into business code.
        $this->assertNull($this->intents->enqueue($s['order'], 'promo_rahasia', 'marketing.unknown'));
    }

    public function test_an_unreachable_recipient_is_suppressed_with_a_stated_reason(): void
    {
        // Asserted against SendPolicy directly, and the reason is worth stating:
        // Step 4 makes `customers.phone_normalized` NOT NULL, so a customer row
        // with no destination cannot exist. The guard is therefore DEFENSIVE — it
        // covers an order with no customer, and any future path that yields an
        // empty destination. Driving it through a customer row would require
        // faking a database state the schema forbids, which would prove nothing.
        $result = \App\Modules\Notification\Services\SendPolicy::evaluate(
            NotificationTemplate::ORDER_RECEIVED,
            null,
            '',
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame(NotificationIntent::SUPPRESSED_NO_DESTINATION, $result['reason']);
    }

    public function test_a_grant_and_a_withdrawal_in_the_same_instant_resolve_to_withdrawn(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-consent-tie');

        // The clock is frozen, so both rows carry an identical `recorded_at` —
        // the same-second race that a random-UUID tiebreak would resolve
        // arbitrarily. Ambiguity must resolve to "do not send".
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_WITHDRAWN);

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $intent->state);
        $this->assertSame(NotificationIntent::SUPPRESSED_MARKETING_OPTED_OUT, $intent->suppression_reason,
            'The cost of wrongly withholding a promotion is one message; the cost of '
            .'wrongly sending one is a compliance failure. The tie breaks toward withdrawal.');
    }

    // =====================================================================
    // FR-097 — quiet hours, at the boundaries
    // =====================================================================

    public function test_the_quiet_hours_boundaries_are_exact(): void
    {
        $outlet = new Outlet(['timezone' => 'Asia/Jakarta']);

        $cases = [
            '19:59' => false, // still permitted
            '20:00' => true,  // inclusive start
            '23:30' => true,
            '00:30' => true,  // the window wraps midnight
            '07:59' => true,
            '08:00' => false, // exclusive end
            '12:00' => false,
        ];

        foreach ($cases as $wallClock => $expectedQuiet) {
            $instant = Carbon::parse('2026-07-25 '.$wallClock, 'Asia/Jakarta')->setTimezone('UTC');

            $this->assertSame($expectedQuiet, QuietHours::isQuiet($outlet, $instant),
                "Quiet-hours evaluation is wrong at {$wallClock} outlet-local.");
        }
    }

    public function test_a_message_due_inside_quiet_hours_is_deferred_and_never_dropped(): void
    {
        $this->atJakarta('22:30');
        $s = $this->trackingScenario('policy-quiet-defer');

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        // Deferred, not dropped and not sent anyway (NOT-021).
        $this->assertSame(NotificationIntent::STATE_DEFERRED, $intent->state);
        $this->assertTrue((bool) $intent->deferred_for_quiet_hours);
        $this->assertNotNull($intent->scheduled_for);

        $localTarget = $intent->scheduled_for->copy()->setTimezone('Asia/Jakarta');
        $this->assertSame(8, (int) $localTarget->format('G'),
            'A deferred message resumes at the next permitted window: 08.00 outlet-local.');
    }

    public function test_a_message_queued_after_midnight_defers_to_the_same_morning(): void
    {
        $this->atJakarta('01:30');
        $s = $this->trackingScenario('policy-quiet-midnight');

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $localTarget = $intent->scheduled_for->copy()->setTimezone('Asia/Jakarta');

        // 01:30 defers by six and a half hours, not by thirty-two: the window
        // wraps, so "the next 08.00" is THIS morning.
        $this->assertSame(8, (int) $localTarget->format('G'));
        $this->assertSame('2026-07-25', $localTarget->format('Y-m-d'));
    }

    public function test_quiet_hours_are_evaluated_in_outlet_local_time_not_server_time(): void
    {
        // 22:00 in Jayapura (WIT, UTC+9) is 20:00 in Jakarta (WIB, UTC+7).
        // Both are inside quiet hours locally, but a server evaluating one zone
        // for both would get one of them wrong.
        Carbon::setTestNow(Carbon::parse('2026-07-25 22:30', 'Asia/Jayapura')->setTimezone('UTC'));

        $jayapura = $this->trackingScenario('policy-tz-jayapura', 'Asia/Jayapura');
        $intentJ = $this->intents->enqueue($jayapura['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->assertSame(NotificationIntent::STATE_DEFERRED, $intentJ->state,
            '22:30 in Jayapura is inside quiet hours for a Jayapura outlet.');

        // The SAME instant is 20:30 in Jakarta — also quiet. Shift to a moment
        // that is quiet in Jayapura but NOT in Jakarta to prove independence:
        // 06:00 WIT = 04:00 WIB. Both quiet. Use 09:00 WIT = 07:00 WIB instead:
        // permitted in Jayapura, still quiet in Jakarta.
        Carbon::setTestNow(Carbon::parse('2026-07-25 09:00', 'Asia/Jayapura')->setTimezone('UTC'));

        $jakarta = $this->trackingScenario('policy-tz-jakarta', 'Asia/Jakarta');
        $jayapura2 = $this->trackingScenario('policy-tz-jayapura2', 'Asia/Jayapura');

        $intentJakarta = $this->intents->enqueue($jakarta['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $intentJayapura = $this->intents->enqueue($jayapura2['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->assertSame(NotificationIntent::STATE_DEFERRED, $intentJakarta->state,
            '07:00 Jakarta is inside quiet hours.');
        $this->assertSame(NotificationIntent::STATE_PENDING, $intentJayapura->state,
            '09:00 Jayapura is outside quiet hours — evaluated in the OUTLET\'s zone.');
    }

    public function test_an_unusable_outlet_timezone_fails_closed(): void
    {
        $outlet = new Outlet(['timezone' => 'Bukan/Zona_Waktu']);
        $instant = Carbon::parse('2026-07-25 12:00', 'UTC');

        // Sending at an unknown local hour is the exact harm quiet hours exist to
        // prevent, so an unparseable timezone defers rather than sends.
        $this->assertTrue(QuietHours::isQuiet($outlet, $instant));
        $this->assertTrue(QuietHours::nextPermitted($outlet, $instant)->greaterThan($instant));
    }

    public function test_an_empty_outlet_timezone_fails_closed(): void
    {
        $outlet = new Outlet(['timezone' => '']);
        $this->assertTrue(QuietHours::isQuiet($outlet, Carbon::parse('2026-07-25 12:00', 'UTC')));
    }

    public function test_quiet_hours_are_re_checked_at_dispatch_not_only_at_enqueue(): void
    {
        // Queued at 19:58, picked up at 20:03 by a worker that was busy.
        $this->atJakarta('19:58');
        $s = $this->trackingScenario('policy-quiet-recheck');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->assertSame(NotificationIntent::STATE_PENDING, $intent->state);

        $this->atJakarta('20:03');
        $result = (new NotificationDispatcher($this->provider))->dispatch($intent->fresh());

        $this->assertSame(NotificationIntent::STATE_DEFERRED, $result->state);
        $this->assertSame([], $this->provider->sent,
            'Checking quiet hours only at enqueue would send this message inside the window.');
    }

    public function test_every_template_the_outbox_carries_defers_inside_quiet_hours(): void
    {
        $this->atJakarta('23:00');

        // NOT-022 permits an exception ONLY where the Master Source or an accepted
        // decision record grants one. DEC-0040 grants exactly ONE, for exactly one
        // class — a customer-initiated OTP, delivered synchronously by
        // `OtpMessenger` and never by this outbox. This test is the other half of
        // that guarantee: it proves the exception did not leak into the outbox, so
        // everything the outbox carries still defers.
        $carried = 0;

        foreach (NotificationTemplate::keys() as $index => $templateKey) {
            if (NotificationTemplate::carriesOtp($templateKey)) {
                // Not carried by this path at all — asserted separately, below.
                continue;
            }

            $s = $this->trackingScenario('policy-no-exception-'.$index);

            if (NotificationTemplate::isMarketing($templateKey)) {
                $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);
            }

            $intent = $this->intents->enqueue($s['order'], $templateKey, 'uji.'.$templateKey);

            $this->assertSame(NotificationIntent::STATE_DEFERRED, $intent->state,
                "Template {$templateKey} bypassed quiet hours. The only exception is "
                .'DEC-0040\'s customer-initiated OTP, which this path never carries.');

            $this->assertNull($intent->security_classification,
                "Template {$templateKey} acquired a DEC-0040 security classification on the "
                .'ordinary outbox. Nothing the outbox carries is a user-initiated security transaction.');

            $carried++;
        }

        $this->assertGreaterThan(0, $carried,
            'The loop asserted nothing. A guard that skips every case is not a guard.');
    }

    public function test_the_outbox_refuses_an_otp_carrying_template_outright(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('policy-otp-not-enqueueable');

        // DEC-0040 decision item 3. Two independent reasons, either sufficient: this
        // path renders bodies at dispatch time and holds no code (storing one would
        // breach NOT-016), and an enqueue is by definition not the explicit customer
        // request the exemption is gated on. Refusing here keeps the exempt path
        // down to the single caller that holds a live plaintext code.
        $this->assertNull(
            $this->intents->enqueue($s['order'], NotificationTemplate::TRACKING_OTP, 'tracking.otp.requested'),
            'An OTP template was accepted by the ordinary outbox. That is the route by which '
            .'the DEC-0040 quiet-hours exemption would become reachable without a customer request.'
        );

        $this->assertDatabaseCount('notification_intents', 0);
    }
}
