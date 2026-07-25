<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\Outlet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * STEP 7 · UNIT E — ENQUEUEING AN INTENT (FR-096, FR-097, FR-098, FR-099).
 *
 * THE FR-099 GUARANTEE, AND WHERE IT ACTUALLY LIVES
 * -------------------------------------------------
 * "A messaging failure shall never cancel, block, or alter an order's state."
 *
 * Three things make that true here, and none of them is care:
 *
 *   1. `enqueueAfterCommit()` registers the work with `DB::afterCommit()`. The
 *      business transaction has already committed when the intent is written, so a
 *      failure writing it cannot roll anything back. There is nothing left to roll
 *      back.
 *   2. `enqueue()` CATCHES `Throwable` and returns null. It never propagates into a
 *      business caller. A notification table that is unreachable, a template that
 *      throws, a Redis outage — the order still stands.
 *   3. Dispatch happens elsewhere, later, outside any business transaction.
 *
 * `MessagingDoesNotGateOrderStateTest` proves the order is unchanged under provider
 * timeout, 4xx, 5xx, malformed response, absent credentials, and a dead queue.
 *
 * DEDUP IS A CONSTRAINT, NOT A CHECK (FR-098)
 * -------------------------------------------
 * A check-then-insert loses the race by definition under concurrent workers, and
 * FR-098 explicitly requires exactly-once "across retries, queue replays, and
 * scheduler restarts" — which is to say, exactly the concurrent cases a check would
 * miss. So the dedup key is a UNIQUE index and this service uses `insertOrIgnore`:
 * the second writer's insert affects zero rows and it returns the FIRST writer's
 * intent.
 */
class NotificationIntentService
{
    /**
     * Enqueue after the current transaction commits — the FR-099 ordering.
     *
     * Call this from business code. Never call `enqueue()` directly from inside a
     * business transaction: doing so would put a messaging write inside a financial
     * or order transaction, and a failure there WOULD roll the business change back.
     */
    public function enqueueAfterCommit(
        Order $order,
        string $templateKey,
        string $eventType,
        array $variables = [],
    ): void {
        DB::afterCommit(function () use ($order, $templateKey, $eventType, $variables): void {
            $this->enqueue($order, $templateKey, $eventType, $variables);
        });
    }

    /**
     * Create (or return the existing) intent for this (recipient, event, order, window).
     *
     * NEVER THROWS INTO A BUSINESS CALLER. Returns null on any failure, having
     * recorded it. This is deliberate and is the second half of FR-099: a caller
     * that could be interrupted by a notification problem is a caller whose business
     * outcome depends on messaging.
     */
    public function enqueue(
        Order $order,
        string $templateKey,
        string $eventType,
        array $variables = [],
    ): ?NotificationIntent {
        try {
            return $this->createIntent($order, $templateKey, $eventType, $variables);
        } catch (Throwable $e) {
            // Visible, but not fatal. The message is what failed, not the order.
            Log::warning('notification.enqueue_failed', [
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                'template_key' => $templateKey,
                'event_type' => $eventType,
                // The class only. A provider or database exception message can
                // carry connection strings and credentials (Rule 03 hard rule 20).
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function createIntent(
        Order $order,
        string $templateKey,
        string $eventType,
        array $variables,
    ): ?NotificationIntent {
        // Category comes from the TEMPLATE, never from a caller. A caller who wanted
        // to route marketing through a transactional path has nowhere to say so
        // (FR-096, NOT-024). An unknown template throws and is caught above.
        $category = NotificationTemplate::categoryFor($templateKey);
        NotificationTemplate::assertContentSafety($templateKey);

        $customer = Customer::query()->forTenant($order->tenant_id)->find($order->customer_id);
        $outlet = Outlet::query()->forTenant($order->tenant_id)->find($order->outlet_id);

        if ($outlet === null) {
            return null;
        }

        $destination = self::normaliseDestination($customer?->phone_normalized);

        $now = Carbon::now('UTC');
        $quiet = QuietHours::isQuiet($outlet, $now);
        $scheduledFor = $quiet ? QuietHours::nextPermitted($outlet, $now) : $now;

        // The window is the intended SEND window, so a message deferred by quiet
        // hours dedups against another triggering of the same event that would land
        // in the same window — which is what "intended send window" means in
        // NOT-002. Hour granularity: two triggers of the same event for the same
        // order within an hour are the same notification.
        $dedupKey = self::dedupKey(
            $destination,
            $eventType,
            $order->id,
            $scheduledFor,
        );

        $policy = SendPolicy::evaluate($templateKey, $customer, $destination);

        $state = match (true) {
            ! $policy['allowed'] => NotificationIntent::STATE_SUPPRESSED,
            $quiet => NotificationIntent::STATE_DEFERRED,
            default => NotificationIntent::STATE_PENDING,
        };

        $id = (string) Str::uuid();

        // insertOrIgnore: the UNIQUE (tenant_id, dedup_key) index decides. A second
        // writer affects zero rows and falls through to reading the first one's row.
        $inserted = DB::table('notification_intents')->insertOrIgnore([
            'id' => $id,
            'tenant_id' => $order->tenant_id,
            'outlet_id' => $order->outlet_id,
            'order_id' => $order->id,
            'customer_id' => $customer?->id,
            'event_type' => $eventType,
            'template_key' => $templateKey,
            'template_version' => NotificationTemplate::versionFor($templateKey),
            'category' => $category,
            'channel' => 'whatsapp',
            'recipient_normalized' => $destination,
            'dedup_key' => $dedupKey,
            'state' => $state,
            'suppression_reason' => $policy['allowed'] ? null : $policy['reason'],
            'scheduled_for' => $scheduledFor,
            'deferred_for_quiet_hours' => $quiet,
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $intent = NotificationIntent::query()
            ->forTenant($order->tenant_id)
            ->where('dedup_key', $dedupKey)
            ->first();

        if ($inserted === 0) {
            // A duplicate. The original intent stands, unchanged — this is FR-098
            // working, not an error. The caller receives the original.
            return $intent;
        }

        // Variables are rendered at DISPATCH time, not stored here: a stored body
        // would be a second copy of personal data sitting in the outbox, and the
        // rendered text can be rebuilt from the template and the order at any time.
        unset($variables);

        return $intent;
    }

    /**
     * The FR-098 identity: recipient + event + order + intended send window.
     *
     * Hashed so the stored key holds no phone number — the outbox is queryable by
     * anyone with database access, and a plaintext recipient in an index is personal
     * data sitting in an unexpected place (Rule 21).
     */
    public static function dedupKey(string $recipient, string $eventType, ?string $orderId, Carbon $window): string
    {
        return hash('sha256', implode('|', [
            $recipient,
            $eventType,
            $orderId ?? '-',
            $window->copy()->setTimezone('UTC')->format('Y-m-d\TH'),
        ]));
    }

    /**
     * E.164 digits, or an empty string when there is nothing usable.
     *
     * An empty destination is not an exception: it is a suppression reason
     * (`recipient_unreachable`), because a customer with no phone number recorded is
     * an ordinary state of the world and not a system failure.
     */
    public static function normaliseDestination(?string $phoneNormalized): string
    {
        return preg_replace('/\D+/', '', (string) $phoneNormalized) ?? '';
    }
}
