<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OutboundMessage;
use App\Modules\Notification\Models\NotificationAttempt;
use App\Modules\Notification\Models\NotificationIntent;
use App\Modules\Notification\Templates\NotificationTemplate;
use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Http\CorrelationId;
use App\Modules\Tracking\Support\PublicMask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * DELIVERING AN OTP — the one message that cannot go through the ordinary outbox.
 *
 * WHY THIS CLASS EXISTS AT ALL
 * ----------------------------
 * Every other notification is enqueued and rendered later, from the template plus
 * the order. An OTP cannot be: its body contains the code, and the code must never
 * be persisted anywhere (only its hash is, on the challenge row — NOT-016, Rule 03
 * hard rule 20). Storing an intent and rendering the body at dispatch would require
 * storing the code. Storing the rendered body would store the code. Both are
 * forbidden.
 *
 * So this path renders and sends SYNCHRONOUSLY, holding the code in memory for the
 * duration of one call, and records an intent + attempt that contain everything
 * EXCEPT the code. The intent proves a message was attempted, to whom, and with
 * what outcome; nothing durable can reconstruct the code.
 *
 * WHAT IT STILL HONOURS — ALL OF IT
 * ---------------------------------
 * Template-derived category, consent, opt-out, dedup, quiet hours, and the
 * account-takeover rule (the TRACKING_OTP template carries no tracking link,
 * TRK-029/NOT-014). Being synchronous buys it no exemptions.
 *
 * QUIET HOURS AND THE OPEN QUESTION (OQ-018)
 * ------------------------------------------
 * Master Source §14.1 rule 6 holds NON-URGENT messages until quiet hours end, and
 * §14.2's catalogue contains no OTP entry — so whether a customer-initiated OTP is
 * "urgent" is UNDECIDED. This implementation therefore takes the conservative
 * reading and DEFERS, meaning no OTP is sent inside 20.00–08.00 outlet local time.
 *
 * The consequence is stated rather than hidden: because a challenge lives five
 * minutes, a deferred OTP is in practice an OTP that is not sent, so the FR-091
 * sensitive-action flow is unavailable during quiet hours. That is a real usability
 * limitation and it is recorded as OQ-018 for the repository owner. It is NOT
 * resolved here by inventing an "urgent" exception: NOT-022 permits one only where
 * the Master Source or an accepted decision record grants it, and neither does
 * (Rule 00 hard rule 6).
 *
 * NEVER THROWS. FR-099 applies here as everywhere: an OTP that cannot be messaged
 * does not break the challenge, the portal, or the order.
 */
class OtpMessenger
{
    public function __construct(private readonly NotificationProvider $provider)
    {
    }

    /**
     * Send an OTP for an order. Returns the resulting intent state, or null.
     *
     * The `$code` parameter is the ONLY place a plaintext OTP enters this module,
     * it is used exactly once to render the body, and it is never assigned to a
     * property, logged, or written to any row.
     */
    public function send(Order $order, string $code): ?string
    {
        try {
            return $this->deliver($order, $code);
        } catch (Throwable $e) {
            Log::warning('notification.otp_delivery_failed', [
                'tenant_id' => $order->tenant_id,
                'order_id' => $order->id,
                // The exception CLASS only — never its message, which can carry a
                // URL, a credential, or the rendered body.
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function deliver(Order $order, string $code): ?string
    {
        $template = NotificationTemplate::TRACKING_OTP;

        // Refuses a template that would carry an OTP and a tracking link together.
        NotificationTemplate::assertContentSafety($template);

        $outlet = Outlet::query()->forTenant($order->tenant_id)->find($order->outlet_id);
        $customer = Customer::query()->forTenant($order->tenant_id)->find($order->customer_id);

        if ($outlet === null) {
            return null;
        }

        $destination = NotificationIntentService::normaliseDestination($customer?->phone_normalized);
        $policy = SendPolicy::evaluate($template, $customer, $destination);

        $now = Carbon::now('UTC');
        $quiet = QuietHours::isQuiet($outlet, $now);
        $scheduledFor = $quiet ? QuietHours::nextPermitted($outlet, $now) : $now;

        $state = match (true) {
            ! $policy['allowed'] => NotificationIntent::STATE_SUPPRESSED,
            // Conservative reading of §14.1 rule 6. See the class docblock and
            // OQ-018 — this is a recorded open question, not a settled decision.
            $quiet => NotificationIntent::STATE_DEFERRED,
            default => NotificationIntent::STATE_PENDING,
        };

        $intent = $this->recordIntent(
            $order,
            $template,
            $destination,
            $state,
            $policy['allowed'] ? null : $policy['reason'],
            $scheduledFor,
            $quiet,
        );

        if ($intent === null || $state !== NotificationIntent::STATE_PENDING) {
            // Suppressed or deferred: nothing is sent, and nothing claims it was.
            return $state;
        }

        if (! $this->provider->isAvailable()) {
            // No automated channel. The manual fallback is NOT offered for an OTP:
            // handing a staff member a link containing a customer's verification
            // code would defeat the point of the code entirely.
            $this->recordAttempt($intent, 'unavailable', 'provider_unavailable',
                'Tidak ada penyedia pesan otomatis; kode verifikasi tidak dikirim.');

            $intent->forceFill([
                'state' => NotificationIntent::STATE_FAILED_PERMANENT,
                'failure_code' => 'provider_unavailable',
                'provider_key' => $this->provider->key(),
            ])->save();

            return NotificationIntent::STATE_FAILED_PERMANENT;
        }

        // Rendered here, in memory, and handed straight to the provider.
        $body = NotificationTemplate::render($template, [
            'otp_code' => $code,
            'customer_name' => PublicMask::name($customer?->name),
        ]);

        $result = $this->provider->send(new OutboundMessage(
            destination: $destination,
            body: $body,
            templateKey: $template,
            category: NotificationIntent::CATEGORY_TRANSACTIONAL,
            correlationId: $this->correlationId(),
        ));

        // $body held the code. Drop the reference before anything else runs.
        unset($body);

        $this->recordAttempt(
            $intent,
            $result->outcome,
            $result->failureCode,
            // The provider's first-party detail. Never the body.
            $result->detail,
        );

        $intent->forceFill($result->isAccepted()
            ? [
                'state' => NotificationIntent::STATE_SENT,
                'accepted_at' => now(),
                'provider_key' => $this->provider->key(),
                'provider_reference' => $result->providerReference,
            ]
            : [
                'state' => NotificationIntent::STATE_FAILED_PERMANENT,
                'failure_code' => $result->failureCode,
                'provider_key' => $this->provider->key(),
            ])->save();

        $intent->forceFill(['attempt_count' => 1])->save();

        return (string) $intent->fresh()?->state;
    }

    /**
     * Record the intent, deduplicated on (recipient, event, order, window).
     *
     * Dedup applies to OTPs too: a customer double-tapping "kirim kode" inside the
     * same window gets one message, not two (FR-098). The resend cooldown in
     * `TrackingOtpService` is the first line; this is the structural backstop.
     */
    private function recordIntent(
        Order $order,
        string $template,
        string $destination,
        string $state,
        ?string $suppressionReason,
        Carbon $scheduledFor,
        bool $quiet,
    ): ?NotificationIntent {
        $eventType = 'tracking.otp.requested';
        $dedupKey = NotificationIntentService::dedupKey($destination, $eventType, $order->id, $scheduledFor);

        \Illuminate\Support\Facades\DB::table('notification_intents')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $order->tenant_id,
            'outlet_id' => $order->outlet_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'event_type' => $eventType,
            'template_key' => $template,
            'template_version' => NotificationTemplate::versionFor($template),
            'category' => NotificationTemplate::categoryFor($template),
            'channel' => 'whatsapp',
            'recipient_normalized' => $destination,
            'dedup_key' => $dedupKey,
            'state' => $state,
            'suppression_reason' => $suppressionReason,
            'scheduled_for' => $scheduledFor,
            'deferred_for_quiet_hours' => $quiet,
            'attempt_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return NotificationIntent::query()
            ->forTenant($order->tenant_id)
            ->where('dedup_key', $dedupKey)
            ->first();
    }

    private function recordAttempt(
        NotificationIntent $intent,
        string $outcome,
        ?string $failureCode,
        ?string $detail,
    ): void {
        NotificationAttempt::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $intent->tenant_id,
            'intent_id' => $intent->id,
            'attempt_number' => 1,
            'provider_key' => $this->provider->key(),
            'outcome' => $outcome,
            'failure_code' => $failureCode,
            'detail' => $detail,
            'occurred_at' => now(),
        ]);
    }

    private function correlationId(): string
    {
        return app()->bound(CorrelationId::class)
            ? app(CorrelationId::class)->value
            : (string) Str::uuid();
    }
}
