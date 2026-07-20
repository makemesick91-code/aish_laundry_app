<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Organization\Models\LaundryBrand;
use App\Modules\Organization\Models\Outlet;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * THE ISOLATION BOUNDARY and the billing boundary (Rule 02).
 *
 * Hierarchy: User Account -> Membership -> Tenant/Organization -> Laundry Brand
 * -> Outlet.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $timezone
 */
class Tenant extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'timezone',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'tenant_id');
    }

    public function laundryBrands(): HasMany
    {
        return $this->hasMany(LaundryBrand::class, 'tenant_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'tenant_id');
    }
}
