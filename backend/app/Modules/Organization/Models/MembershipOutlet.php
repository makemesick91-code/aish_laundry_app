<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\SharedKernel\Concerns\HasOptimisticVersion;
use App\Modules\Tenancy\Models\Membership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * THE BINDING BETWEEN AN EXISTING MEMBERSHIP AND AN EXISTING OUTLET.
 *
 * This is the whole of Step 4's staff-assignment scope. It confers NO permission
 * of its own: a membership's permissions come from `membership_role` and
 * `PermissionRegistry`, exactly as they did in Step 3. An assignment answers
 * "which counter does this person work at", never "what may this person do"
 * (DEC-0031 A2, Rule 40).
 *
 * That separation matters. If an assignment granted capability, assigning
 * somebody to an outlet would be a privilege change wearing the name of a
 * roster edit, and the escalation guard on role assignment could be walked
 * around entirely.
 *
 * REVOKED, NEVER DELETED. `revoked_at` and `revoked_by_membership_id` keep the
 * history a later audit will need. Both are set together or neither is — a
 * database CHECK enforces it, because a half-written audit fact is worse than
 * none.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $membership_id
 * @property string $outlet_id
 */
class MembershipOutlet extends Model
{
    use HasFactory;
    use HasOptimisticVersion;
    use HasUuids;

    protected $table = 'membership_outlet';

    /**
     * DELIBERATELY EMPTY.
     *
     * Every column on this table is either an identity resolved server-side
     * (`tenant_id`, `membership_id`, `outlet_id`) or an audit fact written by the
     * registry (`assigned_by_membership_id`, `assigned_at`, `revoked_at`,
     * `revoked_by_membership_id`). None of them may come from a request body, so
     * none of them is mass-assignable (threat T-05).
     *
     * @var list<string>
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Assignments that are currently in force. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
