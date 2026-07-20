<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A commercial brand owned by a tenant (Rule 02, hard rule 3).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 */
class LaundryBrand extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'laundry_brands';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'laundry_brand_id');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
