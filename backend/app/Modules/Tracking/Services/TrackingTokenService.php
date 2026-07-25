<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Ordering\Models\Order;
use App\Modules\SharedKernel\Http\ApiException;
use App\Modules\SharedKernel\Http\ErrorCode;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingToken;
use App\Modules\Tracking\Support\IssuedTrackingLink;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * STEP 7 · UNIT A — THE TRACKING-TOKEN LIFECYCLE (FR-086, FR-087, FR-088).
 *
 * Realises TRACKING_ACCESS_LIFECYCLE.md transitions K-01 (issue), K-08 (revoke),
 * K-09 (expire), and K-10 (rotate/supersede). K-02 (resolve) lives in
 * PublicTrackingResolver because it is reached by an anonymous visitor and shares
 * none of this class's tenant-context assumptions.
 *
 * THE PLAINTEXT RULE, STATED ONCE AND ENFORCED EVERYWHERE
 * ------------------------------------------------------
 * `issue()` and `rotate()` are the ONLY methods in the entire application that
 * ever hold a plaintext token, and each returns it exactly once inside an
 * IssuedTrackingLink. Nothing persists it, nothing logs it, no projection carries
 * it, and no endpoint can retrieve it afterwards (TRK-002, TRK-019). A customer
 * who loses the link gets a NEW one — that is what rotation is for.
 *
 * WHY SHA-256 AND NOT A PASSWORD HASH
 * -----------------------------------
 * The token is 256 bits of CSPRNG material, not a human-chosen secret. There is no
 * dictionary to slow down and no rainbow table to salt against; a work factor
 * would buy nothing. What it would cost is real: the public resolver must find a
 * row from a presented token with no tenant hint, so a non-deterministic hash
 * would turn every public request into a full table scan. That is an availability
 * defect on the most exposed surface in the product, traded for no security gain.
 */
class TrackingTokenService
{
    /** 32 bytes = 256 bits. Base64url without padding → 43 URL-safe characters. */
    private const TOKEN_BYTES = 32;

    /**
     * The bound applied at issuance, before completion is known.
     *
     * TRK-005's canonical rule is "30 days after order completion", but completion
     * has not happened when the link is handed over at the counter. So issuance
     * sets a bounded ceiling and `applyCompletionExpiry()` tightens it the moment
     * the order completes. There is no path to an unbounded link: the column is
     * NOT NULL and every write computes a value.
     */
    private const ISSUE_HORIZON_DAYS = 60;

    /** TRK-005 — the canonical default, applied once the order completes. */
    private const DAYS_AFTER_COMPLETION = 30;

    /**
     * Issue a tracking access for an order (K-01).
     *
     * Idempotent on `client_reference`: a replayed issue command returns the
     * ORIGINAL record. It cannot return the original plaintext, because the
     * original plaintext was never stored — the replay is therefore reported as a
     * conflict the caller must resolve by rotating, rather than by silently
     * minting a second live link behind the first one's back.
     */
    public function issue(TenantContext $context, Order $order, string $clientReference): IssuedTrackingLink
    {
        return DB::transaction(function () use ($context, $order, $clientReference): IssuedTrackingLink {
            $replay = TrackingToken::query()
                ->forTenant($context->tenantId())
                ->where('client_reference', $clientReference)
                ->first();

            if ($replay !== null) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Tautan pelacakan untuk perintah ini sudah pernah dibuat. Gunakan rotasi tautan bila pelanggan kehilangan tautannya.',
                    ['client_reference' => ['already_issued']],
                );
            }

            // A DRAFT order has no customer-facing existence yet; issuing a link
            // against one would publish an order the counter has not accepted.
            if ($order->status === Order::STATUS_DRAFT) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Pesanan masih berstatus draf. Terima pesanan terlebih dahulu sebelum membuat tautan pelacakan.',
                    ['status' => ['order_is_draft']],
                );
            }

            $existing = TrackingToken::query()
                ->forTenant($context->tenantId())
                ->where('order_id', $order->id)
                ->where('state', TrackingToken::STATE_ISSUED)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->isLive()) {
                // Refuse rather than silently rotate. Two live links for one order
                // is the state the partial unique index forbids, and quietly
                // superseding a link somebody is holding is a surprise, not a
                // convenience — rotation is an explicit, reason-bearing act.
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Pesanan ini sudah memiliki tautan pelacakan aktif. Gunakan rotasi bila tautan perlu diganti.',
                    ['order' => ['tracking_link_already_active']],
                );
            }

            if ($existing !== null) {
                // Present but past its expiry: sweep it to EXPIRED so the partial
                // unique index frees up and the audit records the transition.
                $this->markExpired($existing);
            }

            return $this->mint($context, $order, $clientReference, TrackingAccessEvent::TYPE_ISSUED, null);
        });
    }

    /**
     * Rotate: mint a new access and supersede the old one, in ONE transaction (K-10).
     *
     * Atomicity is the security property here. If the new row were written and the
     * supersession failed, two links would resolve; if the supersession committed
     * and the mint failed, the customer would hold a dead link with no replacement.
     * The row lock also serialises this against a concurrent revoke, so a token can
     * never end up revoked-and-superseded into an inconsistent pair.
     */
    public function rotate(
        TenantContext $context,
        string $tokenId,
        ?int $expectedVersion,
        string $clientReference,
        string $reasonCode,
        ?string $reason,
    ): IssuedTrackingLink {
        return DB::transaction(function () use ($context, $tokenId, $expectedVersion, $clientReference, $reasonCode, $reason): IssuedTrackingLink {
            $current = $this->lockOrFail($context, $tokenId);
            $this->assertVersion($current, $expectedVersion);

            if ($current->state !== TrackingToken::STATE_ISSUED) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Tautan ini sudah tidak aktif dan tidak dapat dirotasi. Buat tautan baru untuk pesanan ini.',
                    ['state' => ['not_issued']],
                );
            }

            $order = Order::query()->forTenant($context->tenantId())->find($current->order_id);
            if ($order === null) {
                throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
            }

            $current->state = TrackingToken::STATE_SUPERSEDED;
            $current->superseded_at = now();
            $current->save();

            $issued = $this->mint($context, $order, $clientReference, TrackingAccessEvent::TYPE_REISSUED, [
                'superseded_token_id' => $current->id,
                'reason_code' => $reasonCode,
            ]);

            $current->superseded_by_id = $issued->token->id;
            $current->save();

            $this->record($context, $current, TrackingAccessEvent::TYPE_SUPERSEDED, [
                'reason_code' => $reasonCode,
                'reason' => $reason,
                'superseded_by_id' => $issued->token->id,
            ]);

            return $issued;
        });
    }

    /**
     * Revoke (K-08). Terminal, immediate, and reason-bearing.
     *
     * There is no un-revoke. A revocation that whoever performed it could undo is
     * not a security control (TRACKING_ACCESS_LIFECYCLE §4.2), so recovery is a
     * fresh issuance and the revocation record stays.
     */
    public function revoke(
        TenantContext $context,
        string $tokenId,
        ?int $expectedVersion,
        string $reasonCode,
        ?string $reason,
    ): TrackingToken {
        return DB::transaction(function () use ($context, $tokenId, $expectedVersion, $reasonCode, $reason): TrackingToken {
            $token = $this->lockOrFail($context, $tokenId);
            $this->assertVersion($token, $expectedVersion);

            if ($token->state === TrackingToken::STATE_REVOKED) {
                // Idempotent: already revoked is the state the caller wanted.
                return $token;
            }

            if ($token->isTerminal()) {
                throw ApiException::of(
                    ErrorCode::CONFLICT,
                    'Tautan ini sudah berakhir dan tidak dapat dicabut lagi.',
                    ['state' => ['already_terminal']],
                );
            }

            $token->state = TrackingToken::STATE_REVOKED;
            $token->revoked_at = now();
            $token->revoked_by_membership_id = $context->membershipId();
            $token->revoke_reason_code = $reasonCode;
            $token->revoke_reason = $reason;
            $token->save();

            $this->record($context, $token, TrackingAccessEvent::TYPE_REVOKED, [
                'reason_code' => $reasonCode,
                'reason' => $reason,
            ]);

            return $token->fresh();
        });
    }

    /**
     * Apply TRK-005's completion-anchored expiry.
     *
     * Only ever TIGHTENS: `min(current, completed_at + 30 days)`. Extending an
     * expiry in place is forbidden outright (TRACKING_ACCESS_LIFECYCLE §5), so this
     * method cannot lengthen a link's life even if called with a later anchor.
     */
    public function applyCompletionExpiry(TenantContext $context, Order $order, DateTimeInterface $completedAt): void
    {
        $ceiling = Carbon::parse($completedAt->format(DATE_ATOM))->addDays(self::DAYS_AFTER_COMPLETION);

        TrackingToken::query()
            ->forTenant($context->tenantId())
            ->where('order_id', $order->id)
            ->where('state', TrackingToken::STATE_ISSUED)
            ->where('expires_at', '>', $ceiling)
            ->update(['expires_at' => $ceiling, 'updated_at' => now()]);
    }

    /** Sweep an observed-expired access to its terminal state (K-09). */
    public function markExpired(TrackingToken $token): TrackingToken
    {
        if ($token->state !== TrackingToken::STATE_ISSUED) {
            return $token;
        }

        $token->state = TrackingToken::STATE_EXPIRED;
        $token->save();

        TrackingAccessEvent::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $token->tenant_id,
            'tracking_token_id' => $token->id,
            'type' => TrackingAccessEvent::TYPE_EXPIRED,
            'actor_membership_id' => null,
            'payload' => ['reason' => 'expiry_reached'],
            'occurred_at' => now(),
        ]);

        return $token;
    }

    /**
     * THE hash function for tracking tokens. One definition, used by the minter
     * and by the resolver, so the two can never drift apart.
     */
    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Generate the plaintext.
     *
     * `random_bytes()` is the CSPRNG (FR-086). Base64url so the value is safe in a
     * URL path without encoding, and stripped of `=` padding so the link carries no
     * character that a chat client might treat as a boundary.
     *
     * The output derives from NOTHING (FR-087): not the order number, not any id,
     * not the clock, not a counter.
     */
    public static function generatePlaintext(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    private function mint(
        TenantContext $context,
        Order $order,
        string $clientReference,
        string $eventType,
        ?array $extraPayload,
    ): IssuedTrackingLink {
        $plaintext = self::generatePlaintext();

        $token = new TrackingToken();
        $token->id = (string) Str::uuid();
        $token->tenant_id = $context->tenantId();
        $token->order_id = $order->id;
        $token->outlet_id = $order->outlet_id;
        $token->token_hash = self::hash($plaintext);
        $token->state = TrackingToken::STATE_ISSUED;
        $token->issued_at = now();
        $token->expires_at = now()->addDays(self::ISSUE_HORIZON_DAYS);
        $token->issued_by_membership_id = $context->membershipId();
        $token->client_reference = $clientReference;
        $token->save();

        $this->record($context, $token, $eventType, $extraPayload ?? []);

        // The ONLY moment the plaintext leaves this class.
        return new IssuedTrackingLink($token->fresh(), $plaintext);
    }

    private function record(TenantContext $context, TrackingToken $token, string $type, array $payload): void
    {
        TrackingAccessEvent::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $token->tenant_id,
            'tracking_token_id' => $token->id,
            'type' => $type,
            'actor_membership_id' => $context->membershipId(),
            // Whatever the caller passes, the plaintext is not among the things it
            // can pass: no code path in this class puts $plaintext into a payload.
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function lockOrFail(TenantContext $context, string $tokenId): TrackingToken
    {
        $token = TrackingToken::query()
            ->forTenant($context->tenantId())
            ->where('id', $tokenId)
            ->lockForUpdate()
            ->first();

        if ($token === null) {
            // A token belonging to another tenant 404s exactly like an absent one
            // (Rule 48 hard rule 5). The caller learns nothing either way.
            throw ApiException::of(ErrorCode::NOT_FOUND, 'Data yang Anda cari tidak ditemukan.');
        }

        return $token;
    }

    private function assertVersion(TrackingToken $token, ?int $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $token->version !== $expectedVersion) {
            throw ApiException::of(ErrorCode::CONFLICT);
        }
    }
}
