<?php

declare(strict_types=1);

namespace App\Modules\CustomerManagement\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant-scoped customer profile (FR-021).
 *
 * TENANT SCOPE IS EXPLICIT, NOT AMBIENT
 * -------------------------------------
 * `scopeForTenant()` is required at every call site, exactly as `Outlet` does
 * it. A global scope reading an ambient tenant would be shorter to write and
 * would silently return nothing — or worse, everything — in a queue worker or a
 * console command where no request context exists (Rule 02, hard rule 8; Rule 20,
 * hard rule 6).
 *
 * `phone_normalized` and `code` are NOT mass-assignable. Both are derived
 * server-side: the normalized phone by `PhoneNumber::normalize()`, the code by
 * the tenant's own sequence. Accepting either from a client would let a caller
 * choose the value the FR-022 uniqueness constraint is checked against
 * (T-05, mass assignment).
 *
 * `tenant_id` is likewise absent from `$fillable`; it comes from the verified
 * `TenantContext`, never from input.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $phone
 * @property string $phone_normalized
 * @property string|null $email
 * @property string|null $internal_notes
 * @property string $status
 */
class Customer extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'customers';

    /**
     * Deliberately narrow. See the class docblock: `tenant_id`, `code` and
     * `phone_normalized` are all server-derived and must never be settable from
     * a request payload.
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'internal_notes',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class, 'customer_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * The current consent state for a type, or null when never recorded.
     *
     * Reads the LATEST record rather than a mutable flag (invariant C6). The
     * secondary sort on `id` makes the result deterministic when two records
     * share a timestamp — without it, "the latest" would be whichever row the
     * planner happened to return, and consent would be non-deterministic at
     * exactly the moment it matters most.
     */
    public function currentConsentState(string $consentType): ?string
    {
        return $this->consents()
            ->where('tenant_id', $this->tenant_id)
            ->where('consent_type', $consentType)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('state');
    }

    /**
     * Marketing may be sent only on an explicit, current grant.
     *
     * Absence of any record is NOT consent (Rule 08, hard rule 5). A customer
     * who has never been asked has not agreed.
     */
    public function hasMarketingConsent(string $consentType): bool
    {
        return $this->currentConsentState($consentType) === CustomerConsent::STATE_GRANTED;
    }
}
