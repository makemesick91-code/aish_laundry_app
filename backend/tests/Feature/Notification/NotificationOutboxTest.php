<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\ProviderResult;
use App\Modules\Notification\Models\NotificationAttempt;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Notification\Services\NotificationIntentService;
use App\Modules\Notification\Templates\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 · UNIT E — THE TRANSACTIONAL OUTBOX (FR-098, FR-099).
 *
 * Dedup and bounded retry, against live PostgreSQL. The dedup tests matter most:
 * FR-098 requires exactly-once "across retries, queue replays, and scheduler
 * restarts", which is precisely the set of cases a check-then-insert would miss.
 */
final class NotificationOutboxTest extends TestCase
{
    use BuildsTrackingScenario;
    use RefreshDatabase;

    private NotificationIntentService $intents;

    private FakeNotificationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intents = app(NotificationIntentService::class);

        $provider = app(NotificationProvider::class);
        $this->assertInstanceOf(FakeNotificationProvider::class, $provider,
            'The testing environment must resolve the fake provider, never a real one.');
        $this->provider = $provider->reset();

        // Fix the clock OUTSIDE quiet hours so these tests exercise dedup and
        // retry rather than deferral. Quiet hours have their own suite.
        Carbon::setTestNow(Carbon::parse('2026-07-25 05:00:00', 'UTC')); // 12:00 WIB
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dispatcher(): NotificationDispatcher
    {
        return new NotificationDispatcher($this->provider);
    }

    // =====================================================================
    // FR-098 — deduplication
    // =====================================================================

    public function test_the_same_event_twice_produces_exactly_one_intent(): void
    {
        $s = $this->trackingScenario('outbox-dedup');

        $first = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $second = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id,
            'A duplicate trigger must return the ORIGINAL intent, not create a second.');

        $this->assertSame(1, NotificationIntent::query()
            ->forTenant($s['context']->tenantId())
            ->where('event_type', 'order.received')
            ->count());
    }

    public function test_dedup_survives_a_replay_after_the_first_intent_was_already_sent(): void
    {
        $s = $this->trackingScenario('outbox-dedup-after-send');

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->dispatcher()->dispatch($intent);

        $this->assertSame(NotificationIntent::STATE_SENT, $intent->fresh()->state);

        // A queue replay or a scheduler restart re-triggers the same event.
        $replay = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->assertSame($intent->id, $replay->id);
        $this->assertCount(1, $this->provider->sent,
            'A replay after a successful send must not message the customer twice (FR-098).');
    }

    public function test_different_events_for_the_same_order_are_not_deduplicated_together(): void
    {
        $s = $this->trackingScenario('outbox-distinct-events');

        $a = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $b = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_READY_FOR_PICKUP, 'order.ready');

        $this->assertNotSame($a->id, $b->id,
            'Dedup is keyed on the EVENT as well as the order; two different events are two messages.');
    }

    public function test_the_dedup_key_is_a_digest_and_never_holds_the_phone_number(): void
    {
        $s = $this->trackingScenario('outbox-dedup-key');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->assertSame(64, strlen($intent->dedup_key));
        $this->assertStringNotContainsString('6281200000000', $intent->dedup_key,
            'The outbox is queryable by anyone with database access; a plaintext '
            .'recipient in an index is personal data sitting in an unexpected place.');
    }

    public function test_the_unique_constraint_refuses_a_second_row_with_the_same_key(): void
    {
        $s = $this->trackingScenario('outbox-dedup-structural');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        // The structural backstop: even a direct SQL insert cannot duplicate it.
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('notification_intents')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $intent->tenant_id,
            'outlet_id' => $intent->outlet_id,
            'order_id' => $intent->order_id,
            'event_type' => $intent->event_type,
            'template_key' => $intent->template_key,
            'template_version' => 1,
            'category' => $intent->category,
            'channel' => 'whatsapp',
            'recipient_normalized' => $intent->recipient_normalized,
            'dedup_key' => $intent->dedup_key,
            'state' => 'PENDING',
            'scheduled_for' => now(),
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =====================================================================
    // Dispatch outcomes and bounded retry
    // =====================================================================

    public function test_an_accepted_send_records_sent_and_an_acceptance_timestamp(): void
    {
        $s = $this->trackingScenario('outbox-accepted');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $result = $this->dispatcher()->dispatch($intent);

        $this->assertSame(NotificationIntent::STATE_SENT, $result->state);
        $this->assertNotNull($result->accepted_at);
        $this->assertSame('fake_provider', $result->provider_key);
        $this->assertNotNull($result->provider_reference);

        $this->assertSame(1, NotificationAttempt::query()
            ->forTenant($intent->tenant_id)->where('intent_id', $intent->id)->count());
    }

    public function test_a_rejected_send_is_permanent_immediately_and_is_not_retried(): void
    {
        $s = $this->trackingScenario('outbox-rejected');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willReturn(ProviderResult::REJECTED);
        $result = $this->dispatcher()->dispatch($intent);

        // The provider understood and refused. Repeating it produces the same
        // refusal and burns the tenant's message allowance.
        $this->assertSame(NotificationIntent::STATE_FAILED_PERMANENT, $result->state);
        $this->assertFalse($result->canRetry());
    }

    public function test_a_timeout_is_retryable_and_backs_off(): void
    {
        $s = $this->trackingScenario('outbox-timeout');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willReturn(ProviderResult::TIMEOUT);
        $result = $this->dispatcher()->dispatch($intent);

        $this->assertSame(NotificationIntent::STATE_FAILED_RETRYABLE, $result->state);
        $this->assertTrue($result->canRetry());
        $this->assertSame('provider_timeout', $result->failure_code);
        $this->assertTrue($result->scheduled_for->greaterThan(now()),
            'A retry must back off rather than hammer a struggling provider.');
    }

    public function test_retry_is_bounded_and_ends_visible_rather_than_silent(): void
    {
        $s = $this->trackingScenario('outbox-bounded');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willReturn(ProviderResult::TIMEOUT);

        for ($i = 0; $i < NotificationIntent::MAX_ATTEMPTS + 2; $i++) {
            $intent = $this->dispatcher()->dispatch($intent->fresh());
            // The backoff would otherwise make the intent not-yet-due.
            DB::table('notification_intents')->where('id', $intent->id)
                ->update(['scheduled_for' => now()->subMinute()]);
        }

        $final = $intent->fresh();

        // Not retried forever (NOT-018), and not silently discarded: the row and
        // every attempt remain, visible (NOT-017).
        $this->assertSame(NotificationIntent::STATE_FAILED_PERMANENT, $final->state);
        $this->assertLessThanOrEqual(NotificationIntent::MAX_ATTEMPTS, (int) $final->attempt_count);
        $this->assertGreaterThan(0, NotificationAttempt::query()
            ->forTenant($final->tenant_id)->where('intent_id', $final->id)->count());
    }

    public function test_a_malformed_provider_response_is_handled_as_an_outcome_not_a_crash(): void
    {
        $s = $this->trackingScenario('outbox-malformed');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willReturn(ProviderResult::MALFORMED);
        $result = $this->dispatcher()->dispatch($intent);

        $this->assertSame(NotificationIntent::STATE_FAILED_RETRYABLE, $result->state);
        $this->assertSame('provider_malformed_response', $result->failure_code);
    }

    public function test_an_unavailable_provider_never_claims_a_send(): void
    {
        $s = $this->trackingScenario('outbox-unavailable');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willBeUnavailable();
        $result = $this->dispatcher()->dispatch($intent);

        $this->assertNotSame(NotificationIntent::STATE_SENT, $result->state);
        $this->assertNull($result->accepted_at);
        $this->assertSame([], $this->provider->sent, 'Nothing may be handed to an unavailable provider.');
    }

    public function test_a_terminal_intent_is_never_dispatched_again(): void
    {
        $s = $this->trackingScenario('outbox-terminal');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->dispatcher()->dispatch($intent);
        $this->assertCount(1, $this->provider->sent);

        $this->dispatcher()->dispatch($intent->fresh());

        $this->assertCount(1, $this->provider->sent,
            'Dispatching an already-SENT intent again would be the duplicate FR-098 forbids.');
    }

    public function test_an_attempt_row_never_records_a_credential_or_a_message_body(): void
    {
        $s = $this->trackingScenario('outbox-attempt-content');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->dispatcher()->dispatch($intent);

        $attempts = NotificationAttempt::query()
            ->forTenant($intent->tenant_id)->where('intent_id', $intent->id)->get();

        foreach ($attempts as $attempt) {
            $encoded = (string) json_encode($attempt->toArray());
            $this->assertStringNotContainsString('Budi', $encoded);
            $this->assertStringNotContainsString('6281200000000', $encoded);
            $this->assertStringNotContainsString('ALS-2026-000042', $encoded);
        }
    }

    public function test_due_returns_only_tenant_scoped_intents_that_are_actually_due(): void
    {
        $a = $this->trackingScenario('outbox-due-a');
        $b = $this->trackingScenario('outbox-due-b');

        $this->intents->enqueue($a['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->intents->enqueue($b['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $dueForA = NotificationDispatcher::due($a['context']->tenantId());

        $this->assertCount(1, $dueForA);
        $this->assertSame($a['context']->tenantId(), $dueForA->first()->tenant_id,
            'A dispatcher query that is not tenant-scoped would send one tenant\'s '
            .'messages under another tenant\'s context (Rule 02).');
    }
}
