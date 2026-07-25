<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Notification\Contracts\MessageSecurityClassification;
use App\Modules\SharedKernel\Cache\TenantCacheKey;
use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingOtpChallenge;
use App\Modules\Tracking\Support\ResolvedTrackingAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * STEP 7 · UNIT D — OTP FOR THE TWO CANONICAL SENSITIVE ACTIONS (FR-091).
 *
 * SCOPE DISCIPLINE FIRST. FR-091 names exactly two sensitive actions: changing a
 * delivery address, and requesting a schedule change. This service gates those two
 * and nothing else. No "verify your identity to see more" flow is invented, and no
 * third action is added — a customer-facing capability the PRD does not carry does
 * not exist (Rule 16, Rule 00 hard rule 6).
 *
 * WHAT VERIFICATION ACTUALLY BUYS IN STEP 7
 * -----------------------------------------
 * A verified OTP records an ACCEPTED REQUEST. It does not reschedule a delivery and
 * it does not rewrite an address, because both of those act on Step 8 workflow that
 * does not exist yet (DEC-0039 §5). Building the effect now would be the scope leak;
 * building the gate now is what FR-091 asks for. The operator sees the accepted
 * request and acts on it.
 *
 * Note what verification does NOT unlock: the full address is never rendered back
 * to the portal, in any state, with or without OTP (TRK-010). The customer SUPPLIES
 * a new address; they never read the old one out of the page.
 *
 * ONE FAILURE RESPONSE
 * --------------------
 * Wrong code, expired challenge, consumed challenge, wrong action, wrong token,
 * attempts exhausted, throttled — every one returns `false`. The caller renders one
 * body. A response that distinguished "expired" from "wrong" would tell an attacker
 * they had found a live challenge.
 */
class TrackingOtpService
{
    /** Challenges per token per hour. The OTP path is not a free messaging cannon. */
    private const MAX_CHALLENGES_PER_TOKEN = 3;

    private const CHALLENGE_DECAY_SECONDS = 3600;

    /**
     * Issue a challenge for a sensitive action.
     *
     * Returns the plaintext code EXACTLY ONCE, to the caller that will hand it to
     * the notification subsystem. It is never persisted, never logged, never
     * returned by an HTTP response, and never written into a notification attempt
     * row (NOT-016, Rule 03 hard rule 20).
     *
     * Returns null when the challenge cannot be issued — rate limited, cooling
     * down, or an unknown action. Null, again, so the caller cannot branch.
     *
     * DEC-0040 EXEMPTS THE RESULTING MESSAGE FROM QUIET HOURS, AND CHANGES NOTHING
     * HERE. Every guard below still applies at every hour: the per-token limit
     * (3/hour), the per-IP limit (12/hour), the resend cooldown, the five-minute
     * expiry, and the attempt limit and single-use consumption in `verify()`. The
     * exemption moved the SCHEDULE; it did not open a messaging cannon, and a
     * customer-initiated code at 02.00 is rate-limited exactly as one at 14.00.
     */
    public function issue(ResolvedTrackingAccess $access, string $action, string $clientIp): ?string
    {
        if (! in_array($action, TrackingOtpChallenge::ACTIONS, true)) {
            return null;
        }

        $key = TenantCacheKey::publicTrackingRateLimit('otp_issue', $access->token->token_hash);
        $ipKey = TenantCacheKey::ipRateLimit('public_tracking_otp', $clientIp);

        if (RateLimiter::tooManyAttempts($key, self::MAX_CHALLENGES_PER_TOKEN)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_CHALLENGES_PER_TOKEN * 4)) {
            return null;
        }

        // Resend cooldown: an unconsumed, unexpired challenge younger than the
        // cooldown means the customer already has a live code. Re-issuing would let
        // the endpoint be used to spam a phone number the requester does not own.
        $recent = TrackingOtpChallenge::query()
            ->forTenant($access->token->tenant_id)
            ->where('tracking_token_id', $access->token->id)
            ->where('action', $action)
            ->whereNull('consumed_at')
            ->orderByDesc('issued_at')
            ->first();

        if ($recent !== null
            && $recent->issued_at !== null
            && $recent->issued_at->addSeconds(TrackingOtpChallenge::RESEND_COOLDOWN_SECONDS)->isFuture()) {
            return null;
        }

        RateLimiter::hit($key, self::CHALLENGE_DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::CHALLENGE_DECAY_SECONDS);

        // Six digits from the CSPRNG. `random_int` throws rather than falling back
        // to a weak source, which is the behaviour we want: no OTP is better than a
        // predictable one.
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = new TrackingOtpChallenge();
        $challenge->id = (string) Str::uuid();
        $challenge->tenant_id = $access->token->tenant_id;
        $challenge->tracking_token_id = $access->token->id;
        $challenge->order_id = $access->order->id;
        $challenge->action = $action;
        $challenge->code_hash = self::hash($code);
        $challenge->issued_at = now();
        $challenge->expires_at = now()->addSeconds(TrackingOtpChallenge::TTL_SECONDS);
        $challenge->save();

        TrackingAccessEvent::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $access->token->tenant_id,
            'tracking_token_id' => $access->token->id,
            'type' => TrackingAccessEvent::TYPE_OTP_CHALLENGE_ISSUED,
            'actor_membership_id' => null,
            // The ACTION, the challenge id, and the DEC-0040 classification. Never
            // the code (NOT-016).
            //
            // The classification is recorded HERE, on the tracking side, as well as
            // on the notification intent, because this is the row that proves a
            // CUSTOMER asked. An auditor reading only the outbox would see a message
            // sent at 02.00 and have to take the exemption on trust; this event is
            // where the trust is grounded.
            'payload' => [
                'action' => $action,
                'challenge_id' => $challenge->id,
                'classification' => MessageSecurityClassification::USER_INITIATED_SECURITY_TRANSACTION,
            ],
            'occurred_at' => now(),
        ]);

        return $code;
    }

    /**
     * Verify a submitted code against the live challenge for (token, action).
     *
     * Every guard is inside one transaction with a row lock, so two concurrent
     * submissions cannot both consume the same challenge and cannot both increment
     * from the same attempt count.
     */
    public function verify(ResolvedTrackingAccess $access, string $action, string $submitted): bool
    {
        return DB::transaction(function () use ($access, $action, $submitted): bool {
            $challenge = TrackingOtpChallenge::query()
                ->forTenant($access->token->tenant_id)
                // Bound to THIS token: a challenge minted against another link can
                // never be verified here, whatever code is submitted.
                ->where('tracking_token_id', $access->token->id)
                // Bound to THIS action: a code for change_delivery_address can
                // never authorise request_schedule_change.
                ->where('action', $action)
                ->whereNull('consumed_at')
                ->orderByDesc('issued_at')
                ->lockForUpdate()
                ->first();

            if ($challenge === null || ! $challenge->isUsable()) {
                $this->recordFailure($access, $action);

                return false;
            }

            // Count the attempt BEFORE comparing, so a crash or an exception between
            // compare and save cannot give an attacker a free guess.
            $challenge->attempts = (int) $challenge->attempts + 1;
            $challenge->save();

            // Constant-time comparison. A `===` on the hash is already
            // length-invariant, but hash_equals states the intent and removes any
            // question about short-circuit behaviour.
            if (! hash_equals($challenge->code_hash, self::hash($submitted))) {
                $this->recordFailure($access, $action);

                return false;
            }

            // Consumed. A verified code cannot be replayed — this is what makes
            // "verified once" mean once.
            $challenge->consumed_at = now();
            $challenge->save();

            TrackingAccessEvent::query()->forceCreate([
                'id' => (string) Str::uuid(),
                'tenant_id' => $access->token->tenant_id,
                'tracking_token_id' => $access->token->id,
                'type' => TrackingAccessEvent::TYPE_OTP_VERIFIED,
                'actor_membership_id' => null,
                'payload' => ['action' => $action, 'challenge_id' => $challenge->id],
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    private function recordFailure(ResolvedTrackingAccess $access, string $action): void
    {
        TrackingAccessEvent::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $access->token->tenant_id,
            'tracking_token_id' => $access->token->id,
            'type' => TrackingAccessEvent::TYPE_OTP_FAILED,
            'actor_membership_id' => null,
            // The action attempted, never the submitted value. Logging a wrong
            // guess would put a near-miss OTP in durable storage.
            'payload' => ['action' => $action],
            'occurred_at' => now(),
        ]);
    }
}
