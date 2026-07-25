<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\CustomerManagement\Models\CustomerConsent;
use App\Modules\Notification\Contracts\MessageSecurityClassification;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OtpDispatchOrigin;
use App\Modules\Notification\Contracts\ProviderResult;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Services\NotificationIntentService;
use App\Modules\Notification\Services\OtpMessenger;
use App\Modules\Notification\Services\QuietHours;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Organization\Models\Outlet;
use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingOtpChallenge;
use App\Modules\Tracking\Services\PublicTrackingResolver;
use App\Modules\Tracking\Services\TrackingOtpService;
use App\Modules\Tracking\Services\TrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * DEC-0040 — THE ONE QUIET-HOURS EXEMPTION, AND ITS FENCES.
 *
 * The repository owner resolved OQ-018: a customer-initiated OTP for a canonical
 * FR-091 sensitive action is a `USER_INITIATED_SECURITY_TRANSACTION` and is exempt
 * from quiet hours 20.00–08.00 outlet local time.
 *
 * An exemption is only as good as what it CANNOT reach, so roughly half of this file
 * is fences rather than the exemption itself: an OTP nobody asked for is refused, the
 * ordinary outbox will not carry an OTP template at all, marketing still defers,
 * opt-out is still honoured, the rate limit and resend cooldown are unmoved, and the
 * database refuses a row that claims the exemption and a quiet-hours deferral at once.
 *
 * The boundary cases are the substance: 19:59 (outside the window), 20:00 (inclusive
 * start), 00:00 (across the midnight wrap), and 07:59 (last quiet minute) are each
 * asserted, because "exempt from quiet hours" is easy to claim and easy to get wrong
 * at exactly the edges the window is defined by.
 */
final class OtpQuietHoursExemptionTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private OtpMessenger $messenger;

    private FakeNotificationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = app(NotificationProvider::class)->reset();
        $this->messenger = app(OtpMessenger::class);
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

    /** A fictional six-digit code. Never a real one, and never persisted. */
    private function code(): string
    {
        return '024680';
    }

    /** @param array<string, mixed> $scenario */
    private function outletOf(array $scenario): Outlet
    {
        return Outlet::query()->findOrFail($scenario['outlet_id']);
    }

    private function intentFor(string $tenantId): ?NotificationIntent
    {
        return NotificationIntent::query()
            ->forTenant($tenantId)
            ->where('template_key', NotificationTemplate::TRACKING_OTP)
            ->first();
    }

    // =====================================================================
    // The exemption itself — at every boundary of the window
    // =====================================================================

    public function test_a_customer_request_at_1959_is_eligible(): void
    {
        // Outside the window entirely. Included so the boundary cases below are
        // read against a case that never needed the exemption at all.
        $this->atJakarta('19:59');
        $s = $this->trackingScenario('otp-exempt-1959');

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        $this->assertSame(NotificationIntent::STATE_SENT, $state);
        $this->assertCount(1, $this->provider->sent);
    }

    /**
     * 20:00, 00:00, and 07:59 outlet-local: the inclusive start, the midnight wrap,
     * and the last minute before the window closes. Every one is INSIDE quiet hours
     * and every one must be sent immediately.
     */
    public function test_a_customer_request_inside_quiet_hours_is_eligible_immediately(): void
    {
        foreach (['20:00', '00:00', '07:59'] as $index => $wallClock) {
            $this->provider->reset();
            $this->atJakarta($wallClock);

            $s = $this->trackingScenario('otp-exempt-'.$index);

            // Sanity: this instant really is inside the window. Without this the
            // test could pass by accidentally choosing a permitted hour.
            $this->assertTrue(
                QuietHours::isQuiet($this->outletOf($s), Carbon::now('UTC')),
                "{$wallClock} outlet-local should be inside quiet hours."
            );

            $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

            $this->assertSame(NotificationIntent::STATE_SENT, $state,
                "A customer-requested OTP at {$wallClock} was not sent. DEC-0040 exempts it.");
            $this->assertCount(1, $this->provider->sent,
                "Nothing reached the provider at {$wallClock}.");

            $intent = $this->intentFor($s['context']->tenantId());
            $this->assertNotNull($intent);

            $this->assertFalse((bool) $intent->deferred_for_quiet_hours,
                "The intent at {$wallClock} was marked deferred for quiet hours despite being exempt.");
            $this->assertSame(
                MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION,
                $intent->security_classification,
                "The DEC-0040 classification was not recorded at {$wallClock}."
            );
            $this->assertTrue($intent->isQuietHoursExempt());

            // `scheduled_for` is NOW, not the next 08.00 — a deferred five-minute
            // challenge is a challenge that expired before its message arrived.
            $this->assertTrue(
                $intent->scheduled_for->equalTo(Carbon::now('UTC')),
                "The message at {$wallClock} was scheduled forward instead of sent now."
            );
        }
    }

    public function test_the_exemption_is_evaluated_in_outlet_local_time(): void
    {
        // 06:00 in Jayapura (WIT, UTC+9) is 04:00 in Jakarta (WIB). Both are quiet
        // locally; the point is that the exempt path resolves the same way in a
        // non-WIB zone rather than quietly assuming Asia/Jakarta.
        Carbon::setTestNow(Carbon::parse('2026-07-25 06:00', 'Asia/Jayapura')->setTimezone('UTC'));

        $s = $this->trackingScenario('otp-exempt-jayapura', 'Asia/Jayapura');

        $this->assertTrue(QuietHours::isQuiet($this->outletOf($s), Carbon::now('UTC')));

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        $this->assertSame(NotificationIntent::STATE_SENT, $state);
    }

    // =====================================================================
    // The fences — what the exemption cannot reach
    // =====================================================================

    public function test_an_automated_otp_without_an_explicit_customer_request_is_rejected(): void
    {
        // Midday: outside quiet hours entirely, so the refusal cannot be mistaken
        // for a deferral. The gate is the ORIGIN, not the hour.
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('otp-automated-refused');

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::Automated);

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $state);
        $this->assertSame([], $this->provider->sent,
            'An OTP nobody requested reached the provider.');

        $intent = $this->intentFor($s['context']->tenantId());
        $this->assertNotNull($intent);
        $this->assertSame(NotificationIntent::SUPPRESSED_OTP_NOT_CUSTOMER_INITIATED, $intent->suppression_reason);

        // REFUSED, not deferred. Sending a code no customer asked for is the abuse
        // the gate exists to prevent; delaying it does not make it acceptable.
        $this->assertNotSame(NotificationIntent::STATE_DEFERRED, $intent->state);
        $this->assertNull($intent->security_classification,
            'An automated origin acquired the DEC-0040 classification, and with it the exemption.');
    }

    public function test_an_automated_otp_inside_quiet_hours_is_rejected_rather_than_deferred(): void
    {
        $this->atJakarta('23:30');
        $s = $this->trackingScenario('otp-automated-quiet');

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::Automated);

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $state);
        $this->assertSame([], $this->provider->sent);
    }

    public function test_the_ordinary_outbox_refuses_an_otp_carrying_template(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('otp-outbox-refused');

        // The outbox renders bodies at dispatch time and holds no code. Letting it
        // carry an OTP template would either persist a code (NOT-016) or ship an
        // empty `:otp_code` to a customer — and it would be a second route to the
        // exempt path that no customer request had to pass through.
        $this->assertNull(
            app(NotificationIntentService::class)
                ->enqueue($s['order'], NotificationTemplate::TRACKING_OTP, 'tracking.otp.requested')
        );

        $this->assertDatabaseCount('notification_intents', 0);
    }

    public function test_a_marketing_notification_during_quiet_hours_is_still_deferred(): void
    {
        $this->atJakarta('22:00');
        $s = $this->trackingScenario('otp-marketing-still-defers');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);

        $intent = app(NotificationIntentService::class)
            ->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        $this->assertSame(NotificationIntent::STATE_DEFERRED, $intent->state,
            'DEC-0040 exempts one class. Marketing is not it, and never becomes it.');
        $this->assertTrue((bool) $intent->deferred_for_quiet_hours);
        $this->assertNull($intent->security_classification);
    }

    public function test_a_transactional_order_notification_during_quiet_hours_is_still_deferred(): void
    {
        $this->atJakarta('22:00');
        $s = $this->trackingScenario('otp-transactional-still-defers');

        $intent = app(NotificationIntentService::class)
            ->enqueue($s['order'], NotificationTemplate::ORDER_READY_FOR_PICKUP, 'order.ready');

        // The exemption is NOT "transactional messages may be sent at any hour".
        // A transactional category was never an exemption and still is not.
        $this->assertSame(NotificationIntent::STATE_DEFERRED, $intent->state);
    }

    public function test_marketing_opt_out_is_not_bypassed_by_the_exemption(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-optout-not-bypassed');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_GRANTED);
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_WITHDRAWN);

        $marketing = app(NotificationIntentService::class)
            ->enqueue($s['order'], NotificationTemplate::MARKETING_PROMOTION, 'marketing.promo');

        $this->assertSame(NotificationIntent::STATE_SUPPRESSED, $marketing->state);
        $this->assertSame(NotificationIntent::SUPPRESSED_MARKETING_OPTED_OUT, $marketing->suppression_reason);
        $this->assertSame([], $this->provider->sent);
    }

    public function test_marketing_opt_out_does_not_block_a_customer_requested_security_otp(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-optout-does-not-block-otp');
        $this->recordConsent($s['context']->tenantId(), $s['customer_id'], CustomerConsent::STATE_WITHDRAWN);

        // DEC-0040 decision item 4. The OTP template is TRANSACTIONAL by catalogue
        // definition, and a customer who declined promotions has said nothing about
        // a verification code they themselves just asked for.
        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        $this->assertSame(NotificationIntent::STATE_SENT, $state);
        $this->assertCount(1, $this->provider->sent);
    }

    // =====================================================================
    // Rate limiting, cooldown, and the rest of the controls DEC-0040 keeps
    // =====================================================================

    public function test_the_per_token_rate_limit_still_refuses_a_challenge_inside_quiet_hours(): void
    {
        $this->atJakarta('02:00');

        $s = $this->trackingScenario('otp-ratelimit-quiet');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $access = app(PublicTrackingResolver::class)->resolve($issued->plaintext(), '127.0.0.1');
        $this->assertNotNull($access);

        $otp = app(TrackingOtpService::class);
        $action = TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS;

        // Three challenges per token per hour. Consume the budget by clearing the
        // cooldown between issues — the cooldown is asserted separately below, and
        // conflating the two would leave one of them untested.
        for ($i = 0; $i < 3; $i++) {
            $this->assertNotNull($otp->issue($access, $action, '127.0.0.1'),
                "Challenge {$i} should have been issued within the per-token budget.");

            DB::table('tracking_otp_challenges')
                ->where('tracking_token_id', $access->token->id)
                ->update(['consumed_at' => now()]);
        }

        $this->assertNull($otp->issue($access, $action, '127.0.0.1'),
            'The per-token rate limit was relaxed by the DEC-0040 exemption. It moves the '
            .'schedule and nothing else (DEC-0040 decision item 5).');
    }

    public function test_the_resend_cooldown_still_refuses_a_second_challenge_inside_quiet_hours(): void
    {
        $this->atJakarta('02:00');

        $s = $this->trackingScenario('otp-cooldown-quiet');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $access = app(PublicTrackingResolver::class)->resolve($issued->plaintext(), '127.0.0.1');
        $this->assertNotNull($access);

        $otp = app(TrackingOtpService::class);
        $action = TrackingOtpChallenge::ACTION_REQUEST_SCHEDULE_CHANGE;

        $this->assertNotNull($otp->issue($access, $action, '127.0.0.1'));

        // A live, unconsumed, unexpired challenge exists. Re-issuing would let the
        // endpoint be used to message a phone number the requester does not own —
        // at 02.00, which is exactly when that matters most.
        $this->assertNull($otp->issue($access, $action, '127.0.0.1'));
    }

    public function test_dedup_still_applies_to_a_customer_requested_otp(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-dedup-quiet');

        $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);
        $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        // FR-098: one intent for (recipient, event, order, window). The cooldown in
        // TrackingOtpService is the first line; this is the structural backstop.
        $this->assertDatabaseCount('notification_intents', 1);
    }

    // =====================================================================
    // The audit record, and the structural guarantee behind it
    // =====================================================================

    public function test_the_audit_records_the_user_initiated_security_classification(): void
    {
        $this->atJakarta('01:00');

        $s = $this->trackingScenario('otp-audit-classification');
        $issued = app(TrackingTokenService::class)->issue($s['context'], $s['order'], $this->ref());
        $access = app(PublicTrackingResolver::class)->resolve($issued->plaintext(), '127.0.0.1');
        $this->assertNotNull($access);

        $code = app(TrackingOtpService::class)
            ->issue($access, TrackingOtpChallenge::ACTION_CHANGE_DELIVERY_ADDRESS, '127.0.0.1');
        $this->assertNotNull($code);

        $this->messenger->send($s['order'], $code, OtpDispatchOrigin::CustomerRequest);

        // The tracking side: the row that proves a CUSTOMER asked.
        $event = TrackingAccessEvent::query()
            ->forTenant($access->token->tenant_id)
            ->where('type', TrackingAccessEvent::TYPE_OTP_CHALLENGE_ISSUED)
            ->firstOrFail();

        $this->assertSame(
            MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION,
            $event->payload['classification'] ?? null
        );

        // And the notification side: why the message was not held until 08.00.
        $intent = $this->intentFor($s['context']->tenantId());
        $this->assertNotNull($intent);
        $this->assertSame(
            MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION,
            $intent->security_classification
        );

        // The audit still carries no code, at any hour and on either side.
        $this->assertStringNotContainsString($code, (string) json_encode($event->payload));
        $this->assertStringNotContainsString($code, (string) json_encode($intent->toArray()));
    }

    public function test_the_database_refuses_an_exempt_intent_that_claims_a_quiet_hours_deferral(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-check-constraint');

        $this->expectException(\Illuminate\Database\QueryException::class);

        // The two claims contradict each other: a message cannot be exempt from
        // quiet hours AND deferred for them. DEC-0040 makes that structural rather
        // than a convention a future code path must remember.
        DB::table('notification_intents')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'order_id' => $s['order']->id,
            'customer_id' => $s['customer_id'],
            'event_type' => 'tracking.otp.requested',
            'template_key' => NotificationTemplate::TRACKING_OTP,
            'template_version' => 1,
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'channel' => 'whatsapp',
            'recipient_normalized' => '6281200000000',
            'dedup_key' => hash('sha256', 'uji-dec-0040-check'),
            'state' => NotificationIntent::STATE_DEFERRED,
            'scheduled_for' => now(),
            'deferred_for_quiet_hours' => true,
            'security_classification' => MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION,
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_refuses_an_unrecognised_security_classification(): void
    {
        $this->atJakarta('12:00');
        $s = $this->trackingScenario('otp-check-unknown-class');

        $this->expectException(\Illuminate\Database\QueryException::class);

        // A second exempt class needs a migration and, per DEC-0040's supersession
        // policy, its own decision record. It cannot be created by writing a string.
        DB::table('notification_intents')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $s['context']->tenantId(),
            'outlet_id' => $s['outlet_id'],
            'order_id' => $s['order']->id,
            'customer_id' => $s['customer_id'],
            'event_type' => 'order.received',
            'template_key' => NotificationTemplate::ORDER_RECEIVED,
            'template_version' => 1,
            'category' => NotificationIntent::CATEGORY_TRANSACTIONAL,
            'channel' => 'whatsapp',
            'recipient_normalized' => '6281200000000',
            'dedup_key' => hash('sha256', 'uji-dec-0040-unknown-class'),
            'state' => NotificationIntent::STATE_PENDING,
            'scheduled_for' => now(),
            'deferred_for_quiet_hours' => false,
            'security_classification' => 'URGENT',
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =====================================================================
    // Provider truthfulness — the exemption changes nothing here either
    // =====================================================================

    public function test_an_unavailable_provider_is_reported_as_a_failure_not_as_delivery(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-provider-unavailable');

        $this->provider->willBeUnavailable();

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        // Not SENT, not "prepared", not anything that reads like delivery. The
        // manual deep-link fallback is deliberately NOT offered for an OTP: handing
        // a staff member a link containing a customer's code defeats the code.
        $this->assertSame(NotificationIntent::STATE_FAILED_PERMANENT, $state);
        $this->assertSame([], $this->provider->sent);

        $intent = $this->intentFor($s['context']->tenantId());
        $this->assertNotNull($intent);
        $this->assertSame('provider_unavailable', $intent->failure_code);
        $this->assertNull($intent->accepted_at,
            'An acceptance timestamp on an unsent message would be a fabricated delivery claim.');
    }

    public function test_a_rejecting_provider_is_reported_as_a_failure(): void
    {
        $this->atJakarta('23:00');
        $s = $this->trackingScenario('otp-provider-rejects');

        $this->provider->willReturn(ProviderResult::REJECTED);

        $state = $this->messenger->send($s['order'], $this->code(), OtpDispatchOrigin::CustomerRequest);

        $this->assertSame(NotificationIntent::STATE_FAILED_PERMANENT, $state);

        $intent = $this->intentFor($s['context']->tenantId());
        $this->assertNotNull($intent);
        $this->assertNull($intent->accepted_at,
            'A rejected message must never carry an acceptance timestamp (Rule 01).');
    }
}
