<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * THE IMMUTABLE TRACKING-ACCESS AUDIT (TRK-024).
 *
 * Issuance, views, OTP challenges, throttling, revocation, and rotation are
 * recorded here as security events. The record of who revoked a link and why is
 * evidence, so it is append-only in three layers, exactly as `customer_consents`
 * is: this model refuses loudly, no service exposes an update path, and the table
 * carries refuse-UPDATE/DELETE/TRUNCATE triggers that also cover the paths this
 * application cannot see.
 *
 * WHAT A PAYLOAD MAY NEVER CONTAIN
 * --------------------------------
 * The token plaintext and any OTP value (TRK-019, NOT-016). Both are `SECRET`
 * (Rule 21). `TrackingSchemaTest` asserts no stored payload holds a value shaped
 * like a token, rather than trusting this comment.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $tracking_token_id
 * @property string $type
 * @property array<string, mixed> $payload
 */
class TrackingAccessEvent extends Model
{
    use HasUuids;

    public const TYPE_ISSUED = 'TrackingAccessIssued';

    public const TYPE_VIEWED = 'TrackingAccessViewed';

    public const TYPE_THROTTLED = 'TrackingAccessThrottled';

    public const TYPE_OTP_CHALLENGE_ISSUED = 'TrackingOtpChallengeIssued';

    public const TYPE_OTP_VERIFIED = 'TrackingOtpVerified';

    public const TYPE_OTP_FAILED = 'TrackingOtpFailed';

    public const TYPE_REVOKED = 'TrackingAccessRevoked';

    public const TYPE_EXPIRED = 'TrackingAccessExpired';

    public const TYPE_REISSUED = 'TrackingAccessReissued';

    public const TYPE_SUPERSEDED = 'TrackingAccessSuperseded';

    protected $table = 'tracking_access_events';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    protected static function booted(): void
    {
        static::updating(function (self $event): void {
            throw new RuntimeException(
                'tracking_access_events is append-only (TRK-022, TRK-024). The record of '
                .'who revoked a tracking link, and why, is never rewritten.'
            );
        });

        static::deleting(function (self $event): void {
            throw new RuntimeException(
                'tracking_access_events is append-only (TRK-024) and is never deleted.'
            );
        });
    }
}
