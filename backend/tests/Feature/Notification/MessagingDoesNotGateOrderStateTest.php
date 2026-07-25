<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\ProviderResult;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Providers\FakeNotificationProvider;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Notification\Services\NotificationIntentService;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Payments\Support\OrderBalance;
use App\Modules\Production\Models\QualityControlInspection;
use App\Modules\Production\Services\ProductionReadyService;
use App\Modules\Production\Services\ProductionRegistry;
use App\Modules\Production\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTrackingScenario;
use Tests\TestCase;

/**
 * STEP 7 — FR-099: A MESSAGING FAILURE NEVER ALTERS AN ORDER'S STATE.
 *
 * This is the hard gate of the notification subsystem, and it is asserted the only
 * way that means anything: by producing every provider failure mode there is and
 * then checking the order, the ledger, and the production state are untouched.
 *
 * The structural assertions at the end matter as much as the behavioural ones. A
 * suite that proves today's code is decoupled proves nothing about tomorrow's, so
 * the absence of any path from a notification failure back into business state is
 * checked by reading the source tree (NOT-001, NOT-027, NOT-029).
 */
final class MessagingDoesNotGateOrderStateTest extends TestCase
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

        // Midday WIB, so quiet hours are not what stops a send.
        Carbon::setTestNow(Carbon::parse('2026-07-25 05:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{state: string, total: int, balance: array<string, mixed>} */
    private function orderSnapshot(string $orderId): array
    {
        $row = DB::table('orders')->where('id', $orderId)->first();

        return [
            'state' => (string) $row->status,
            'total' => (int) $row->total_rupiah,
            'balance' => OrderBalance::for(\App\Modules\Ordering\Models\Order::query()->findOrFail($orderId)),
        ];
    }

    /**
     * Every provider failure mode there is, each against a fresh order.
     *
     * Written as an explicit loop rather than a data provider so the whole matrix
     * lives in one readable place, and so a new failure mode is added by adding a
     * line here rather than by remembering a separate provider method.
     */
    public function test_the_order_is_unchanged_under_every_provider_failure(): void
    {
        $modes = [
            'timeout' => ProviderResult::TIMEOUT,
            'provider rejected (4xx-class)' => ProviderResult::REJECTED,
            'provider error (5xx-class)' => ProviderResult::ERROR,
            'malformed response' => ProviderResult::MALFORMED,
            'provider unavailable' => ProviderResult::UNAVAILABLE,
        ];

        foreach ($modes as $label => $outcome) {
            $s = $this->trackingScenario('fr099-'.mb_substr(md5($outcome), 0, 8));
            $before = $this->orderSnapshot($s['order']->id);

            $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
            $this->provider->reset()->willReturn($outcome);

            (new NotificationDispatcher($this->provider))->dispatch($intent);

            $this->assertSame($before, $this->orderSnapshot($s['order']->id),
                "The order changed after a {$label}. Notification is a side effect, "
                .'never a dependency (FR-099, NOT-001).');
        }
    }

    public function test_the_order_is_unchanged_when_no_credentials_exist_at_all(): void
    {
        $s = $this->trackingScenario('fr099-no-credentials');
        $before = $this->orderSnapshot($s['order']->id);

        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $this->provider->willBeUnavailable();

        (new NotificationDispatcher($this->provider))->dispatch($intent);

        $this->assertSame($before, $this->orderSnapshot($s['order']->id));
    }

    public function test_enqueue_never_throws_into_a_business_caller(): void
    {
        $s = $this->trackingScenario('fr099-never-throws');
        $before = $this->orderSnapshot($s['order']->id);

        // An unknown template throws inside the service. `enqueue` must catch it
        // and return null: a caller that could be interrupted by a notification
        // problem is a caller whose business outcome depends on messaging.
        $result = $this->intents->enqueue($s['order'], 'template_yang_tidak_ada', 'order.received');

        $this->assertNull($result);
        $this->assertSame($before, $this->orderSnapshot($s['order']->id));
    }

    public function test_a_dispatcher_exception_is_absorbed_and_recorded_rather_than_propagated(): void
    {
        $s = $this->trackingScenario('fr099-dispatcher-throws');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');
        $before = $this->orderSnapshot($s['order']->id);

        // A provider that violates its own contract by throwing.
        $throwing = new class implements NotificationProvider
        {
            public function key(): string
            {
                return 'provider_yang_melempar';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(\App\Modules\Notification\Contracts\OutboundMessage $message): ProviderResult
            {
                throw new \RuntimeException('kegagalan penyedia yang tidak terduga');
            }
        };

        $result = (new NotificationDispatcher($throwing))->dispatch($intent);

        $this->assertNotSame(NotificationIntent::STATE_SENT, $result->state);
        $this->assertSame($before, $this->orderSnapshot($s['order']->id),
            'FR-099 is only as strong as its weakest caller; the dispatcher must never rethrow.');
    }

    public function test_the_payment_ledger_is_untouched_by_a_messaging_failure(): void
    {
        $s = $this->trackingScenario('fr099-ledger');

        $paymentsBefore = DB::table('payments')->count();
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::PAYMENT_RECEIVED, 'payment.recorded');

        $this->provider->willReturn(ProviderResult::ERROR);
        (new NotificationDispatcher($this->provider))->dispatch($intent);

        $this->assertSame($paymentsBefore, DB::table('payments')->count(),
            'The notification subsystem never writes to the append-only ledger (Rule 04).');
    }

    public function test_production_state_and_the_readiness_anchor_survive_a_messaging_failure(): void
    {
        $s = $this->trackingScenario('fr099-production');

        $registry = new ProductionRegistry();
        $qc = new QualityControlService();
        $ready = app(ProductionReadyService::class);

        $job = $registry->createJobForOrder($s['context'], $s['order'], $this->ref());
        $job = $registry->startStage($s['context'], $job->id, (int) $job->version, $this->ref(), 'FINISHING');
        $job = $registry->sendToQualityControl($s['context'], $job->id, (int) $job->version, $this->ref());
        $qc->recordInspection($s['context'], $job->id, QualityControlInspection::VERDICT_PASSED, (int) $job->version, $this->ref());
        $ready->markReady($s['context'], $job->fresh()->id, $this->ref());

        $anchorBefore = DB::table('production_ready_events')
            ->where('order_id', $s['order']->id)->value('occurred_at');
        $jobStateBefore = DB::table('production_jobs')->where('id', $job->id)->value('state');

        $intent = $this->intents->enqueue(
            $s['order']->fresh(), NotificationTemplate::ORDER_READY_FOR_PICKUP, 'order.ready'
        );
        $this->provider->willReturn(ProviderResult::TIMEOUT);
        (new NotificationDispatcher($this->provider))->dispatch($intent);

        $this->assertSame($anchorBefore, DB::table('production_ready_events')
            ->where('order_id', $s['order']->id)->value('occurred_at'),
            'The immutable first-ready anchor must be untouched by messaging (Rule 10, FR-077).');
        $this->assertSame($jobStateBefore, DB::table('production_jobs')->where('id', $job->id)->value('state'));
    }

    public function test_a_failed_send_stays_visible_rather_than_disappearing(): void
    {
        $s = $this->trackingScenario('fr099-visible');
        $intent = $this->intents->enqueue($s['order'], NotificationTemplate::ORDER_RECEIVED, 'order.received');

        $this->provider->willReturn(ProviderResult::TIMEOUT);
        $result = (new NotificationDispatcher($this->provider))->dispatch($intent);

        // NOT-018: not retried forever, and NOT silently discarded.
        $this->assertNotNull(NotificationIntent::query()->find($result->id));
        $this->assertNotNull($result->failure_code);
        $this->assertGreaterThan(0, DB::table('notification_attempts')
            ->where('intent_id', $result->id)->count());
    }

    // =====================================================================
    // The structural half: no path exists from messaging back to business state
    // =====================================================================

    public function test_no_business_module_subscribes_to_a_notification_outcome(): void
    {
        // NOT-001 / NOT-027: every arrow into Notification is inbound. A future
        // subscriber wiring a notification failure into an order, a payment, or a
        // production job is a design rejection, and this is where it is rejected.
        foreach (['Ordering', 'Payments', 'Production'] as $module) {
            $root = app_path('Modules/'.$module);

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());

                $this->assertStringNotContainsString('Modules\\Notification', $source,
                    basename($file->getPathname()).' imports the Notification module. Business '
                    .'state must never depend on messaging (FR-099, NOT-001, NOT-029).');
            }
        }
    }

    public function test_the_notification_module_writes_to_no_business_table(): void
    {
        $root = app_path('Modules/Notification');
        $businessTables = ['orders', 'order_lines', 'payments', 'production_jobs', 'production_ready_events'];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach ($businessTables as $table) {
                // Reading is legitimate (the dispatcher needs the order number);
                // WRITING is not, and a table write goes through these two shapes.
                $this->assertStringNotContainsString("DB::table('{$table}')->update", $source);
                $this->assertStringNotContainsString("DB::table('{$table}')->insert", $source);
            }
        }
    }

    public function test_the_enqueue_helper_defers_until_after_the_business_transaction_commits(): void
    {
        $source = (string) file_get_contents(
            app_path('Modules/Notification/Services/NotificationIntentService.php')
        );

        // The ordering guarantee itself: by the time an intent is written, the
        // business transaction has already committed, so there is nothing left to
        // roll back.
        $this->assertStringContainsString('DB::afterCommit', $source,
            'enqueueAfterCommit must register its work after commit — an enqueue inside '
            .'a business transaction could roll that transaction back (FR-099).');
    }
}
