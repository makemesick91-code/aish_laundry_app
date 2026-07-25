<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * THE FR-091 OTP GATE for a sensitive portal action.
 *
 * Exactly two actions exist, and they are the two FR-091 names: changing a
 * delivery address and requesting a schedule change. No third action is invented
 * here — inventing one would be inventing a product decision (Rule 00 hard rule 6),
 * and a customer-facing action the PRD does not carry does not exist (Rule 16).
 *
 * The challenge is bound to (tracking token, order, action). That binding is what
 * stops a code minted for one action verifying another, and a code minted against
 * one link verifying a different link.
 *
 * Only `code_hash` is stored. The plaintext OTP is never persisted, never logged,
 * never returned by an API, and never written into a notification attempt row
 * (NOT-016, Rule 03 hard rule 20).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tracking_token_id
 * @property string $order_id
 * @property string $action
 * @property string $code_hash
 * @property int $attempts
 */
class TrackingOtpChallenge extends Model
{
    use HasUuids;

    public const ACTION_CHANGE_DELIVERY_ADDRESS = 'change_delivery_address';

    public const ACTION_REQUEST_SCHEDULE_CHANGE = 'request_schedule_change';

    /** The closed set FR-091 names. Mirrors the database CHECK constraint. */
    public const ACTIONS = [self::ACTION_CHANGE_DELIVERY_ADDRESS, self::ACTION_REQUEST_SCHEDULE_CHANGE];

    /** Short enough that a stolen code ages out fast; long enough to type. */
    public const TTL_SECONDS = 300;

    /** After this many wrong guesses the challenge is dead, not merely slowed. */
    public const MAX_ATTEMPTS = 5;

    /** Resend cooldown, so the OTP path cannot be used as a free SMS/WA cannon. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    protected $table = 'tracking_otp_challenges';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Usable means: not consumed, not expired, and with attempts remaining.
     *
     * All three are checked together and the caller is told nothing about WHICH
     * one failed — a response that distinguished "expired" from "wrong code" would
     * tell an attacker whether they had the right challenge.
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
