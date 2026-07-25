<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * ONE APPEND-ONLY ATTEMPT RECORD.
 *
 * FR-099 requires failures to be "visible and retried under a bounded policy".
 * Visibility only means something if the history cannot be tidied away, so this
 * table is append-only in three layers exactly as `customer_consents` and
 * `production_events` are: this model refuses loudly, no service exposes an update
 * path, and PostgreSQL triggers refuse UPDATE, DELETE, and TRUNCATE — covering the
 * paths this application cannot see.
 *
 * WHAT A ROW MAY NEVER CONTAIN
 * ----------------------------
 * A provider credential, an OTP value, a tracking-token plaintext, or a full
 * address (NOT-015, NOT-016, Rule 46 hard rule 2). `detail` is first-party,
 * redacted text — never the vendor's raw error body, which would both leak vendor
 * shape into stored business data (breaking FR-093) and risk echoing back whatever
 * we sent.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $intent_id
 * @property string $outcome
 */
class NotificationAttempt extends Model
{
    use HasUuids;

    protected $table = 'notification_attempts';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'attempt_number' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            throw new RuntimeException(
                'notification_attempts is append-only (FR-099, NOT-018). A failed send stays '
                .'visible; record a new attempt instead of rewriting an existing one.'
            );
        });

        static::deleting(function (self $attempt): void {
            throw new RuntimeException(
                'notification_attempts is append-only (NOT-018) and is never deleted.'
            );
        });
    }
}
