<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Notification\Contracts\MessageSecurityClassification;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ONE ROW IN THE TRANSACTIONAL OUTBOX (FR-096 … FR-099).
 *
 * An intent is created AFTER the business transaction commits and is dispatched
 * outside it entirely. That ordering is the mechanism by which "a messaging failure
 * never alters an order's state" is structural rather than careful (FR-099).
 *
 * THE STATE VOCABULARY IS HONEST BY DESIGN
 * ----------------------------------------
 * `SENT` means the PROVIDER ACCEPTED the message. It does not mean the customer
 * received it, and no surface in this product renders it that way — we hold no
 * delivery receipt, and claiming one would be a false claim (Rule 01).
 *
 * `SUPPRESSED` always carries a `suppression_reason`, enforced by a database CHECK.
 * A tenant looking at a message that never went out needs to know whether it was
 * opt-out, quiet hours, or a duplicate; "suppressed" with no reason is an answer
 * nobody can act on.
 *
 * `MANUAL_FALLBACK_PREPARED` is deliberately NOT a success state. A staff member
 * has been handed a link to send by hand (FR-095); nothing has been sent, and the
 * word "prepared" is the only word used for it anywhere in the system.
 *
 * THE ONE QUIET-HOURS EXEMPTION LIVES ON A COLUMN, NOT IN CODE (DEC-0040)
 * ----------------------------------------------------------------------
 * `security_classification` is null for every ordinary message and carries
 * `USER_INITIATED_SECURITY_TRANSACTION` only for an OTP a customer explicitly
 * requested. A database CHECK refuses a row that carries that classification AND
 * `deferred_for_quiet_hours`, so "the exemption means it is not deferred" is a
 * property of the schema rather than a rule some future code path must remember.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $category
 * @property string $state
 * @property string $dedup_key
 * @property string|null $security_classification
 */
class NotificationIntent extends Model
{
    use HasUuids;

    public const CATEGORY_TRANSACTIONAL = 'transactional';

    public const CATEGORY_MARKETING = 'marketing';

    public const STATE_PENDING = 'PENDING';

    /** Quiet hours: due, but not now. `scheduled_for` holds the next window. */
    public const STATE_DEFERRED = 'DEFERRED';

    public const STATE_SENDING = 'SENDING';

    /** The provider ACCEPTED it. Never rendered as "delivered". */
    public const STATE_SENT = 'SENT';

    public const STATE_FAILED_RETRYABLE = 'FAILED_RETRYABLE';

    public const STATE_FAILED_PERMANENT = 'FAILED_PERMANENT';

    /** Blocked by consent, opt-out, category, or dedup. Always carries a reason. */
    public const STATE_SUPPRESSED = 'SUPPRESSED';

    public const STATE_MANUAL_FALLBACK_PREPARED = 'MANUAL_FALLBACK_PREPARED';

    public const STATES = [
        self::STATE_PENDING, self::STATE_DEFERRED, self::STATE_SENDING, self::STATE_SENT,
        self::STATE_FAILED_RETRYABLE, self::STATE_FAILED_PERMANENT, self::STATE_SUPPRESSED,
        self::STATE_MANUAL_FALLBACK_PREPARED,
    ];

    // --- Suppression reasons. A closed set, so the UI can explain every one. ---
    public const SUPPRESSED_MARKETING_NO_CONSENT = 'marketing_consent_absent';

    public const SUPPRESSED_MARKETING_OPTED_OUT = 'marketing_opted_out';

    public const SUPPRESSED_NO_DESTINATION = 'recipient_unreachable';

    public const SUPPRESSED_DUPLICATE = 'duplicate_suppressed';

    /**
     * An OTP nobody asked for (DEC-0040 decision item 3).
     *
     * NOT a deferral. The DEC-0040 quiet-hours exemption is gated on an explicit
     * customer request, so an OTP with any other origin is REFUSED — sending it
     * later would be sending a code no customer requested, which is the abuse the
     * gate exists to prevent, merely delayed.
     */
    public const SUPPRESSED_OTP_NOT_CUSTOMER_INITIATED = 'otp_not_customer_initiated';

    /**
     * An OTP-carrying template pushed through the ordinary outbox (DEC-0040).
     *
     * The outbox renders bodies at dispatch time from the template plus the order.
     * It has no code to render and must never store one, so an OTP template reaching
     * it is a defect rather than a message — and refusing it structurally is what
     * keeps the exempt path down to the single caller that holds a live code.
     */
    public const SUPPRESSED_OTP_TEMPLATE_NOT_DISPATCHABLE = 'otp_template_not_dispatchable';

    /**
     * Bounded retry (FR-099, NOT-017/NOT-018). Not forever, not silently dropped.
     * Five attempts across roughly five hours, then permanent and VISIBLE.
     */
    public const MAX_ATTEMPTS = 5;

    /** @var list<int> backoff in seconds, indexed by attempt number already made */
    public const BACKOFF_SECONDS = [60, 300, 900, 3600, 14400];

    protected $table = 'notification_intents';

    /**
     * Empty. Every field is a server decision — most importantly `category`, which
     * comes from the TEMPLATE and never from a caller-supplied string. A
     * mass-assignable category would be the exact hole NOT-024 forbids: a marketing
     * message relabelled transactional to evade opt-out.
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'last_attempted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'deferred_for_quiet_hours' => 'boolean',
            'attempt_count' => 'integer',
            'template_version' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Does this intent carry the single DEC-0040 quiet-hours exemption?
     *
     * Read from the stored classification, never from the template or the category —
     * a transactional category is not an exemption, and never was.
     */
    public function isQuietHoursExempt(): bool
    {
        return MessageSecurityClassification::isQuietHoursExempt($this->security_classification);
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [
            self::STATE_SENT,
            self::STATE_FAILED_PERMANENT,
            self::STATE_SUPPRESSED,
        ], true);
    }

    /**
     * May another attempt be made?
     *
     * Both halves are required: a retryable failure with the attempt budget spent
     * is permanent, and a permanent failure is never retried however few attempts
     * were made.
     */
    public function canRetry(): bool
    {
        return in_array($this->state, [self::STATE_PENDING, self::STATE_DEFERRED, self::STATE_FAILED_RETRYABLE], true)
            && (int) $this->attempt_count < self::MAX_ATTEMPTS;
    }
}
