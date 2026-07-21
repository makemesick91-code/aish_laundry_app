<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * ONE APPEND-ONLY CONSENT RECORD (FR-027, FR-028).
 *
 * WHY THERE IS NO `SoftDeletes` AND NO UPDATE PATH
 * ------------------------------------------------
 * Consent history is evidence. FR-028 requires that a recorded opt-out is never
 * reset by an import, a bulk update, or a migration. Granting appends a row;
 * withdrawing appends another; the current state is the latest row. An opt-out
 * cannot be reset because there is nothing to overwrite.
 *
 * DEFENCE IN DEPTH, THREE LAYERS
 * ------------------------------
 *   1. This model refuses to update or delete an existing record, loudly.
 *   2. No service or controller exposes an update or delete path.
 *   3. PostgreSQL rules on the table make UPDATE and DELETE do nothing at all,
 *      which covers the paths this model cannot see — a migration, an import
 *      script, a direct psql session.
 *
 * Layer 3 is the one FR-028 actually names, because a migration runs outside
 * the application entirely. Layers 1 and 2 exist so a developer discovers the
 * rule at the point of writing the code rather than by wondering why their
 * update silently did nothing.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $customer_id
 * @property string $consent_type
 * @property string $state
 * @property string $source
 * @property string|null $recorded_by_membership_id
 */
class CustomerConsent extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATE_GRANTED = 'granted';

    public const STATE_WITHDRAWN = 'withdrawn';

    public const TYPE_MARKETING_WHATSAPP = 'marketing_whatsapp';

    public const TYPE_MARKETING_EMAIL = 'marketing_email';

    public const TYPE_MARKETING_SMS = 'marketing_sms';

    protected $table = 'customer_consents';

    /**
     * Everything meaningful is set explicitly by the recording service.
     *
     * `recorded_at` is deliberately absent: a client-suppliable consent
     * timestamp is a backdated consent record (T-07).
     */
    protected $fillable = [
        'consent_type',
        'state',
        'source',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Refuse to rewrite history.
     *
     * Throws rather than returning false. A silent refusal would let a caller
     * believe a withdrawal had been reversed, and "the update appeared to work"
     * is the exact failure mode FR-028 exists to prevent.
     */
    protected static function booted(): void
    {
        static::updating(function (self $consent): void {
            throw new RuntimeException(
                'Consent records are append-only (FR-028). Record a new consent '
                .'entry instead of modifying an existing one.'
            );
        });

        static::deleting(function (self $consent): void {
            throw new RuntimeException(
                'Consent records are append-only (FR-028) and are never deleted. '
                .'Withdrawal is recorded as a new entry.'
            );
        });
    }
}
