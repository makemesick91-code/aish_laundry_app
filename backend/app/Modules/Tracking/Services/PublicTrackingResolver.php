<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Cache\TenantCacheKey;
use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingToken;
use App\Modules\Tracking\Support\CustomerVisibleStatus;
use App\Modules\Tracking\Support\ResolvedTrackingAccess;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * STEP 7 · UNIT C — RESOLUTION FOR AN ANONYMOUS VISITOR (K-02).
 *
 * This is the most exposed code path in the product: an unauthenticated stranger,
 * holding only a string, reaching a tenant's order data. Everything below follows
 * from that sentence.
 *
 * ONE OUTCOME FOR EVERY FAILURE
 * -----------------------------
 * Unknown, malformed, expired, revoked, superseded, and throttled all return
 * `null`, and the caller renders ONE response body for all six. No distinct code,
 * no distinct message, no distinct length, no early return that a timing
 * measurement could separate. Distinguishing any of them would turn this endpoint
 * into an oracle that answers "does this order exist?" — which is precisely what
 * `TRK-007` and Rule 48 hard rule 5 forbid.
 *
 * THE TENANT IS NEVER SUPPLIED BY THE VISITOR
 * -------------------------------------------
 * A visitor presents a token and nothing else. The tenant is derived server-side
 * from the stored row (`TRK-021`). There is no parameter, header, or hostname on
 * this path that could influence which tenant is read, so a client-supplied tenant
 * cannot be authorization proof here because there is no client-supplied tenant at
 * all. If the stored row's tenant cannot be established, the lookup FAILS CLOSED.
 *
 * WHY THROTTLING IS NOT PERSISTED ON THE AGGREGATE
 * ------------------------------------------------
 * The lifecycle document models `THROTTLED` as a state. Realising it as a stored
 * state would let an attacker who guesses at one victim's link push that link into
 * a persistent throttled state — the abuse-resistance mechanism would become the
 * attack. So it is a transient Redis condition keyed on the HASH of the presented
 * token and the HASH of the client address, and it expires on its own (K-04).
 */
class PublicTrackingResolver
{
    /** Per presented-token attempts, then the same generic answer for a while. */
    private const MAX_PER_TOKEN = 20;

    /** Per client address, across all tokens — the enumeration budget. */
    private const MAX_PER_IP = 60;

    private const DECAY_SECONDS = 300;

    public function __construct(private readonly TrackingTokenService $tokens)
    {
    }

    /**
     * Resolve a presented plaintext token, or return null.
     *
     * NULL IS THE ONLY FAILURE SIGNAL. The caller must not be able to tell which
     * of the failure reasons applied, so this method deliberately returns no error
     * object, no reason code, and no exception type that could vary the response.
     */
    public function resolve(string $presented, string $clientIp): ?ResolvedTrackingAccess
    {
        // Shape check BEFORE any database work, so a garbage string costs nothing.
        // A token is 43 base64url characters; anything else cannot be one. This is
        // not a security boundary (the hash lookup is), it is a cheap filter.
        $presented = trim($presented);

        $tokenKey = TenantCacheKey::publicTrackingRateLimit('resolve', $presented);
        $ipKey = TenantCacheKey::ipRateLimit('public_tracking', $clientIp);

        if (RateLimiter::tooManyAttempts($tokenKey, self::MAX_PER_TOKEN)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            return null;
        }

        RateLimiter::hit($tokenKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);

        if ($presented === '' || ! preg_match('/^[A-Za-z0-9_-]{20,128}$/', $presented)) {
            return null;
        }

        // The lookup is by HASH. The plaintext is never stored, so this is the only
        // way a token can be resolved at all (TRK-002).
        $token = TrackingToken::query()
            ->where('token_hash', TrackingTokenService::hash($presented))
            ->first();

        if ($token === null) {
            return null;
        }

        // Expiry is decided by the SERVER, on read, every time. A row may still say
        // ISSUED while its expires_at has passed; the sweep below makes the stored
        // state agree, but the decision does not wait for it.
        if (! $token->isLive()) {
            if ($token->state === TrackingToken::STATE_ISSUED) {
                $this->tokens->markExpired($token);
            }

            return null;
        }

        $order = Order::query()->forTenant($token->tenant_id)->find($token->order_id);

        // Fail closed. A token whose order cannot be established in its own tenant
        // resolves to nothing rather than to a guess (TRK-021).
        if ($order === null || ! CustomerVisibleStatus::isPubliclyRenderable($order->status)) {
            return null;
        }

        $this->recordView($token, $clientIp);

        return new ResolvedTrackingAccess($token, $order);
    }

    /**
     * A successful resolution leaves the access ISSUED (K-02).
     *
     * Sharing is a FEATURE (TRK-014): the link is forwarded over WhatsApp to the
     * family member collecting the laundry, and it must keep working for them. So a
     * view updates the counter and the last-viewed timestamp and changes nothing
     * else. The projection is designed to be safe under exactly that assumption.
     */
    private function recordView(TrackingToken $token, string $clientIp): void
    {
        $token->forceFill([
            'last_viewed_at' => now(),
            'view_count' => (int) $token->view_count + 1,
        ])->saveQuietly();

        TrackingAccessEvent::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => $token->tenant_id,
            'tracking_token_id' => $token->id,
            'type' => TrackingAccessEvent::TYPE_VIEWED,
            'actor_membership_id' => null,
            // A HASH of the address, never the address itself. Enough to recognise
            // a repeat visitor during an incident; not a stored log of who read
            // what from where (Rule 21 data classification, Rule 46).
            'payload' => ['client_fingerprint' => hash('sha256', $clientIp)],
            'occurred_at' => now(),
        ]);
    }
}
