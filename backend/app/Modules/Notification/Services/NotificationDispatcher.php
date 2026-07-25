<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Contracts\ProviderResult;
use App\Modules\Notification\Models\NotificationAttempt;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\CorrelationId;
use App\Modules\Tracking\Support\PublicMask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * DISPATCH — one attempt against the configured provider (FR-094, FR-099).
 *
 * Runs OUTSIDE any business transaction, always. Nothing this class does can roll
 * back an order, a payment, or a production transition, because by the time it runs
 * those have long since committed.
 *
 * WHAT AN ATTEMPT RECORDS, AND WHAT IT REFUSES TO RECORD
 * -----------------------------------------------------
 * Every attempt appends a row: provider, outcome, first-party failure code, redacted
 * detail. `SENT` is written only on a provider `accepted`, and the database CHECK
 * `(state = 'SENT') = (accepted_at IS NOT NULL)` means a fabricated success cannot
 * even be stored. No attempt row ever holds a credential, an OTP, a token, or a full
 * address.
 *
 * BOUNDED RETRY (NOT-017, NOT-018)
 * --------------------------------
 * Five attempts with exponential backoff, then `FAILED_PERMANENT` and VISIBLE. Not
 * retried forever, not silently discarded. A `rejected` outcome is permanent
 * immediately — the provider understood and refused, so repeating it changes
 * nothing and costs the tenant money.
 *
 * QUIET HOURS ARE RE-CHECKED AT DISPATCH, NOT ONLY AT ENQUEUE
 * -----------------------------------------------------------
 * An intent may have been queued at 19:58 and picked up at 20:03 by a worker that
 * was busy. Checking only at enqueue would send it inside the window. So the check
 * happens again here, and a message that has become quiet is re-deferred rather
 * than sent (FR-097, NOT-021).
 */
class NotificationDispatcher
{
    public function __construct(private readonly NotificationProvider $provider)
    {
    }

    /**
     * Attempt one dispatch. Returns the intent in its resulting state.
     *
     * NEVER THROWS. A dispatcher that threw would push a messaging failure into
     * whatever invoked it — a queue worker, a retry endpoint, a console command —
     * and FR-099's guarantee is only as good as its weakest caller.
     */
    public function dispatch(NotificationIntent $intent): NotificationIntent
    {
        try {
            return $this->attempt($intent);
        } catch (Throwable $e) {
            $this->recordAttempt($intent, ProviderResult::error('dispatcher_error', $e::class));

            return $this->applyFailure($intent, ProviderResult::error('dispatcher_error', $e::class));
        }
    }

    private function attempt(NotificationIntent $intent): NotificationIntent
    {
        if ($intent->isTerminal()) {
            // Already SENT, SUPPRESSED, or permanently failed. Dispatching again
            // would be the duplicate FR-098 forbids.
            return $intent;
        }

        $order = $intent->order_id === null
            ? null
            : Order::query()->forTenant($intent->tenant_id)->find($intent->order_id);
        $outlet = Outlet::query()->forTenant($intent->tenant_id)->find($intent->outlet_id);

        if ($outlet === null || $order === null) {
            return $this->applyFailure($intent, ProviderResult::error('intent_context_missing'));
        }

        // Re-check quiet hours: the window may have closed since enqueue.
        $now = Carbon::now('UTC');
        if (QuietHours::isQuiet($outlet, $now)) {
            $intent->forceFill([
                'state' => NotificationIntent::STATE_DEFERRED,
                'deferred_for_quiet_hours' => true,
                'scheduled_for' => QuietHours::nextPermitted($outlet, $now),
            ])->save();

            return $intent->refresh();
        }

        // Re-evaluate consent at SEND time, not queue time (NOT-005). A customer who
        // opted out between enqueue and dispatch has still opted out.
        $customer = $intent->customer_id === null
            ? null
            : Customer::query()->forTenant($intent->tenant_id)->find($intent->customer_id);

        $policy = SendPolicy::evaluate($intent->template_key, $customer, (string) $intent->recipient_normalized);

        if (! $policy['allowed']) {
            $intent->forceFill([
                'state' => NotificationIntent::STATE_SUPPRESSED,
                'suppression_reason' => $policy['reason'],
            ])->save();

            return $intent->refresh();
        }

        if (! $this->provider->isAvailable()) {
            $result = ProviderResult::unavailable();
            $this->recordAttempt($intent, $result);

            return $this->applyFailure($intent, $result);
        }

        $message = new OutboundMessage(
            destination: (string) $intent->recipient_normalized,
            body: $this->renderBody($intent, $order, $outlet, $customer),
            templateKey: (string) $intent->template_key,
            category: (string) $intent->category,
            correlationId: $this->correlationId(),
        );

        $intent->forceFill([
            'state' => NotificationIntent::STATE_SENDING,
            'attempt_count' => (int) $intent->attempt_count + 1,
            'last_attempted_at' => now(),
        ])->save();

        $result = $this->provider->send($message);
        $this->recordAttempt($intent, $result);

        if ($result->isAccepted()) {
            $intent->forceFill([
                // "SENT" = the provider ACCEPTED it. Never rendered as delivered.
                'state' => NotificationIntent::STATE_SENT,
                'accepted_at' => now(),
                'provider_key' => $this->provider->key(),
                'provider_reference' => $result->providerReference,
                'failure_code' => null,
            ])->save();

            return $intent->refresh();
        }

        return $this->applyFailure($intent, $result);
    }

    /**
     * Render the body from the template.
     *
     * Every variable is masked or safe by construction. The customer's name is
     * masked, the outlet is named, the order number is public by design (FR-053 —
     * it grants no access). There is NO address variable, no phone variable, and no
     * internal identifier available to any template (NOT-015).
     *
     * `:tracking_url` is deliberately absent here. Only the caller that MINTED a
     * token holds the plaintext, and it hands the finished URL in through the
     * intent's own render path — this method never has access to a plaintext token
     * and so can never place one in a body it should not be in.
     */
    private function renderBody(
        NotificationIntent $intent,
        Order $order,
        Outlet $outlet,
        ?Customer $customer,
    ): string {
        return NotificationTemplate::render((string) $intent->template_key, [
            'customer_name' => PublicMask::name($customer?->name),
            'order_number' => (string) $order->order_number,
            'outlet_name' => (string) $outlet->name,
        ]);
    }

    private function applyFailure(NotificationIntent $intent, ProviderResult $result): NotificationIntent
    {
        $exhausted = (int) $intent->attempt_count >= NotificationIntent::MAX_ATTEMPTS;

        // A rejection is permanent immediately: the provider understood the request
        // and refused it, so a retry produces the same refusal at the tenant's cost.
        $permanent = $exhausted || ! $result->isRetryable();

        $next = $intent->scheduled_for;
        if (! $permanent) {
            $index = min((int) $intent->attempt_count, count(NotificationIntent::BACKOFF_SECONDS) - 1);
            $next = now()->addSeconds(NotificationIntent::BACKOFF_SECONDS[max($index - 1, 0)]);
        }

        $intent->forceFill([
            'state' => $permanent
                ? NotificationIntent::STATE_FAILED_PERMANENT
                : NotificationIntent::STATE_FAILED_RETRYABLE,
            'failure_code' => $result->failureCode,
            'provider_key' => $this->provider->key(),
            'scheduled_for' => $next,
        ])->save();

        return $intent->refresh();
    }

    private function recordAttempt(NotificationIntent $intent, ProviderResult $result): void
    {
        NotificationAttempt::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $intent->tenant_id,
            'intent_id' => $intent->id,
            'attempt_number' => max((int) $intent->attempt_count, 1),
            'provider_key' => $this->provider->key(),
            'outcome' => $result->outcome,
            'failure_code' => $result->failureCode,
            // First-party, already-redacted text. Never a vendor error body.
            'detail' => $result->detail,
            'occurred_at' => now(),
        ]);
    }

    private function correlationId(): string
    {
        return app()->bound(CorrelationId::class)
            ? app(CorrelationId::class)->value
            : (string) Str::uuid();
    }

    /**
     * Intents that are due for an attempt right now, tenant-scoped.
     *
     * Ordered by `scheduled_for` so a deferred message is not overtaken by a newer
     * one. `DB::raw` is deliberately absent — the query is a plain tenant-scoped
     * builder, so the scoping cannot be lost to a hand-written fragment.
     *
     * @return \Illuminate\Support\Collection<int, NotificationIntent>
     */
    public static function due(string $tenantId, int $limit = 50)
    {
        return NotificationIntent::query()
            ->forTenant($tenantId)
            ->whereIn('state', [
                NotificationIntent::STATE_PENDING,
                NotificationIntent::STATE_DEFERRED,
                NotificationIntent::STATE_FAILED_RETRYABLE,
            ])
            ->where('attempt_count', '<', NotificationIntent::MAX_ATTEMPTS)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();
    }
}
