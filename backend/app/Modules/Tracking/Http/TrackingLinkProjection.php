<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http;

use App\Modules\Tracking\Models\TrackingAccessEvent;
use App\Modules\Tracking\Models\TrackingToken;

/**
 * THE OPERATOR-FACING VIEW OF A TRACKING LINK — metadata only.
 *
 * WHAT IS NOT HERE, AND CANNOT BE
 * -------------------------------
 * The token. Not the plaintext, which is unrecoverable, and not the hash either.
 * Exposing the hash would be exposing a value that identifies the credential, and
 * an operator has no use for it: they can see the link's STATE, rotate it, or
 * revoke it. Rule 32 hard rule 10 is explicit — ops surfaces show tracking state
 * and a revoke control, never the token.
 *
 * `view_count` and `last_viewed_at` are here because "has the customer opened the
 * link I sent?" is the question the counter actually asks, and answering it stops a
 * staff member from re-sending unnecessarily.
 */
final class TrackingLinkProjection
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(TrackingToken $token): array
    {
        return [
            'id' => $token->id,
            'order_id' => $token->order_id,
            'state' => $token->state,
            'issued_at' => $token->issued_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'last_viewed_at' => $token->last_viewed_at?->toIso8601String(),
            'view_count' => (int) $token->view_count,
            'revoked_at' => $token->revoked_at?->toIso8601String(),
            'revoke_reason_code' => $token->revoke_reason_code,
            'superseded_at' => $token->superseded_at?->toIso8601String(),
            'is_live' => $token->isLive(),
            'version' => (int) $token->version,
        ];
    }

    /**
     * The lifecycle audit for one link.
     *
     * Payloads are passed through as stored, which is safe precisely because
     * `TrackingTokenService` and `TrackingOtpService` are the only writers and
     * neither has a code path that puts a token or an OTP into one.
     *
     * @return list<array<string, mixed>>
     */
    public static function timeline(TrackingToken $token): array
    {
        return TrackingAccessEvent::query()
            ->forTenant($token->tenant_id)
            ->where('tracking_token_id', $token->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(static fn (TrackingAccessEvent $e): array => [
                'type' => $e->type,
                'actor_membership_id' => $e->actor_membership_id,
                'payload' => $e->payload ?? [],
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])
            ->all();
    }
}
