<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Authorization\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MEMBERSHIP is the join between a user and a tenant, and it is the ONLY source
 * of tenant authorization (Rule 02; DEC-0025 §2).
 *
 * A user who is an owner in tenant A and a kasir in tenant B has two memberships
 * and two unrelated role sets. Neither is reachable from the other.
 *
 * STATUS IS AN AUTHORIZATION FACT, NOT A LABEL. Only `active` grants access.
 * `invited`, `suspended` and `revoked` all grant nothing, and each produces a
 * DISTINCT error code so a user is told what actually happened rather than being
 * left to guess (Rule 29 hard rule 9 — errors explain recovery).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property string $status
 */
class Membership extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REVOKED = 'revoked';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_INVITED,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_REVOKED,
    ];

    protected $table = 'memberships';

    /**
     * `status` is deliberately ABSENT from $fillable.
     *
     * A membership's status is changed only through the explicit domain methods
     * below, each of which is audited. Mass-assigning it from a request body is
     * exactly the kind of silent privilege change Rule 02 exists to prevent.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Role assignments. The pivot carries `tenant_id`, bound to this membership's
     * tenant by the composite foreign key `membership_role_tenant_membership_foreign`,
     * so a cross-tenant assignment is rejected by PostgreSQL (DEC-0025 §4).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role', 'membership_id', 'role_id')
            ->withPivot(['id', 'tenant_id'])
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function markActive(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->accepted_at ??= now();
        $this->revoked_at = null;
        $this->save();
    }

    public function markSuspended(): void
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->save();
    }

    /**
     * Revocation is recorded, never a row deletion.
     *
     * DEC-0025 §6: "Membership revocation immediately invalidates tenant access.
     * The next authorization decision returns zero effective tenant permissions.
     * Nothing waits for a token to expire."
     */
    public function markRevoked(): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->revoked_at = now();
        $this->save();
    }
}
