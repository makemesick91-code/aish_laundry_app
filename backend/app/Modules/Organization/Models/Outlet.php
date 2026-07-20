<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical location belonging to a brand (Rule 02, hard rule 4).
 *
 * `outlets` carries `tenant_id` directly, bound to the brand's tenant by a
 * composite foreign key, so an outlet can never belong to a brand in a different
 * tenant. Every outlet lookup in this application is tenant-scoped; a missing
 * scope yields NOTHING rather than another tenant's rows (Rule 02, hard rule 8 —
 * fail closed, never fail open).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $laundry_brand_id
 * @property string $name
 * @property string $code
 * @property string $timezone
 */
class Outlet extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'outlets';

    protected $fillable = [
        'tenant_id',
        'laundry_brand_id',
        'name',
        'code',
        'timezone',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function laundryBrand(): BelongsTo
    {
        return $this->belongsTo(LaundryBrand::class, 'laundry_brand_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
