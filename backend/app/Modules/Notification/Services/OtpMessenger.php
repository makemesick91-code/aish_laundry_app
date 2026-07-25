<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\CustomerManagement\Models\Customer;
use App\Modules\Notification\Contracts\NotificationProvider;
use App\Modules\Notification\Contracts\OtpDispatchOrigin;
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
 * WHAT IT STILL HONOURS — ALL OF IT EXCEPT ONE THING
 * --------------------------------------------------
 * Template-derived category, consent, opt-out, dedup, rate limiting and the resend
 * cooldown (in `TrackingOtpService`), and the account-takeover rule (the
 * TRACKING_OTP template carries no tracking link, TRK-029/NOT-014). Being
 * synchronous buys it no exemptions. The single exception is quiet hours, below.
 *
 * QUIET HOURS — OQ-018, NOW DECIDED (DEC-0040)
 * --------------------------------------------
 * The repository owner has classified a customer-initiated OTP for a canonical
 * FR-091 sensitive action as a `USER_INITIATED_SECURITY_TRANSACTION`: a
 * transactional security message, never a scheduled outbound notification and never
 * marketing. It is EXEMPT from quiet hours 20.00–08.00.
 *
 * This supersedes the conservative deferral Step 7 originally implemented and
 * recorded as OQ-018. That reading made FR-091 unavailable for twelve hours a day,
 * because a challenge lives five minutes and a message deferred to 08.00 verifies a
 * challenge that expired at 22.35.
 *
 * THE EXEMPTION IS GATED ON AN EXPLICIT CUSTOMER REQUEST AND ON NOTHING ELSE.
 * `$origin` is a REQUIRED, TYPED argument with no permissive default. An OTP with
 * any other origin is REFUSED — not deferred, not sent later — because sending a
 * code no customer asked for is the abuse the gate exists to prevent, and delaying
 * it does not make it acceptable. The ordinary outbox separately refuses any
 * OTP-carrying template, so this is the only path that can reach the exemption.
 *
 * MARKETING CANNOT ACQUIRE IT. Category comes from the TEMPLATE, never a caller
 * (FR-096, NOT-024), and this path renders exactly one template. A marketing message
 * has no argument it can pass to become a security transaction.
 *
 * NOTHING ELSE RELAXES. Rate limits, resend cooldown, five-minute expiry, attempt
 * limit, single-use consumption, and destination/action/token/order binding all
 * still apply in full (DEC-0040 decision item 5).
 *
 * NEVER THROWS. FR-099 applies here as everywhere: an OTP that cannot be messaged
 * does not break the challenge, the portal, or the order. And a provider failure is
 * reported as a failure: `SENT` means the provider ACCEPTED the message, never that
 * a customer received it, and an unavailable provider is FAILED_PERMANENT rather
 * than anything that reads like delivery (Rule 01, DEC-0040 decision item 6).
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
     *
     * `$origin` is required and has no default. A default would have been the whole
     * hole: every future caller would inherit "a customer requested this" without
     * ever having established that one did (DEC-0040 decision item 3).
     */
    public function send(Order $order, string $code, OtpDispatchOrigin $origin): ?string
    {
        try {
            return $this->deliver($order, $code, $origin);
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

    private function deliver(Order $order, string $code, OtpDispatchOrigin $origin): ?string
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

        // Consent: the OTP template is TRANSACTIONAL by catalogue definition, so a
        // marketing opt-out does not — and must not — block a code the customer
        // just asked for (DEC-0040 decision item 4). What this call still enforces
        // is a reachable destination.
        $policy = SendPolicy::evaluate($template, $customer, $destination);

        // The classification, and therefore the exemption, is earned by the ORIGIN.
        // An automated origin earns null, so it can never earn the exemption.
        $classification = $origin->securityClassification();

        $now = Carbon::now('UTC');
        $defer = QuietHours::shouldDefer($outlet, $now, $classification);
        $scheduledFor = $defer ? QuietHours::nextPermitted($outlet, $now) : $now;

        $state = match (true) {
            // An OTP nobody asked for. REFUSED, not deferred — see the class
            // docblock: delaying a code no customer requested does not make it
            // acceptable (DEC-0040 decision item 3).
            ! $origin->isCustomerInitiated() => NotificationIntent::STATE_SUPPRESSED,
            ! $policy['allowed'] => NotificationIntent::STATE_SUPPRESSED,
            // Reachable only when the classification is absent, which for this
            // path means the origin was not a customer request — and that case is
            // already suppressed above. Kept so the exemption is a decision the
            // match arm shows rather than an omission a reader must infer.
            $defer => NotificationIntent::STATE_DEFERRED,
            default => NotificationIntent::STATE_PENDING,
        };

        $suppressionReason = match (true) {
            ! $origin->isCustomerInitiated() => NotificationIntent::SUPPRESSED_OTP_NOT_CUSTOMER_INITIATED,
            ! $policy['allowed'] => $policy['reason'],
            default => null,
        };

        $intent = $this->recordIntent(
            $order,
            $template,
            $destination,
            $state,
            $suppressionReason,
            $scheduledFor,
            $defer,
            $classification,
        );

        if ($intent === null || $state !== NotificationIntent::STATE_PENDING) {
            // Refused (no customer request), suppressed (unreachable), or deferred:
            // nothing is sent, and nothing claims it was.
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
        ?string $securityClassification,
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
            // DEC-0040. The audit record of WHY this message was not held until
            // 08.00. A database CHECK refuses this value together with
            // `deferred_for_quiet_hours`, so the two can never both be true.
            'security_classification' => $securityClassification,
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
