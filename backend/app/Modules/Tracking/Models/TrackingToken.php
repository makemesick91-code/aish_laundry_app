<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Models;

use App\Modules\Ordering\Models\Order;
use App\Modules\Organization\Models\Outlet;
use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * THE TrackingAccess AGGREGATE (FR-086 … FR-088).
 *
 * The plaintext token is NOT a property of this model and never will be. Only
 * `token_hash` is persisted; the plaintext is returned exactly once, by the
 * service that minted it, and exists thereafter only inside the link the customer
 * holds (TRK-002, TRK-019).
 *
 * `$fillable` is EMPTY on purpose. Every field here is server-owned: the state,
 * the expiry, the view counter, the revocation actor. A mass-assignable expiry
 * would let a request extend its own access, which is the whole point of having a
 * bounded one.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $order_id
 * @property string $outlet_id
 * @property string $token_hash
 * @property string $state
 * @property int $view_count
 * @property int $version
 */
class TrackingToken extends Model
{
    use HasOptimisticVersion;
    use HasUuids;

    /** The only live state. A resolution leaves the record here (K-02). */
    public const STATE_ISSUED = 'ISSUED';

    /** Terminal. Deliberately terminated by staff; effective immediately (K-08). */
    public const STATE_REVOKED = 'REVOKED';

    /** Terminal. Passed its bound expiry (K-09). */
    public const STATE_EXPIRED = 'EXPIRED';

    /** Terminal. Replaced by a rotation (K-10). */
    public const STATE_SUPERSEDED = 'SUPERSEDED';

    public const STATES = [self::STATE_ISSUED, self::STATE_REVOKED, self::STATE_EXPIRED, self::STATE_SUPERSEDED];

    protected $table = 'tracking_tokens';

    /**
     * Nothing is mass-assignable. See the class docblock: every column is a
     * server decision, and the expiry in particular must never be client-settable.
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'last_viewed_at' => 'datetime',
            'revoked_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'view_count' => 'integer',
            'version' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Is this access resolvable RIGHT NOW?
     *
     * Both halves matter. A row may still read `ISSUED` while its `expires_at` has
     * passed — the sweep to `EXPIRED` is observational, not a scheduler guarantee —
     * so expiry is evaluated against server time on every read. A client clock
     * never extends an access (TRACKING_ACCESS_LIFECYCLE §4.3).
     */
    public function isLive(): bool
    {
        return $this->state === self::STATE_ISSUED
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Terminal states never return to ISSUED. Recovery is a NEW issuance, which is
     * what makes a revocation mean anything (TRACKING_ACCESS_LIFECYCLE §5).
     */
    public function isTerminal(): bool
    {
        return in_array($this->state, [self::STATE_REVOKED, self::STATE_EXPIRED, self::STATE_SUPERSEDED], true);
    }
}
