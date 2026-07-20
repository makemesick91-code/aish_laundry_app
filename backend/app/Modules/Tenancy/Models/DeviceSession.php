<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device's registration WITHIN A TENANT.
 *
 * Rule 03 hard rule 9: a specific device's access can be revoked without forcing
 * every other device to re-authenticate. This table is how that is answerable.
 *
 * TENANT-SCOPED BY CONSTRUCTION. A user in two tenants has separate device
 * sessions per tenant; revoking one affects and reveals nothing in the other.
 * The composite foreign key `device_sessions_tenant_membership_foreign`
 * guarantees the membership referenced is in the SAME tenant.
 *
 * `device_identifier` IS AN UNTRUSTED HINT, never an authorization signal
 * (Rule 31 hard rule 12). It exists so revocation and "which devices are signed
 * in" are answerable — not so an access decision can be made from it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $membership_id
 * @property string $user_id
 * @property string $device_identifier
 */
class DeviceSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'device_sessions';

    protected $fillable = [
        'tenant_id',
        'membership_id',
        'user_id',
        'device_identifier',
        'device_name',
        'platform',
        'ip_address',
        'user_agent',
        'last_seen_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Revocation is explicit and recorded, never a silent row deletion, so that
     * "this device was revoked, by whom, and when" stays answerable.
     */
    public function revoke(string $revokedByUserId): void
    {
        $this->revoked_at = now();
        $this->revoked_by_user_id = $revokedByUserId;
        $this->save();
    }
}
